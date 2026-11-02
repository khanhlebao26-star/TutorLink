<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BeD04OutboxRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('D04 worker and retention validation requires PostgreSQL.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_announcement_and_fee_events_pause_resume_and_deliver_once_to_snapshot_recipients(): void
    {
        $this->deferExistingOutboxEvents();
        $program = $this->program();
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        DB::table('fee_records')->where('program_id', $program->id)->delete();

        $announcement = $this->api($owner, 'POST', '/api/v1/programs/'.$program->id.'/announcements', [
            'title' => 'D04 worker delivery',
            'body' => 'Synthetic announcement for outbox validation.',
        ], null, 'D04-17-announcement-create');
        $announcement->assertCreated();
        $announcementId = (int) $announcement->json('data.id');
        $announcementKey = (string) Str::uuid();
        $published = $this->api($owner, 'POST', '/api/v1/announcements/'.$announcementId.'/publish', [
            'expected_version' => 1,
        ], $announcementKey, 'D04-17-announcement-publish');
        $published->assertOk()->assertJsonPath('data.state', 'published');

        $feeKey = (string) Str::uuid();
        $fee = $this->api($owner, 'POST', '/api/v1/programs/'.$program->id.'/fee-records', [
            'period' => '2026-11',
            'amount_minor' => 325000,
        ], $feeKey, 'D04-17-fee-create');
        $fee->assertCreated();
        $feeId = (int) $fee->json('data.id');

        $announcementEvent = DB::table('notification_outbox')
            ->where('resource_type', 'announcement')->where('resource_id', $announcementId)->first();
        $feeEvent = DB::table('notification_outbox')
            ->where('resource_type', 'fee_record')->where('resource_id', $feeId)->first();

        $this->assertNotNull($announcementEvent);
        $this->assertNotNull($feeEvent);
        $this->assertSame((int) $member->id, (int) $announcementEvent->user_id);
        $this->assertSame('announcement_published', $announcementEvent->type);
        $this->assertSame("announcement:{$announcementId}:published", $announcementEvent->event_key);
        $this->assertSame([
            'deep_link' => "/programs/{$program->id}?tab=announcements&announcement_id={$announcementId}",
        ], json_decode($announcementEvent->payload, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame((int) $member->id, (int) $feeEvent->user_id);
        $this->assertSame('fee_record_changed', $feeEvent->type);
        $this->assertSame("fee_record:{$feeId}:v1", $feeEvent->event_key);
        $this->assertEqualsCanonicalizing([
            'deep_link' => "/programs/{$program->id}?tab=fees&fee_record_id={$feeId}",
            'period' => '2026-11',
        ], json_decode($feeEvent->payload, true, 512, JSON_THROW_ON_ERROR));

        $eventIds = [(string) $announcementEvent->id, (string) $feeEvent->id];
        DB::table('notification_outbox')->whereIn('id', $eventIds)->update([
            'available_at' => DB::raw("clock_timestamp() + INTERVAL '1 day'"),
        ]);
        $this->artisan('outbox:process', ['--limit' => 10])->assertExitCode(0)->run();
        $this->assertSame(0, DB::table('notifications')->whereIn('event_key', [
            $announcementEvent->event_key,
            $feeEvent->event_key,
        ])->count());
        $this->recordWorker('paused', $eventIds);

        DB::table('notification_outbox')->whereIn('id', $eventIds)->update([
            'available_at' => DB::raw("clock_timestamp() - INTERVAL '1 second'"),
        ]);
        $this->artisan('outbox:process', ['--limit' => 10])->assertExitCode(0)->run();
        $this->artisan('outbox:process', ['--limit' => 10])->assertExitCode(0)->run();

        $notifications = DB::table('notifications')->whereIn('event_key', [
            $announcementEvent->event_key,
            $feeEvent->event_key,
        ])->orderBy('event_key')->get();
        $this->assertCount(2, $notifications);
        $this->assertSame(1, $notifications->where('event_key', $announcementEvent->event_key)->count());
        $this->assertSame(1, $notifications->where('event_key', $feeEvent->event_key)->count());
        $announcementNotification = $notifications->firstWhere('event_key', $announcementEvent->event_key);
        $feeNotification = $notifications->firstWhere('event_key', $feeEvent->event_key);
        $this->assertSame((int) $member->id, (int) $announcementNotification->user_id);
        $this->assertSame('announcement_published', $announcementNotification->type);
        $this->assertSame(json_decode($announcementEvent->payload, true), json_decode($announcementNotification->payload, true));
        $this->assertSame((int) $member->id, (int) $feeNotification->user_id);
        $this->assertSame('fee_record_changed', $feeNotification->type);
        $this->assertSame(json_decode($feeEvent->payload, true), json_decode($feeNotification->payload, true));
        $this->assertNull(DB::table('notification_outbox')->whereIn('id', $eventIds)->whereNull('processed_at')->first());

        foreach ($eventIds as $eventId) {
            $this->assertNotNull(DB::table('notification_outbox')->where('id', $eventId)->value('processed_at'));
        }
        $this->recordWorker('resumed_and_retried', $eventIds);
    }

    public function test_fee_receipt_survives_retention_after_fee_void_and_program_completion(): void
    {
        $program = $this->program();
        $owner = $this->user('be-d04-owner@tutorlink.test');
        DB::table('fee_records')->where('program_id', $program->id)->delete();
        $path = '/api/v1/programs/'.$program->id.'/fee-records';
        $key = (string) Str::uuid();
        $payload = ['period' => '2026-11', 'amount_minor' => 410000];

        $created = $this->api($owner, 'POST', $path, $payload, $key, 'D04-17-fee-create-durable-receipt');
        $created->assertCreated();
        $feeId = (int) $created->json('data.id');
        $voidKey = (string) Str::uuid();
        $voided = $this->api($owner, 'POST', '/api/v1/fee-records/'.$feeId.'/void', [
            'expected_version' => 1,
            'reason' => 'Synthetic retention fixture.',
        ], $voidKey, 'D04-17-fee-void');
        $voided->assertOk()->assertJsonPath('data.status', 'voided');

        $receiptIds = DB::table('idempotency_receipts')
            ->where('actor_id', $owner->id)->whereIn('idempotency_key', [$key, $voidKey])->pluck('id')->all();
        $this->assertCount(2, $receiptIds);
        DB::table('idempotency_receipts')->whereIn('id', $receiptIds)->update([
            'created_at' => DB::raw("clock_timestamp() - INTERVAL '31 days'"),
        ]);
        DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->update([
            'created_at' => DB::raw("clock_timestamp() - INTERVAL '25 months'"),
        ]);
        DB::table('programs')->where('id', $program->id)->update(['status' => 'completed']);

        $receiptBefore = DB::table('idempotency_receipts')->whereIn('id', $receiptIds)->first();
        $this->assertNull($receiptBefore->expires_at);
        $this->assertSame(2, DB::table('idempotency_receipts')->whereIn('id', $receiptIds)->whereNull('expires_at')->count());
        $this->assertTrue(now('UTC')->subDays(30)->greaterThanOrEqualTo(Carbon::parse($receiptBefore->created_at)));
        $this->assertSame('voided', DB::table('fee_records')->where('id', $feeId)->value('status'));

        $operator = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'permissions' => [AdminPermissions::PURGE_NOTIFICATION_DATA, AdminPermissions::PURGE_AUDIT_LOGS],
        ]);
        config(['notification_outbox.purge_actor_id' => $operator->id]);
        $previewExitCode = Artisan::call('outbox:purge');
        $previewOutput = Artisan::output();
        $executeExitCode = Artisan::call('outbox:purge', ['--execute' => true]);
        $executeOutput = Artisan::output();
        $this->assertSame(0, $previewExitCode);
        $this->assertSame(0, $executeExitCode);
        fwrite(STDOUT, json_encode([
            'case' => 'D04-17-cleanup-command',
            'preview_exit' => $previewExitCode,
            'preview_output' => trim($previewOutput),
            'execute_exit' => $executeExitCode,
            'execute_output' => trim($executeOutput),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        $this->assertDatabaseHas('fee_records', ['id' => $feeId, 'status' => 'voided']);
        $this->assertSame(2, DB::table('idempotency_receipts')->whereIn('id', $receiptIds)->whereNull('expires_at')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->recordRetention('after_cleanup_before_replay', $program, $feeId, $receiptIds);

        $outboxCount = DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $replayed = $this->api($owner, 'POST', $path, $payload, $key, 'D04-17-fee-replay-after-31-days-terminal-purge');
        $this->assertSame(201, $replayed->getStatusCode());
        $this->assertEquals($created->json(), $replayed->json());
        $this->assertSame($outboxCount, DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->assertSame(1, DB::table('fee_records')->where('id', $feeId)->count());
        $this->recordRetention('after_replay', $program, $feeId, $receiptIds);
    }

    public function test_expired_announcement_publish_receipt_cannot_publish_twice(): void
    {
        $program = $this->program();
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $draft = $this->api($owner, 'POST', '/api/v1/programs/'.$program->id.'/announcements', [
            'title' => 'D04 expired receipt',
            'body' => 'An expired key must not publish a second time.',
        ], null, 'D04-17-announcement-draft');
        $draft->assertCreated();
        $announcementId = (int) $draft->json('data.id');
        $path = '/api/v1/announcements/'.$announcementId.'/publish';
        $key = (string) Str::uuid();
        $payload = ['expected_version' => 1];

        $published = $this->api($owner, 'POST', $path, $payload, $key, 'D04-17-announcement-publish');
        $published->assertOk()->assertJsonPath('data.version', 2);
        $receipt = DB::table('idempotency_receipts')
            ->where('actor_id', $owner->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($receipt);
        $this->assertTrue(Carbon::parse($receipt->expires_at)->equalTo(
            Carbon::parse($receipt->created_at)->addDays(30),
        ));

        DB::table('idempotency_receipts')->where('id', $receipt->id)->update([
            'created_at' => DB::raw("clock_timestamp() - INTERVAL '31 days'"),
            'expires_at' => DB::raw("clock_timestamp() - INTERVAL '1 second'"),
        ]);
        $auditCount = DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();
        $outboxCount = DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();

        $replay = $this->api($owner, 'POST', $path, $payload, $key, 'D04-17-expired-publish-key');
        $replay->assertConflict()->assertJsonPath('code', 'stale_version');
        $this->assertDatabaseHas('announcements', [
            'id' => $announcementId,
            'version' => 2,
            'published_at' => $published->json('data.published_at'),
        ]);
        $this->assertSame($auditCount, DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertSame($outboxCount, DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertDatabaseMissing('idempotency_receipts', ['id' => $receipt->id]);
        $this->recordApiState('expired_publish_attempt', $announcementId, $key);
    }

    private function deferExistingOutboxEvents(): void
    {
        DB::table('notification_outbox')->whereNull('processed_at')->update([
            'available_at' => DB::raw("clock_timestamp() + INTERVAL '1 day'"),
            'attempt_count' => 0,
            'failed_at' => null,
        ]);
    }

    private function recordWorker(string $phase, array $eventIds): void
    {
        $rows = DB::table('notification_outbox')->whereIn('id', $eventIds)->orderBy('id')->get([
            'id', 'user_id', 'type', 'event_key', 'attempt_count', 'processed_at', 'available_at',
        ]);
        $notifications = DB::table('notifications')->whereIn('event_key', $rows->pluck('event_key')->all())
            ->orderBy('event_key')->get(['user_id', 'type', 'event_key', 'payload', 'created_at']);
        fwrite(STDOUT, json_encode([
            'case' => 'D04-17-worker-'.$phase,
            'outbox' => $rows,
            'notifications' => $notifications,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function recordRetention(string $phase, Program $program, int $feeId, array $receiptIds): void
    {
        fwrite(STDOUT, json_encode([
            'case' => 'D04-17-retention-'.$phase,
            'program_status' => DB::table('programs')->where('id', $program->id)->value('status'),
            'fee' => DB::table('fee_records')->where('id', $feeId)->first(['id', 'status', 'version']),
            'receipts' => DB::table('idempotency_receipts')->whereIn('id', $receiptIds)
                ->orderBy('id')->get(['id', 'created_at', 'expires_at', 'response_status']),
            'fee_audit_count' => DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function recordApiState(string $case, int $announcementId, string $key): void
    {
        fwrite(STDOUT, json_encode([
            'case' => 'D04-17-'.$case,
            'announcement' => DB::table('announcements')->where('id', $announcementId)->first(['id', 'version', 'published_at']),
            'audit_count' => DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count(),
            'outbox_count' => DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count(),
            'receipt' => DB::table('idempotency_receipts')->where('idempotency_key', $key)->first(['created_at', 'expires_at']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function api(User $actor, string $method, string $path, array $body, ?string $key, string $case): TestResponse
    {
        $headers = $key === null ? [] : ['Idempotency-Key' => $key];
        $response = match ($method) {
            'PATCH' => $this->actingAs($actor)->patchJson($path, $body, $headers),
            default => $this->actingAs($actor)->postJson($path, $body, $headers),
        };
        fwrite(STDOUT, json_encode([
            'case' => $case,
            'actor' => $actor->role,
            'request' => ['method' => $method, 'path' => $path, 'headers' => $headers, 'body' => $body],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID')],
                'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        return $response;
    }

    private function program(): Program
    {
        return Program::query()->where('title', 'D04 Fixture Active')->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
