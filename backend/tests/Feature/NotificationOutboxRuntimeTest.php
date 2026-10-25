<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminPermissions;
use App\Support\NotificationOutboxAlerts;
use App\Support\NotificationOutboxWriter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class NotificationOutboxRuntimeTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Runtime integration requires PostgreSQL.');
        }

        Http::preventStrayRequests();
        config(['notification_outbox.purge_actor_id' => 0]);
    }

    public function test_worker_command_processes_committed_events_without_duplicate(): void
    {
        $this->event();

        $this->artisan('outbox:work', ['--once' => true])->assertExitCode(0)->run();
        $this->artisan('outbox:work', ['--once' => true])->assertExitCode(0)->run();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_failure_queues_one_alert_and_monitor_does_not_duplicate_it(): void
    {
        $outbox = $this->conflictingEvent();

        $this->artisan('outbox:process')->assertExitCode(0)->run();
        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->artisan('outbox:monitor')->assertExitCode(0)->run();

        $this->assertDatabaseHas('notification_outbox', ['id' => $outbox->id, 'attempt_count' => 10]);
        $this->assertNotNull(DB::table('notification_outbox')->where('id', $outbox->id)->value('failed_at'));
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('outbox_failed', $this->queuedAlert()->kind);
        Http::assertNothingSent();
    }

    public function test_failed_handoff_rolls_back_failure_marker_and_job_together(): void
    {
        $outbox = $this->conflictingEvent();
        DB::statement("ALTER TABLE jobs ADD CONSTRAINT k02_test_block_alert CHECK (queue <> 'slack')");

        try {
            $this->artisan('outbox:process')->assertExitCode(1)->run();
        } finally {
            DB::statement('ALTER TABLE jobs DROP CONSTRAINT k02_test_block_alert');
        }

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id, 'attempt_count' => 9, 'failed_at' => null, 'processed_at' => null,
        ]);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('cache', 0);

        $this->artisan('outbox:process')->assertExitCode(0)->run();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('cache', 1);
    }

    public function test_monitor_backfills_legacy_failures_and_throttles_reminders(): void
    {
        $outbox = $this->event();
        DB::table('notification_outbox')->where('id', $outbox->id)->update([
            'attempt_count' => 10,
            'failed_at' => DB::raw("clock_timestamp() - INTERVAL '1 minute'"),
            'last_error_code' => 'notification_write_failed',
        ]);

        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->assertDatabaseCount('jobs', 1);

        $this->travel(25)->hours();
        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->assertDatabaseCount('jobs', 2);
        Http::assertNothingSent();
    }

    public function test_pending_alerts_are_aggregated_and_limited_to_once_per_ten_minutes(): void
    {
        foreach (range(1, 2) as $unused) {
            $outbox = $this->event();
            DB::table('notification_outbox')->where('id', $outbox->id)->update([
                'created_at' => DB::raw("clock_timestamp() - INTERVAL '6 minutes'"),
            ]);
        }
        $this->event();

        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->assertDatabaseCount('jobs', 1);
        $alert = $this->queuedAlert();
        $this->assertSame('outbox_pending', $alert->kind);
        $this->assertSame(2, $alert->context['pending_count']);
        $this->assertGreaterThan(300, $alert->context['oldest_pending_seconds']);

        $this->travel(11)->minutes();
        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->assertDatabaseCount('jobs', 2);
        Http::assertNothingSent();
    }

    public function test_pending_alert_handoff_rollback_does_not_consume_throttle_slot(): void
    {
        $outbox = $this->event();
        DB::table('notification_outbox')->where('id', $outbox->id)->update([
            'created_at' => DB::raw("clock_timestamp() - INTERVAL '6 minutes'"),
        ]);

        try {
            DB::transaction(function (): void {
                $this->assertTrue(app(NotificationOutboxAlerts::class)->pending(DB::connection()));

                throw new RuntimeException('rollback_alert');
            });
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback_alert', $exception->getMessage());
        }

        $this->assertDatabaseCount('cache', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->artisan('outbox:monitor')->assertExitCode(0)->run();
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_queue_on_an_independent_connection_is_rejected(): void
    {
        $outbox = $this->conflictingEvent();
        config([
            'database.connections.k02_other_queue' => config('database.connections.pgsql'),
            'queue.connections.database.connection' => 'k02_other_queue',
        ]);

        try {
            $this->artisan('outbox:process')->assertExitCode(1)->run();
        } finally {
            DB::purge('k02_other_queue');
        }

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id, 'attempt_count' => 9, 'failed_at' => null,
        ]);
        $this->assertDatabaseCount('cache', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_retention_preview_does_not_delete_any_data(): void
    {
        $this->retentionFixtures();

        $this->artisan('outbox:purge')->assertExitCode(0)->run();

        $this->assertDatabaseCount('notification_outbox', 6);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_executing_retention_requires_both_permissions(): void
    {
        $this->retentionFixtures();
        $this->artisan('outbox:purge', ['--execute' => true])->assertExitCode(1)->run();

        $operator = User::factory()->create([
            'role' => 'admin', 'status' => 'active',
            'permissions' => [AdminPermissions::PURGE_NOTIFICATION_DATA],
        ]);
        config(['notification_outbox.purge_actor_id' => $operator->id]);
        $this->artisan('outbox:purge', ['--execute' => true])->assertExitCode(1)->run();

        $this->assertDatabaseCount('notification_outbox', 6);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_retention_preserves_unresolved_failures_and_audits_each_batch(): void
    {
        $fixtures = $this->retentionFixtures();
        $operator = $this->purgeOperator();

        $this->artisan('outbox:purge', ['--execute' => true, '--limit' => 1])
            ->assertExitCode(0)->run();

        foreach ($fixtures['delete_outbox'] as $id) {
            $this->assertDatabaseMissing('notification_outbox', ['id' => $id]);
        }
        foreach ($fixtures['keep_outbox'] as $id) {
            $this->assertDatabaseHas('notification_outbox', ['id' => $id]);
        }
        $this->assertDatabaseMissing('notifications', ['id' => $fixtures['old_notification']]);
        $this->assertDatabaseHas('notifications', ['id' => $fixtures['recent_notification']]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $fixtures['old_audit']]);
        $this->assertDatabaseHas('audit_logs', ['id' => $fixtures['recent_audit']]);
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'outbox.purge')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'audit.purge')->count());
        $this->assertSame(4, DB::table('audit_logs')->where('actor_id', $operator->id)->count());
    }

    public function test_failed_retention_audit_rolls_back_deletion(): void
    {
        $fixtures = $this->retentionFixtures();
        $this->purgeOperator();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT k02_test_block_purge_audit CHECK (action NOT IN ('outbox.purge', 'audit.purge'))");

        try {
            $this->artisan('outbox:purge', ['--execute' => true])->assertExitCode(1)->run();
        } finally {
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT k02_test_block_purge_audit');
        }

        foreach ($fixtures['delete_outbox'] as $id) {
            $this->assertDatabaseHas('notification_outbox', ['id' => $id]);
        }
        $this->assertDatabaseCount('notification_outbox', 6);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_scheduler_has_monitor_and_controlled_retention_tasks(): void
    {
        $this->artisan('schedule:list')->assertExitCode(0)->run();
        $events = collect(app(Schedule::class)->events());
        $monitor = $events->first(fn ($event) => str_contains($event->command ?? '', 'outbox:monitor'));
        $purge = $events->first(fn ($event) => str_contains($event->command ?? '', 'outbox:purge'));

        $this->assertNotNull($monitor);
        $this->assertNotNull($purge);
        $this->assertSame('* * * * *', $monitor->expression);
        $this->assertSame('0 2 * * *', $purge->expression);
        $this->assertSame('UTC', $purge->timezone);
        $this->assertTrue($monitor->withoutOverlapping);
        $this->assertTrue($purge->withoutOverlapping);
    }

    private function event(): object
    {
        $recipient = User::factory()->create();
        DB::transaction(fn () => app(NotificationOutboxWriter::class)->record(
            'tutor_profile_approved', 'tutor_profile', 17,
            'profile:17:approve:runtime-test', [$recipient->id], ['deep_link' => '/profile'],
        ));
        DB::table('notification_outbox')->where('user_id', $recipient->id)->update([
            'available_at' => DB::raw("clock_timestamp() - INTERVAL '1 second'"),
        ]);

        return DB::table('notification_outbox')->where('user_id', $recipient->id)->first();
    }

    private function conflictingEvent(): object
    {
        $outbox = $this->event();
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $outbox->user_id,
            'type' => $outbox->type, 'event_key' => $outbox->event_key,
            'payload' => json_encode(['deep_link' => '/different-page'], JSON_THROW_ON_ERROR),
        ]);
        DB::table('notification_outbox')->where('id', $outbox->id)->update([
            'attempt_count' => 9,
            'retry_started_at' => DB::raw("clock_timestamp() - INTERVAL '5 minutes'"),
        ]);

        return $outbox;
    }

    private function queuedAlert(): object
    {
        $payload = json_decode(DB::table('jobs')->first()->payload, true, 512, JSON_THROW_ON_ERROR);

        return unserialize($payload['data']['command']);
    }

    private function purgeOperator(): User
    {
        $operator = User::factory()->create([
            'role' => 'admin', 'status' => 'active',
            'permissions' => [AdminPermissions::PURGE_NOTIFICATION_DATA, AdminPermissions::PURGE_AUDIT_LOGS],
        ]);
        config(['notification_outbox.purge_actor_id' => $operator->id]);

        return $operator;
    }

    private function retentionFixtures(): array
    {
        $delete = [];
        $keep = [];
        foreach ([
            ['processed_at' => "clock_timestamp() - INTERVAL '31 days'"],
            ['failed_at' => "clock_timestamp() - INTERVAL '100 days'", 'resolved_at' => "clock_timestamp() - INTERVAL '91 days'"],
            ['processed_at' => "clock_timestamp() - INTERVAL '29 days'"],
            ['failed_at' => "clock_timestamp() - INTERVAL '100 days'", 'resolved_at' => "clock_timestamp() - INTERVAL '89 days'"],
            ['failed_at' => "clock_timestamp() - INTERVAL '200 days'"],
            [],
        ] as $index => $timestamps) {
            $outbox = $this->event();
            if ($timestamps !== []) {
                DB::table('notification_outbox')->where('id', $outbox->id)
                    ->update(array_map(fn ($expression) => DB::raw($expression), $timestamps));
            }
            if ($index < 2) {
                $delete[] = $outbox->id;
            } else {
                $keep[] = $outbox->id;
            }
        }

        $notificationIds = [];
        foreach ([181, 179] as $days) {
            $id = (string) Str::uuid();
            DB::table('notifications')->insert([
                'id' => $id, 'user_id' => $outbox->user_id,
                'type' => $outbox->type, 'event_key' => 'retention:'.$days,
                'payload' => $outbox->payload,
                'created_at' => DB::raw("clock_timestamp() - INTERVAL '{$days} days'"),
            ]);
            $notificationIds[] = $id;
        }

        $auditIds = [];
        foreach ([25, 23] as $months) {
            $auditIds[] = DB::table('audit_logs')->insertGetId([
                'action' => 'tutor_profile_approved',
                'resource_type' => 'tutor_profile', 'resource_id' => 17,
                'created_at' => DB::raw("clock_timestamp() - INTERVAL '{$months} months'"),
            ]);
        }

        return [
            'delete_outbox' => $delete, 'keep_outbox' => $keep,
            'old_notification' => $notificationIds[0], 'recent_notification' => $notificationIds[1],
            'old_audit' => $auditIds[0], 'recent_audit' => $auditIds[1],
        ];
    }
}
