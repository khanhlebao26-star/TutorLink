<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BeD04IdempotencyMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_fee_create_replay_canonical_hash_and_conflicts_are_distinct(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $path = '/api/v1/programs/'.$program->id.'/fee-records';
        $period = $this->freePeriod($program);
        $key = (string) Str::uuid();
        $body = ['period' => $period, 'amount_minor' => 32100];

        $created = $this->request($owner, 'POST', $path, $body, $key, 'D04-14-fee-create');
        $created->assertCreated()->assertJsonPath('data.period', $period)->assertJsonPath('data.currency', 'VND');
        $feeId = (int) $created->json('data.id');
        $receipt = DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($receipt);
        $canonicalJson = json_encode(
            ['amount_minor' => 32100, 'currency' => 'VND', 'period' => $period],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR,
        );
        $this->assertSame(hash('sha256', "POST\n{$path}\n{$canonicalJson}"), $receipt->request_hash);
        $this->assertSame($path, $receipt->canonical_path);
        $this->assertSame('POST', $receipt->method);
        $this->assertSame(201, (int) $receipt->response_status);
        $this->assertNull($receipt->expires_at);

        $beforeReplayAudit = DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $beforeReplayOutbox = DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $reorderedAndExplicitDefault = $this->request($owner, 'POST', $path, [
            'amount_minor' => 32100,
            'currency' => 'VND',
            'period' => $period,
        ], strtoupper($key), 'D04-14-fee-replay-reordered-default');
        $reorderedAndExplicitDefault->assertCreated();
        $this->assertEquals($created->json(), $reorderedAndExplicitDefault->json());
        $this->assertSame($beforeReplayAudit, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->assertSame($beforeReplayOutbox, DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->assertSame(1, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('idempotency_key', $key)->count());

        $changedBody = $this->request($owner, 'POST', $path, ['period' => $period, 'amount_minor' => 32101], $key, 'D04-14-fee-replay-body-conflict');
        $changedBody->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        $changedPath = $this->request($owner, 'POST', '/api/v1/fee-records/'.$feeId.'/mark-paid', [
            'expected_version' => 1,
        ], $key, 'D04-14-fee-replay-path-conflict');
        $changedPath->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        $changedMethodAndPath = $this->request($owner, 'PATCH', '/api/v1/fee-records/'.$feeId, [
            'expected_version' => 1,
            'amount_minor' => 32100,
            'reason' => 'Same amount, different operation.',
        ], $key, 'D04-14-fee-replay-method-conflict');
        $changedMethodAndPath->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        $updated = $this->request($owner, 'PATCH', '/api/v1/fee-records/'.$feeId, [
            'expected_version' => 1,
            'amount_minor' => 40000,
            'reason' => 'A later real edit.',
        ], (string) Str::uuid(), 'D04-14-fee-edit-after-create');
        $updated->assertOk()->assertJsonPath('data.version', 2);
        $auditAfterEdit = DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $outboxAfterEdit = DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();

        $historicalCreateReplay = $this->request($owner, 'POST', $path, $body, $key, 'D04-14-fee-create-replay-after-edit');
        $historicalCreateReplay->assertCreated();
        $this->assertEquals($created->json(), $historicalCreateReplay->json());
        $this->assertSame(1, $historicalCreateReplay->json('data.version'));
        $this->assertSame(2, DB::table('fee_records')->where('id', $feeId)->value('version'));
        $this->assertSame($auditAfterEdit, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->assertSame($outboxAfterEdit, DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
    }

    public function test_fee_noop_new_key_only_adds_receipt_and_stale_same_value_conflicts(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $collectionPath = '/api/v1/programs/'.$program->id.'/fee-records';
        $period = $this->freePeriod($program);
        $created = $this->request($owner, 'POST', $collectionPath, [
            'period' => $period,
            'amount_minor' => 1000,
        ], (string) Str::uuid(), 'D04-14-fee-noop-create');
        $feeId = (int) $created->json('data.id');
        $itemPath = '/api/v1/fee-records/'.$feeId;
        $key = (string) Str::uuid();
        $auditBeforeNoop = DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $outboxBeforeNoop = DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $receiptBeforeNoop = DB::table('idempotency_receipts')->where('actor_id', $owner->id)->count();
        $updatedAtBeforeNoop = DB::table('fee_records')->where('id', $feeId)->value('updated_at');

        $noop = $this->request($owner, 'PATCH', $itemPath, [
            'expected_version' => 1,
            'amount_minor' => 1000,
            'reason' => 'Reason is required even when amount is unchanged.',
        ], $key, 'D04-14-fee-patch-noop-new-key');
        $noop->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.updated_at', $created->json('data.updated_at'));
        $this->assertSame($updatedAtBeforeNoop, DB::table('fee_records')->where('id', $feeId)->value('updated_at'));
        $this->assertSame($auditBeforeNoop, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->assertSame($outboxBeforeNoop, DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->assertSame($receiptBeforeNoop + 1, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->count());

        $noopReplay = $this->request($owner, 'PATCH', $itemPath, [
            'expected_version' => 1,
            'amount_minor' => 1000,
            'reason' => 'Reason is required even when amount is unchanged.',
        ], $key, 'D04-14-fee-patch-noop-replay');
        $this->assertEquals($noop->json(), $noopReplay->json());
        $this->assertSame($receiptBeforeNoop + 1, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->count());

        $changedNoopReason = $this->request($owner, 'PATCH', $itemPath, [
            'expected_version' => 1,
            'amount_minor' => 1000,
            'reason' => 'A semantically different reason.',
        ], $key, 'D04-14-fee-patch-noop-payload-conflict');
        $changedNoopReason->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        $update = $this->request($owner, 'PATCH', $itemPath, [
            'expected_version' => 1,
            'amount_minor' => 2000,
            'reason' => 'Change the amount.',
        ], (string) Str::uuid(), 'D04-14-fee-update');
        $update->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.amount_minor', 2000);
        $staleSameCurrentValue = $this->request($owner, 'PATCH', $itemPath, [
            'expected_version' => 1,
            'amount_minor' => 2000,
            'reason' => 'Stale version despite matching current amount.',
        ], (string) Str::uuid(), 'D04-14-fee-stale-same-current-value');
        $staleSameCurrentValue->assertConflict()->assertJsonPath('code', 'stale_version')->assertJsonPath('errors.current_version', 2);
    }

    public function test_publish_replay_returns_old_snapshot_after_terminal_change_but_checks_current_owner(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $draft = $this->actingAs($owner)->postJson('/api/v1/programs/'.$program->id.'/announcements', [
            'title' => 'D04-14 idempotent publish',
            'body' => 'Replay keeps the committed snapshot.',
        ]);
        $this->assertSame(201, $draft->getStatusCode());
        $announcementId = (int) $draft->json('data.id');
        $publishPath = '/api/v1/announcements/'.$announcementId.'/publish';
        $key = (string) Str::uuid();
        $published = $this->publish($owner, $publishPath, 1, $key, 'D04-14-publish');
        $published->assertOk()->assertJsonPath('data.state', 'published')->assertJsonPath('data.version', 2);
        $receipt = DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($receipt);
        $this->assertSame(hash('sha256', "POST\n{$publishPath}\n{\"expected_version\":1}"), $receipt->request_hash);
        $this->assertSame(200, (int) $receipt->response_status);

        $auditBeforeReplay = DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();
        $outboxBeforeReplay = DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();
        $replay = $this->publish($owner, $publishPath, 1, strtoupper($key), 'D04-14-publish-replay');
        $this->assertEquals($published->json(), $replay->json());
        $this->assertSame($auditBeforeReplay, DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertSame($outboxBeforeReplay, DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertSame(1, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('idempotency_key', $key)->count());

        $feePath = '/api/v1/programs/'.$program->id.'/fee-records';
        $crossResourceKey = $this->request($owner, 'POST', $feePath, [
            'period' => '2026-11',
            'amount_minor' => 2000,
        ], $key, 'D04-14-publish-key-cross-resource-conflict');
        $crossResourceKey->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        DB::table('programs')->where('id', $program->id)->update(['status' => 'completed']);
        $terminalReplay = $this->publish($owner, $publishPath, 1, $key, 'D04-14-publish-replay-after-terminal');
        $this->assertEquals($published->json(), $terminalReplay->json());
        $terminalNewKey = $this->publish($owner, $publishPath, 2, (string) Str::uuid(), 'D04-14-publish-new-key-terminal-conflict');
        $terminalNewKey->assertConflict()->assertJsonPath('code', 'program_state_conflict');

        $newOwner = $this->user('be-d04-other-tutor@tutorlink.test');
        DB::table('programs')->where('id', $program->id)->update([
            'owner_tutor_profile_id' => $newOwner->tutorProfile->id,
            'status' => 'active',
        ]);
        $permissionRevoked = $this->publish($owner, $publishPath, 1, $key, 'D04-14-publish-replay-permission-revoked');
        $this->assertContains($permissionRevoked->getStatusCode(), [403, 404]);
        $this->assertNotEquals($published->json(), $permissionRevoked->json());
        $this->assertSame(1, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('idempotency_key', $key)->count());
    }

    public function test_progress_and_draft_do_not_create_idempotency_receipts(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $key = (string) Str::uuid();
        $record = DB::table('progress_records')
            ->where('program_id', $program->id)
            ->where('note', 'D04_FIXTURE_PROGRESS_001')
            ->first();
        $this->assertNotNull($record);
        $progressPath = '/api/v1/progress-records/'.$record->id;
        $progressBody = ['expected_version' => (int) $record->version, 'value' => (int) $record->value + 1];
        $updated = $this->actingAs($owner)->patchJson($progressPath, $progressBody, ['Idempotency-Key' => $key]);
        $this->recordApi('D04-14-progress-key-ignored-first-write', $owner, 'PATCH', $progressPath, $progressBody, $updated, $key);
        $updated->assertOk();
        $retry = $this->actingAs($owner)->patchJson($progressPath, $progressBody, ['Idempotency-Key' => $key]);
        $this->recordApi('D04-14-progress-key-ignored-retry', $owner, 'PATCH', $progressPath, $progressBody, $retry, $key);
        $retry->assertConflict()->assertJsonPath('code', 'stale_version');
        $this->assertDatabaseMissing('idempotency_receipts', ['actor_id' => $owner->id, 'idempotency_key' => $key]);

        $draftKey = (string) Str::uuid();
        $draftPath = '/api/v1/programs/'.$program->id.'/announcements';
        $draftBody = ['title' => 'Draft key has no replay guarantee', 'body' => 'Each POST creates a Draft.'];
        $firstDraft = $this->actingAs($owner)->postJson($draftPath, $draftBody, ['Idempotency-Key' => $draftKey]);
        $this->recordApi('D04-14-draft-key-ignored-first-post', $owner, 'POST', $draftPath, $draftBody, $firstDraft, $draftKey);
        $secondDraft = $this->actingAs($owner)->postJson($draftPath, $draftBody, ['Idempotency-Key' => $draftKey]);
        $this->recordApi('D04-14-draft-key-ignored-retry', $owner, 'POST', $draftPath, $draftBody, $secondDraft, $draftKey);
        $firstDraft->assertCreated();
        $secondDraft->assertCreated();
        $this->assertNotSame($firstDraft->json('data.id'), $secondDraft->json('data.id'));
        $this->assertDatabaseMissing('idempotency_receipts', ['actor_id' => $owner->id, 'idempotency_key' => $draftKey]);
    }

    private function request(User $actor, string $method, string $path, array $body, string $key, string $case): TestResponse
    {
        $headers = ['Idempotency-Key' => $key];
        $response = match ($method) {
            'PATCH' => $this->actingAs($actor)->patchJson($path, $body, $headers),
            default => $this->actingAs($actor)->postJson($path, $body, $headers),
        };
        $this->recordApi($case, $actor, $method, $path, $body, $response, $key);

        return $response;
    }

    private function publish(User $actor, string $path, int $version, string $key, string $case): TestResponse
    {
        $body = ['expected_version' => $version];
        $response = $this->actingAs($actor)->postJson($path, $body, ['Idempotency-Key' => $key]);
        $this->recordApi($case, $actor, 'POST', $path, $body, $response, $key);

        return $response;
    }

    private function freePeriod(Program $program): string
    {
        $timezone = new \DateTimeZone($program->timezone);
        $month = CarbonImmutable::parse($program->activated_at)->setTimezone($timezone)->startOfMonth();
        $lastMonth = CarbonImmutable::now($timezone)->startOfMonth();

        while ($month->lessThanOrEqualTo($lastMonth)) {
            if (! DB::table('fee_records')->where('program_id', $program->id)->where('period_start', $month->toDateString())->exists()) {
                return $month->format('Y-m');
            }

            $month = $month->addMonth();
        }

        // The fixture intentionally covers every month. Free its earliest row
        // inside this test transaction so the API can exercise a real create.
        $fixtureFee = DB::table('fee_records')->where('program_id', $program->id)->orderBy('period_start')->first();
        $this->assertNotNull($fixtureFee, 'The D04 active Program should have a Fee fixture row.');
        DB::table('fee_records')->where('id', $fixtureFee->id)->delete();

        return substr($fixtureFee->period_start, 0, 7);
    }

    private function program(string $title): Program
    {
        return Program::query()->where('title', $title)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function recordApi(string $case, User $actor, string $method, string $path, array $body, TestResponse $response, string $key): void
    {
        fwrite(STDOUT, json_encode([
            'case' => $case,
            'actor' => $actor->role,
            'request' => ['method' => $method, 'path' => $path, 'headers' => ['Idempotency-Key' => $key], 'body' => $body],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID')],
                'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL);
    }
}
