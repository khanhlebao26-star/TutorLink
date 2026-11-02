<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

class BeD04RollbackFaultInjectionTest extends TestCase
{
    private User $owner;

    private Program $active;

    private Program $other;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabaseGuard::assertConnectedIdentity(config());
        $this->assertSame('pgsql', DB::getDriverName());
        $this->owner = User::query()->where('email', 'be-d04-owner@tutorlink.test')->firstOrFail();
        $this->active = Program::query()->where('title', 'D04 Fixture Active')->firstOrFail();
        $this->other = Program::query()->where('title', 'D04 Fixture Paused')->firstOrFail();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_announcement_file_link_and_unlink_faults_rollback_and_retry(): void
    {
        $this->prepareProgram($this->active->id);

        foreach (['insert', 'delete'] as $boundary) {
            $oldFileId = $this->cleanFile("{$boundary}-old");
            $newFileId = $this->cleanFile("{$boundary}-new");
            $announcementId = $this->createDraft($this->active->id, "File {$boundary} fault", [$oldFileId]);
            $path = "/api/v1/announcements/{$announcementId}";
            $body = ['expected_version' => 1, 'file_ids' => [$newFileId]];
            $before = $this->announcementSnapshot($announcementId, null);
            $trigger = "d04_16_file_{$boundary}";
            $tableEvent = $boundary === 'insert' ? 'INSERT' : 'DELETE';
            $condition = $boundary === 'insert'
                ? "NEW.resource_type = 'announcement' AND NEW.resource_id = {$announcementId} AND NEW.file_id = {$newFileId}"
                : "OLD.resource_type = 'announcement' AND OLD.resource_id = {$announcementId} AND OLD.file_id = {$oldFileId}";
            $failed = $this->withFault($trigger, 'file_links', $tableEvent, $condition,
                fn () => $this->actingAs($this->owner)->patchJson($path, $body));
            $failed->assertServerError();
            $afterFailure = $this->announcementSnapshot($announcementId, null);
            $this->assertSame($before, $afterFailure);
            $this->assertFaultAbsent($trigger);

            $retry = $this->actingAs($this->owner)->patchJson($path, $body);
            $retry->assertOk()->assertJsonPath('data.version', 2);
            $afterRetry = $this->announcementSnapshot($announcementId, null);
            $this->assertSame([$newFileId], array_column($afterRetry['file_links'], 'file_id'));
            $this->assertSame(2, (int) $afterRetry['announcement']['version']);
            $this->assertSame(2, count(array_filter($afterRetry['audit'], fn (array $row): bool => $row['action'] === 'file.link')));
            $this->assertSame(1, count(array_filter($afterRetry['audit'], fn (array $row): bool => $row['action'] === 'file.unlink')));
            $this->recordCase("announcement_file_{$boundary}", [
                'method' => 'PATCH', 'path' => $path, 'body' => $body,
            ], $failed, $retry, $before, $afterFailure, $afterRetry);
        }
    }

    public function test_fee_update_audit_and_outbox_faults_rollback_and_retry(): void
    {
        $this->prepareProgram($this->other->id);

        foreach (['audit', 'outbox'] as $boundary) {
            $month = $boundary === 'audit' ? '2099-01' : '2099-02';
            $feeId = $this->insertFee($this->other->id, $month, 24000);
            $path = "/api/v1/fee-records/{$feeId}";
            $body = ['expected_version' => 1, 'amount_minor' => 25000, 'reason' => "D04-16 {$boundary} retry"];
            $key = (string) Str::uuid();
            $request = ['method' => 'PATCH', 'path' => $path, 'body' => $body, 'headers' => ['Idempotency-Key' => $key]];
            $before = $this->feeSnapshot($feeId, $key);
            $trigger = "d04_16_fee_{$boundary}";
            $table = $boundary === 'audit' ? 'audit_logs' : 'notification_outbox';
            $condition = $boundary === 'audit'
                ? "NEW.action = 'fee_record.update' AND NEW.resource_id = {$feeId}"
                : "NEW.type = 'fee_record_changed' AND NEW.resource_type = 'fee_record' AND NEW.resource_id = {$feeId}";
            $failed = $this->withFault($trigger, $table, 'INSERT', $condition,
                fn () => $this->actingAs($this->owner)->patchJson($path, $body, $request['headers']));
            $failed->assertServerError();
            $afterFailure = $this->feeSnapshot($feeId, $key);
            $this->assertSame($before, $afterFailure);
            $this->assertFaultAbsent($trigger);

            $retry = $this->actingAs($this->owner)->patchJson($path, $body, $request['headers']);
            $retry->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.amount_minor', 25000);
            $afterRetry = $this->feeSnapshot($feeId, $key);
            $this->assertSame(2, (int) $afterRetry['fee']['version']);
            $this->assertSame(1, count($afterRetry['audit']));
            $this->assertSame($this->recipientCount($this->other->id), count($afterRetry['outbox']));
            $this->assertSame(1, count($afterRetry['receipts']));
            $this->recordCase("fee_update_{$boundary}", $request, $failed, $retry, $before, $afterFailure, $afterRetry);
        }
    }

    public function test_fee_create_receipt_fault_rolls_back_resource_audit_and_outbox_then_retries(): void
    {
        $this->prepareProgram($this->other->id);
        $path = "/api/v1/programs/{$this->other->id}/fee-records";
        $body = ['period' => '2022-02', 'amount_minor' => 26000];
        $key = (string) Str::uuid();
        $request = ['method' => 'POST', 'path' => $path, 'body' => $body, 'headers' => ['Idempotency-Key' => $key]];
        $before = $this->feePeriodSnapshot($this->other->id, '2022-02', $key);
        $condition = "NEW.actor_id = {$this->owner->id} AND NEW.idempotency_key = '{$key}' AND NEW.canonical_path = '{$path}'";
        $failed = $this->withFault('d04_16_fee_receipt', 'idempotency_receipts', 'INSERT', $condition,
            fn () => $this->actingAs($this->owner)->postJson($path, $body, $request['headers']));
        $failed->assertServerError();
        $afterFailure = $this->feePeriodSnapshot($this->other->id, '2022-02', $key);
        $this->assertSame($before, $afterFailure);
        $this->assertFaultAbsent('d04_16_fee_receipt');

        $retry = $this->actingAs($this->owner)->postJson($path, $body, $request['headers']);
        $retry->assertCreated()->assertJsonPath('data.period', '2022-02');
        $feeId = (int) $retry->json('data.id');
        $afterRetry = $this->feeSnapshot($feeId, $key);
        $this->assertSame(1, count($afterRetry['audit']));
        $this->assertSame($this->recipientCount($this->other->id), count($afterRetry['outbox']));
        $this->assertSame(1, count($afterRetry['receipts']));
        $this->recordCase('fee_create_receipt', $request, $failed, $retry, $before, $afterFailure, $afterRetry);
    }

    public function test_announcement_publish_audit_outbox_and_receipt_faults_rollback_then_retry(): void
    {
        $this->prepareProgram($this->other->id);

        foreach (['audit', 'outbox', 'receipt'] as $boundary) {
            $fileId = $this->cleanFile("publish-{$boundary}");
            $announcementId = $this->createDraft($this->other->id, "Publish {$boundary} fault", [$fileId]);
            $path = "/api/v1/announcements/{$announcementId}/publish";
            $body = ['expected_version' => 1];
            $key = (string) Str::uuid();
            $request = ['method' => 'POST', 'path' => $path, 'body' => $body, 'headers' => ['Idempotency-Key' => $key]];
            $before = $this->announcementSnapshot($announcementId, $key);
            $trigger = "d04_16_announcement_{$boundary}";
            [$table, $condition] = match ($boundary) {
                'audit' => ['audit_logs', "NEW.action = 'announcement.publish' AND NEW.resource_id = {$announcementId}"],
                'outbox' => ['notification_outbox', "NEW.type = 'announcement_published' AND NEW.resource_id = {$announcementId}"],
                default => ['idempotency_receipts', "NEW.actor_id = {$this->owner->id} AND NEW.idempotency_key = '{$key}' AND NEW.canonical_path = '{$path}'"],
            };
            $failed = $this->withFault($trigger, $table, 'INSERT', $condition,
                fn () => $this->actingAs($this->owner)->postJson($path, $body, $request['headers']));
            $failed->assertServerError();
            $afterFailure = $this->announcementSnapshot($announcementId, $key);
            $this->assertSame($before, $afterFailure);
            $this->assertFaultAbsent($trigger);

            $retry = $this->actingAs($this->owner)->postJson($path, $body, $request['headers']);
            $retry->assertOk()->assertJsonPath('data.state', 'published')->assertJsonPath('data.version', 2);
            $afterRetry = $this->announcementSnapshot($announcementId, $key);
            $this->assertSame([$fileId], array_column($afterRetry['file_links'], 'file_id'));
            $this->assertSame(1, count(array_filter($afterRetry['audit'], fn (array $row): bool => $row['action'] === 'announcement.publish')));
            $this->assertSame($this->recipientCount($this->other->id), count($afterRetry['outbox']));
            $this->assertSame(1, count($afterRetry['receipts']));
            $this->recordCase("announcement_publish_{$boundary}", $request, $failed, $retry, $before, $afterFailure, $afterRetry);
        }
    }

    private function prepareProgram(int $programId): void
    {
        $program = Program::query()->findOrFail($programId);
        $now = now('UTC')->format('Y-m-d H:i:s.uP');
        DB::table('programs')->where('id', $programId)->update([
            'status' => 'active',
            'activated_at' => '2022-01-01 00:00:00+07:00',
            'ends_at' => null,
            'updated_at' => $now,
        ]);
        DB::table('program_members')->where('program_id', $programId)->where('user_id', $program->customer_id)->update([
            'status' => 'active',
            'left_at' => null,
            'updated_at' => $now,
        ]);
        $this->assertGreaterThan(0, $this->recipientCount($programId));
    }

    private function createDraft(int $programId, string $title, array $fileIds = []): int
    {
        $body = ['title' => $title, 'body' => 'D04-16 rollback fixture.'];
        if ($fileIds !== []) {
            $body['file_ids'] = $fileIds;
        }
        $response = $this->actingAs($this->owner)->postJson("/api/v1/programs/{$programId}/announcements", $body);
        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    private function cleanFile(string $label): int
    {
        $bytes = "%PDF-1.4\nD04-16 fixture {$label}.\n%%EOF";
        $now = now('UTC')->format('Y-m-d H:i:s.uP');

        return DB::table('files')->insertGetId([
            'owner_id' => $this->owner->id,
            'disk' => 'local',
            'object_key' => 'd04-16/'.Str::uuid().'.pdf',
            'original_name' => $label.'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'visibility' => 'private',
            'checksum_sha256' => hash('sha256', $bytes),
            'scan_status' => 'clean',
            'purpose' => 'announcement_attachment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertFee(int $programId, string $period, int $amount): int
    {
        $start = $period.'-01';
        $now = now('UTC')->format('Y-m-d H:i:s.uP');

        return DB::table('fee_records')->insertGetId([
            'program_id' => $programId,
            'period_start' => $start,
            'period_end' => date('Y-m-t', strtotime($start)),
            'amount_minor' => $amount,
            'currency' => 'VND',
            'status' => 'declared',
            'tutor_payment_mark' => 'unmarked',
            'version' => 1,
            'payment_marked_at' => null,
            'payment_marked_by' => null,
            'payment_mark_reason' => null,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
            'last_change_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function announcementSnapshot(int $announcementId, ?string $key): array
    {
        $announcement = DB::table('announcements')->where('id', $announcementId)->first([
            'id', 'program_id', 'title', 'body', 'published_at', 'version', 'updated_at',
        ]);

        return [
            'announcement' => $announcement === null ? null : (array) $announcement,
            'file_links' => DB::table('file_links')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->orderBy('file_id')->get(['file_id', 'purpose'])->map(fn ($row): array => (array) $row)->all(),
            'audit' => DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->orderBy('id')->get(['action', 'before', 'after', 'request_id'])->map(fn ($row): array => (array) $row)->all(),
            'outbox' => DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->orderBy('id')->get(['user_id', 'type', 'event_key', 'payload'])->map(fn ($row): array => (array) $row)->all(),
            'receipts' => $key === null ? [] : DB::table('idempotency_receipts')->where('actor_id', $this->owner->id)->where('idempotency_key', $key)->get(['method', 'canonical_path', 'response_status', 'response_snapshot'])->map(fn ($row): array => (array) $row)->all(),
            'totals' => $this->tableCounts(),
        ];
    }

    private function feeSnapshot(int $feeId, string $key): array
    {
        $fee = DB::table('fee_records')->where('id', $feeId)->first([
            'id', 'program_id', 'period_start', 'period_end', 'amount_minor', 'status', 'tutor_payment_mark',
            'version', 'payment_marked_at', 'payment_marked_by', 'voided_at', 'voided_by', 'last_change_reason', 'updated_at',
        ]);

        return [
            'fee' => $fee === null ? null : (array) $fee,
            'audit' => DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->orderBy('id')->get(['action', 'before', 'after', 'reason', 'request_id'])->map(fn ($row): array => (array) $row)->all(),
            'outbox' => DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->orderBy('id')->get(['user_id', 'type', 'event_key', 'payload'])->map(fn ($row): array => (array) $row)->all(),
            'receipts' => DB::table('idempotency_receipts')->where('actor_id', $this->owner->id)->where('idempotency_key', $key)->get(['method', 'canonical_path', 'response_status', 'response_snapshot'])->map(fn ($row): array => (array) $row)->all(),
            'totals' => $this->tableCounts(),
        ];
    }

    private function feePeriodSnapshot(int $programId, string $period, string $key): array
    {
        return [
            'fees' => DB::table('fee_records')->where('program_id', $programId)->where('period_start', $period.'-01')->get()->map(fn ($row): array => (array) $row)->all(),
            'audit_total' => DB::table('audit_logs')->count(),
            'outbox_total' => DB::table('notification_outbox')->count(),
            'fee_total' => DB::table('fee_records')->count(),
            'file_link_total' => DB::table('file_links')->count(),
            'receipts' => DB::table('idempotency_receipts')->where('actor_id', $this->owner->id)->where('idempotency_key', $key)->get(['method', 'canonical_path', 'response_status', 'response_snapshot'])->map(fn ($row): array => (array) $row)->all(),
        ];
    }

    private function tableCounts(): array
    {
        return [
            'files' => DB::table('files')->count(),
            'announcements' => DB::table('announcements')->count(),
            'file_links' => DB::table('file_links')->count(),
            'fee_records' => DB::table('fee_records')->count(),
            'audit_logs' => DB::table('audit_logs')->count(),
            'notification_outbox' => DB::table('notification_outbox')->count(),
            'idempotency_receipts' => DB::table('idempotency_receipts')->count(),
        ];
    }

    private function recipientCount(int $programId): int
    {
        return DB::table('program_members')
            ->join('users', 'users.id', '=', 'program_members.user_id')
            ->where('program_members.program_id', $programId)
            ->where('program_members.member_role', 'customer')
            ->where('program_members.status', 'active')
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->where('users.id', '<>', $this->owner->id)
            ->count();
    }

    private function withFault(string $name, string $table, string $event, string $condition, Closure $request): TestResponse
    {
        $this->assertMatchesRegularExpression('/^d04_16_[a-z0-9_]+$/', $name);
        $this->assertContains($table, ['file_links', 'audit_logs', 'notification_outbox', 'idempotency_receipts']);
        $this->assertContains($event, ['INSERT', 'DELETE']);
        $functionCreated = false;
        $triggerCreated = false;

        try {
            DB::unprepared("CREATE FUNCTION {$name}() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'D04-16 injected failure'; RETURN NEW; END $$");
            $functionCreated = true;
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} FOR EACH ROW WHEN ({$condition}) EXECUTE FUNCTION {$name}()");
            $triggerCreated = true;

            return $request();
        } finally {
            if ($triggerCreated) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name} ON {$table}");
            }
            if ($functionCreated) {
                DB::unprepared("DROP FUNCTION IF EXISTS {$name}()");
            }
            $this->assertFaultAbsent($name);
        }
    }

    private function assertFaultAbsent(string $name): void
    {
        $triggerCount = (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM pg_trigger WHERE tgname = ? AND NOT tgisinternal', [$name])->aggregate;
        $functionCount = (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM pg_proc WHERE proname = ?', [$name])->aggregate;
        $this->assertSame(0, $triggerCount);
        $this->assertSame(0, $functionCount);
    }

    private function recordCase(string $case, array $request, TestResponse $failed, TestResponse $retry, array $before, array $afterFailure, array $afterRetry): void
    {
        fwrite(STDOUT, json_encode([
            'case' => $case,
            'failed_request' => $request,
            'failed_response' => $this->responseState($failed),
            'state_before' => $before,
            'state_after_failure' => $afterFailure,
            'fault_hook_removed_before_retry' => true,
            'retry_request' => $request,
            'retry_response' => $this->responseState($retry),
            'state_after_retry' => $afterRetry,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function responseState(TestResponse $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'request_id' => $response->headers->get('X-Request-ID'),
            'body' => $response->json() ?? $response->getContent(),
        ];
    }
}
