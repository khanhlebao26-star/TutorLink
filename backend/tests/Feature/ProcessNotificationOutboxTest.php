<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminPermissions;
use App\Support\NotificationOutboxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresConnections;
use Tests\TestCase;

class ProcessNotificationOutboxTest extends TestCase
{
    // Keep committed fixtures visible to independent connections without reversing migrations.
    use DatabaseTruncation;

    private const TYPE = 'tutor_profile_approved';

    private const EVENT_KEY = 'profile:17:approve:123';

    private const PAYLOAD = [
        'deep_link' => '/profile',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('These tests require PostgreSQL.');
        }
    }

    public function test_committed_outbox_creates_notification_and_is_marked_processed(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        $this->runWorker();

        $this->assertDatabaseCount('notifications', 1);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => self::TYPE,
            'event_key' => self::EVENT_KEY,
            'read_at' => null,
        ]);

        $notification = DB::table('notifications')->first();

        $this->assertSame(
            self::PAYLOAD,
            json_decode(
                $notification->payload,
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertNotNull(
            DB::table('notification_outbox')
                ->where('id', $outbox->id)
                ->value('processed_at'),
        );
    }

    public function test_running_command_again_does_not_duplicate_notification(): void
    {
        $recipient = User::factory()->create();

        $this->recordOutbox($recipient);

        $this->runWorker();
        $this->runWorker();

        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_future_outbox_is_not_processed(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        DB::table('notification_outbox')
            ->where('id', $outbox->id)
            ->update([
                'available_at' => now()->addHour(),
            ]);

        $this->runWorker();

        $this->assertDatabaseCount('notifications', 0);

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'processed_at' => null,
        ]);
    }

    public function test_business_rollback_leaves_nothing_for_worker(): void
    {
        $recipient = User::factory()->create();

        try {
            DB::transaction(function () use ($recipient): void {
                $this->recordOutbox($recipient);

                throw new RuntimeException('business_rollback');
            });

            $this->fail('Expected the business transaction to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'business_rollback',
                $exception->getMessage(),
            );
        }

        $this->runWorker();

        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_existing_matching_notification_is_not_duplicated(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $recipient->id,
            'type' => self::TYPE,
            'event_key' => self::EVENT_KEY,
            'payload' => json_encode(
                self::PAYLOAD,
                JSON_THROW_ON_ERROR,
            ),
        ]);

        $this->runWorker();

        $this->assertDatabaseCount('notifications', 1);

        $this->assertNotNull(
            DB::table('notification_outbox')
                ->where('id', $outbox->id)
                ->value('processed_at'),
        );
    }

    public function test_conflicting_notification_does_not_mark_outbox_processed(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $recipient->id,
            'type' => self::TYPE,
            'event_key' => self::EVENT_KEY,
            'payload' => json_encode(
                ['deep_link' => '/different-page'],
                JSON_THROW_ON_ERROR,
            ),
        ]);

        $this->runWorker();

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'attempt_count' => 1,
            'processed_at' => null,
            'failed_at' => null,
            'last_error_code' => 'notification_key_conflict',
        ]);

        $this->assertDatabaseCount('notifications', 1);

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'processed_at' => null,
        ]);
    }

    public function test_uncommitted_outbox_is_invisible_to_another_worker(): void
    {
        $recipient = User::factory()->create();

        PostgresConnections::run(function (Connection $connectionA, Connection $connectionB) use ($recipient): void {
            $connectionA->beginTransaction();

            try {
                $this->usingConnection(
                    $connectionA,
                    fn () => $this->recordOutbox($recipient),
                );

                $this->usingConnection(
                    $connectionB,
                    fn () => $this->runWorker(),
                );

                $this->assertDatabaseCount('notifications', 0);

                $connectionA->commit();

                $this->usingConnection(
                    $connectionB,
                    fn () => $this->runWorker(),
                );

                $this->assertDatabaseCount('notifications', 1);
            } finally {
                if ($connectionA->transactionLevel() > 0) {
                    $connectionA->rollBack();
                }
            }
        });
    }

    public function test_worker_skips_row_locked_by_another_connection(): void
    {
        $recipient = User::factory()->create();

        $first = $this->recordOutbox($recipient);
        $second = $this->recordOutbox(
            $recipient,
            'profile:17:approve:124',
        );

        PostgresConnections::run(function (Connection $connectionA, Connection $connectionB) use ($first, $second): void {
            $connectionA->beginTransaction();

            try {
                $connectionA->table('notification_outbox')
                    ->where('id', $first->id)
                    ->lockForUpdate()
                    ->first();

                $this->usingConnection(
                    $connectionB,
                    fn () => $this->runWorker(),
                );

                $this->assertDatabaseCount('notifications', 1);

                $this->assertDatabaseHas('notification_outbox', [
                    'id' => $first->id,
                    'processed_at' => null,
                ]);

                $this->assertNotNull(
                    DB::table('notification_outbox')
                        ->where('id', $second->id)
                        ->value('processed_at'),
                );
            } finally {
                if ($connectionA->transactionLevel() > 0) {
                    $connectionA->rollBack();
                }
            }

            $this->usingConnection(
                $connectionB,
                fn () => $this->runWorker(),
            );

            $this->assertDatabaseCount('notifications', 2);
        });
    }

    public function test_rolled_back_processing_can_be_retried(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        PostgresConnections::run(function (Connection $connectionA, Connection $connectionB) use ($outbox): void {
            $connectionA->beginTransaction();

            try {
                $this->usingConnection(
                    $connectionA,
                    fn () => $this->runWorker(),
                );

                $this->assertSame(
                    1,
                    $connectionA->table('notifications')->count(),
                );
            } finally {
                if ($connectionA->transactionLevel() > 0) {
                    $connectionA->rollBack();
                }
            }

            $this->assertDatabaseCount('notifications', 0);

            $this->assertDatabaseHas('notification_outbox', [
                'id' => $outbox->id,
                'processed_at' => null,
            ]);

            $this->usingConnection(
                $connectionB,
                fn () => $this->runWorker(),
            );

            $this->assertDatabaseCount('notifications', 1);

            $this->assertNotNull(
                DB::table('notification_outbox')
                    ->where('id', $outbox->id)
                    ->value('processed_at'),
            );
        });
    }

    public function test_retry_window_expires_without_another_delivery_attempt(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        DB::table('notification_outbox')
            ->where('id', $outbox->id)
            ->update([
                'attempt_count' => 1,
                'retry_started_at' => DB::raw(
                    "CURRENT_TIMESTAMP - INTERVAL '61 minutes'"
                ),
                'available_at' => DB::raw(
                    "CURRENT_TIMESTAMP + INTERVAL '1 hour'"
                ),
            ]);

        $this->runWorker();

        $this->assertDatabaseCount('notifications', 0);

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'attempt_count' => 1,
            'processed_at' => null,
            'last_error_code' => 'retry_window_expired',
        ]);

        $this->assertNotNull(
            DB::table('notification_outbox')
                ->where('id', $outbox->id)
                ->value('failed_at'),
        );
    }

    public function test_failed_processing_rolls_back_notification_and_can_retry(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        DB::statement(
            'ALTER TABLE notification_outbox
         ADD CONSTRAINT k02_test_block_processed
         CHECK (processed_at IS NULL)'
        );

        try {
            $this->runWorker();
        } finally {
            DB::statement(
                'ALTER TABLE notification_outbox
             DROP CONSTRAINT k02_test_block_processed'
            );
        }

        $this->assertDatabaseCount('notifications', 0);

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'attempt_count' => 1,
            'processed_at' => null,
            'failed_at' => null,
            'last_error_code' => 'notification_write_failed',
        ]);

        $retry = DB::table('notification_outbox')
            ->where('id', $outbox->id)
            ->first();

        $this->assertTrue(
            CarbonImmutable::parse($retry->available_at)
                ->greaterThan(
                    CarbonImmutable::parse($retry->retry_started_at)
                ),
        );

        DB::table('notification_outbox')
            ->where('id', $outbox->id)
            ->update([
                'available_at' => DB::raw(
                    "CURRENT_TIMESTAMP - INTERVAL '1 second'"
                ),
            ]);

        $this->runWorker();

        $this->assertDatabaseCount('notifications', 1);

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'attempt_count' => 2,
            'last_error_code' => null,
        ]);

        $this->assertNotNull(
            DB::table('notification_outbox')
                ->where('id', $outbox->id)
                ->value('processed_at'),
        );
    }

    public function test_tenth_failure_stops_automatic_retries(): void
    {
        $outbox = $this->recordOutbox(User::factory()->create());
        $this->insertConflictingNotification($outbox);

        DB::table('notification_outbox')->where('id', $outbox->id)->update([
            'attempt_count' => 9,
            'retry_started_at' => DB::raw("clock_timestamp() - INTERVAL '5 minutes'"),
        ]);

        $this->runWorker();

        $failed = $this->freshOutbox($outbox->id);
        $this->assertSame(10, (int) $failed->attempt_count);
        $this->assertNotNull($failed->failed_at);
        $this->assertNull($failed->processed_at);
        $this->assertSame('notification_key_conflict', $failed->last_error_code);
        $this->assertDatabaseCount('notifications', 1);

        $this->runWorker();

        $this->assertSame((array) $failed, (array) $this->freshOutbox($outbox->id));
    }

    public function test_retry_backoff_is_at_most_five_minutes(): void
    {
        $outbox = $this->recordOutbox(User::factory()->create());
        $this->insertConflictingNotification($outbox);

        DB::table('notification_outbox')->where('id', $outbox->id)->update([
            'attempt_count' => 8,
            'retry_started_at' => DB::raw("clock_timestamp() - INTERVAL '5 minutes'"),
        ]);

        $before = CarbonImmutable::parse(
            DB::selectOne('SELECT clock_timestamp() AS value')->value,
        );

        $this->runWorker();

        $after = CarbonImmutable::parse(
            DB::selectOne('SELECT clock_timestamp() AS value')->value,
        );
        $retry = $this->freshOutbox($outbox->id);
        $availableAt = CarbonImmutable::parse($retry->available_at);

        $this->assertSame(9, (int) $retry->attempt_count);
        $this->assertNull($retry->failed_at);
        $this->assertNull($retry->processed_at);
        $this->assertTrue($availableAt->greaterThan($before));
        $this->assertTrue($availableAt->lessThanOrEqualTo($after->addSeconds(300)));
    }

    public function test_authorized_replay_preserves_event_and_writes_audit(): void
    {
        $outbox = $this->makeFailedOutbox();
        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');

        $this->postJson("/api/v1/admin/notification-outbox/{$outbox->id}/replay", [
            'reason' => 'Fixed the cause and requested replay.',
        ])->assertStatus(202)
            ->assertJsonPath('data.id', $outbox->id)
            ->assertJsonPath('data.status', 'replay_pending');

        $replayed = $this->freshOutbox($outbox->id);

        $this->assertSame(0, (int) $replayed->attempt_count);
        $this->assertNull($replayed->processed_at);
        $this->assertNull($replayed->resolved_at);
        $this->assertNull($replayed->last_error_code);
        $this->assertSame($outbox->failed_at, $replayed->failed_at);
        $this->assertTrue(
            CarbonImmutable::parse($replayed->replay_requested_at)
                ->greaterThan(CarbonImmutable::parse($replayed->failed_at)),
        );

        foreach (['id', 'user_id', 'type', 'event_key', 'payload', 'resource_type', 'resource_id'] as $field) {
            $this->assertSame($outbox->{$field}, $replayed->{$field});
        }

        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $operator->id,
            'action' => 'outbox.replay',
            'resource_type' => $outbox->resource_type,
            'resource_id' => $outbox->resource_id,
            'reason' => 'Fixed the cause and requested replay.',
        ]);

        $audit = DB::table('audit_logs')->where('action', 'outbox.replay')->first();
        $before = json_decode($audit->before, true, 512, JSON_THROW_ON_ERROR);
        $after = json_decode($audit->after, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($outbox->id, $before['outbox_id']);
        $this->assertSame($outbox->id, $after['outbox_id']);
        $this->assertSame(10, $before['attempt_count']);
        $this->assertSame(0, $after['attempt_count']);
        $this->assertArrayNotHasKey('payload', $before);
        $this->assertArrayNotHasKey('payload', $after);
    }

    public function test_successful_replay_resolves_failure_without_duplicate_notification(): void
    {
        $outbox = $this->makeFailedOutbox();
        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');

        $this->postJson("/api/v1/admin/notification-outbox/{$outbox->id}/replay", [
            'reason' => 'The underlying failure has been fixed.',
        ])->assertStatus(202);

        $this->runWorker();

        $processed = $this->freshOutbox($outbox->id);

        $this->assertSame(1, (int) $processed->attempt_count);
        $this->assertNotNull($processed->processed_at);
        $this->assertNotNull($processed->resolved_at);
        $this->assertSame($outbox->failed_at, $processed->failed_at);
        $this->assertSame($outbox->event_key, $processed->event_key);
        $this->assertSame($outbox->payload, $processed->payload);
        $this->assertNull($processed->last_error_code);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $outbox->user_id,
            'type' => $outbox->type,
            'event_key' => $outbox->event_key,
        ]);

        $this->runWorker();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame((array) $processed, (array) $this->freshOutbox($outbox->id));
    }

    public function test_failed_replay_requires_a_new_replay_request(): void
    {
        $outbox = $this->makeFailedOutbox();
        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');

        $this->postJson("/api/v1/admin/notification-outbox/{$outbox->id}/replay", [
            'reason' => 'Retry the failed delivery.',
        ])->assertStatus(202);

        $this->insertConflictingNotification($outbox);

        DB::table('notification_outbox')->where('id', $outbox->id)->update([
            'attempt_count' => 9,
            'retry_started_at' => DB::raw("clock_timestamp() - INTERVAL '5 minutes'"),
            'available_at' => DB::raw("clock_timestamp() - INTERVAL '1 second'"),
        ]);

        $this->runWorker();

        $failed = $this->freshOutbox($outbox->id);
        $this->assertSame(10, (int) $failed->attempt_count);
        $this->assertNull($failed->processed_at);
        $this->assertNull($failed->resolved_at);
        $this->assertTrue(
            CarbonImmutable::parse($failed->failed_at)
                ->greaterThan(CarbonImmutable::parse($failed->replay_requested_at)),
        );

        $this->runWorker();

        $this->assertSame((array) $failed, (array) $this->freshOutbox($outbox->id));
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_outbox_apis_require_explicit_permissions(): void
    {
        $outbox = $this->makeFailedOutbox();

        foreach (['customer', 'admin'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'status' => 'active',
                'permissions' => [],
            ]);
            $this->actingAs($user, 'web');

            $this->getJson('/api/v1/admin/notification-outbox?status=failed')
                ->assertForbidden();

            $this->postJson("/api/v1/admin/notification-outbox/{$outbox->id}/replay", [
                'reason' => 'Unauthorized replay attempt.',
            ])->assertForbidden();

            $this->assertSame((array) $outbox, (array) $this->freshOutbox($outbox->id));
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_replay_requires_a_nonblank_reason(): void
    {
        $outbox = $this->makeFailedOutbox();
        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');

        foreach ([[], ['reason' => '   ']] as $body) {
            $this->postJson("/api/v1/admin/notification-outbox/{$outbox->id}/replay", $body)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('reason');

            $this->assertSame((array) $outbox, (array) $this->freshOutbox($outbox->id));
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_second_pending_replay_is_rejected_without_another_audit(): void
    {
        $outbox = $this->makeFailedOutbox();
        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');
        $url = "/api/v1/admin/notification-outbox/{$outbox->id}/replay";

        $this->postJson($url, ['reason' => 'First replay request.'])
            ->assertStatus(202);

        $pending = $this->freshOutbox($outbox->id);

        $this->postJson($url, ['reason' => 'Repeated replay request.'])
            ->assertStatus(409);

        $this->assertSame((array) $pending, (array) $this->freshOutbox($outbox->id));
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_processed_or_resolved_outbox_cannot_be_replayed(): void
    {
        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');

        foreach (['processed_at', 'resolved_at'] as $column) {
            $outbox = $this->makeFailedOutbox();

            DB::table('notification_outbox')->where('id', $outbox->id)->update([
                $column => DB::raw('clock_timestamp()'),
            ]);

            $before = $this->freshOutbox($outbox->id);

            $this->postJson("/api/v1/admin/notification-outbox/{$outbox->id}/replay", [
                'reason' => 'Try to replay a completed event.',
            ])->assertStatus(409);

            $this->assertSame((array) $before, (array) $this->freshOutbox($outbox->id));
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_replay_state(): void
    {
        $outbox = $this->makeFailedOutbox();
        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');
        $this->withoutExceptionHandling();

        DB::statement(
            "ALTER TABLE audit_logs ADD CONSTRAINT k02_test_block_replay_audit
             CHECK (action <> 'outbox.replay')"
        );

        try {
            try {
                $this->postJson("/api/v1/admin/notification-outbox/{$outbox->id}/replay", [
                    'reason' => 'Replay must fail if its audit cannot be written.',
                ]);

                $this->fail('Expected the audit constraint to reject the insert.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0] ?? null);
            }
        } finally {
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT k02_test_block_replay_audit');
        }

        $this->assertSame((array) $outbox, (array) $this->freshOutbox($outbox->id));
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_listing_classifies_outbox_states_and_excludes_payload(): void
    {
        $pending = $this->recordOutbox(User::factory()->create());
        $failed = $this->makeFailedOutbox();
        $replaying = $this->makeFailedOutbox();
        $completed = $this->recordOutbox(User::factory()->create());
        $resolved = $this->makeFailedOutbox();

        DB::table('notification_outbox')->where('id', $completed->id)->update([
            'processed_at' => DB::raw('clock_timestamp()'),
        ]);
        DB::table('notification_outbox')->where('id', $resolved->id)->update([
            'resolved_at' => DB::raw('clock_timestamp()'),
        ]);

        $operator = $this->makeOutboxOperator();
        $this->actingAs($operator, 'web');

        $this->postJson("/api/v1/admin/notification-outbox/{$replaying->id}/replay", [
            'reason' => 'Queue a replay for listing verification.',
        ])->assertStatus(202);

        $pendingRows = $this->getJson('/api/v1/admin/notification-outbox?status=pending')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $failedRows = $this->getJson('/api/v1/admin/notification-outbox?status=failed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data');

        $this->assertEqualsCanonicalizing(
            [$pending->id, $replaying->id],
            array_column($pendingRows, 'id'),
        );
        $this->assertSame([$failed->id], array_column($failedRows, 'id'));

        foreach (array_merge($pendingRows, $failedRows) as $row) {
            $this->assertArrayNotHasKey('payload', $row);
        }
    }

    public function test_killed_worker_before_commit_can_be_retried(): void
    {
        $outbox = $this->recordOutbox(User::factory()->create());

        // This child runs the real command. Its listener pauses immediately
        // after notification INSERT, while the transaction is still open.
        $script = <<<'PHP'
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Tests\Support\TestDatabaseGuard::assertSafe($app['config']);

$connection = \Illuminate\Support\Facades\DB::connection();

if (
    $connection->getDriverName() !== 'pgsql'
    || $connection->getDatabaseName() !== $argv[1]
) {
    exit(2);
}

\Illuminate\Support\Facades\DB::listen(
    function (\Illuminate\Database\Events\QueryExecuted $query): void {
        $sql = strtolower(ltrim($query->sql));

        if (str_starts_with($sql, 'insert into "notifications"')) {
            fwrite(STDOUT, "K02_INSERTED_BEFORE_COMMIT\n");
            fflush(STDOUT);

            while (true) {
                usleep(100000);
            }
        }
    }
);

exit($kernel->call('outbox:process', ['--limit' => 1]));
PHP;

        $process = new Process(
            [
                PHP_BINARY,
                '-r',
                $script,
                DB::connection()->getDatabaseName(),
            ],
            base_path(),
            ['APP_ENV' => 'testing'],
        );

        $process->setTimeout(15);

        try {
            $process->start();

            $deadline = microtime(true) + 10;

            while (
                $process->isRunning()
                && ! str_contains(
                    $process->getOutput(),
                    'K02_INSERTED_BEFORE_COMMIT',
                )
                && microtime(true) < $deadline
            ) {
                usleep(50000);
            }

            $this->assertTrue(
                str_contains(
                    $process->getOutput(),
                    'K02_INSERTED_BEFORE_COMMIT',
                ),
                'Worker did not reach the point before commit.',
            );

            $this->assertTrue($process->isRunning());

            // The parent connection cannot see the uncommitted INSERT.
            $this->assertDatabaseCount('notifications', 0);

            $this->assertDatabaseHas('notification_outbox', [
                'id' => $outbox->id,
                'processed_at' => null,
            ]);

            // Linux signal 9: terminate without graceful transaction cleanup.
            $process->stop(0, 9);

            $this->assertFalse($process->isRunning());

            // Wait for PostgreSQL to release the dead connection's row lock.
            DB::transaction(function () use ($outbox): void {
                DB::statement("SET LOCAL lock_timeout = '5s'");

                $row = DB::table('notification_outbox')
                    ->where('id', $outbox->id)
                    ->lockForUpdate()
                    ->first();

                $this->assertNotNull($row);
                $this->assertNull($row->processed_at);
                $this->assertSame(0, (int) $row->attempt_count);
            });

            $this->assertDatabaseCount('notifications', 0);

            // A new worker must recover the same event.
            $this->runWorker();

            $processed = $this->freshOutbox($outbox->id);

            $this->assertNotNull($processed->processed_at);
            $this->assertNull($processed->failed_at);
            $this->assertSame($outbox->event_key, $processed->event_key);

            $this->assertDatabaseCount('notifications', 1);

            $this->assertDatabaseHas('notifications', [
                'user_id' => $outbox->user_id,
                'type' => $outbox->type,
                'event_key' => $outbox->event_key,
            ]);

            // Another pass must not create a duplicate.
            $this->runWorker();

            $this->assertDatabaseCount('notifications', 1);
        } finally {
            if ($process->isRunning()) {
                $process->stop(0, 9);
            }
        }
    }

    private function makeOutboxOperator(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'permissions' => [
                AdminPermissions::VIEW_NOTIFICATION_OUTBOX,
                AdminPermissions::REPLAY_NOTIFICATION_OUTBOX,
            ],
        ]);
    }

    private function makeFailedOutbox(): object
    {
        $outbox = $this->recordOutbox(User::factory()->create());

        DB::table('notification_outbox')->where('id', $outbox->id)->update([
            'attempt_count' => 10,
            'retry_started_at' => DB::raw("clock_timestamp() - INTERVAL '5 minutes'"),
            'failed_at' => DB::raw("clock_timestamp() - INTERVAL '1 minute'"),
            'last_error_code' => 'notification_write_failed',
        ]);

        return $this->freshOutbox($outbox->id);
    }

    private function insertConflictingNotification(object $outbox): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $outbox->user_id,
            'type' => $outbox->type,
            'event_key' => $outbox->event_key,
            'payload' => json_encode(
                ['deep_link' => '/different-page'],
                JSON_THROW_ON_ERROR,
            ),
        ]);
    }

    private function freshOutbox(string $id): object
    {
        return DB::table('notification_outbox')->where('id', $id)->first();
    }

    private function recordOutbox(
        User $recipient,
        string $eventKey = self::EVENT_KEY,
    ): object {
        DB::transaction(function () use ($recipient, $eventKey): void {
            app(NotificationOutboxWriter::class)->record(
                self::TYPE,
                'tutor_profile',
                17,
                $eventKey,
                [$recipient->id],
                self::PAYLOAD,
            );

            // timestamp(0) can round a new row into the next second; make ready fixtures explicit.
            DB::table('notification_outbox')
                ->where('user_id', $recipient->id)
                ->where('type', self::TYPE)
                ->where('event_key', $eventKey)
                ->update([
                    'available_at' => DB::raw("CURRENT_TIMESTAMP - INTERVAL '1 second'"),
                ]);
        });

        return DB::table('notification_outbox')
            ->where('user_id', $recipient->id)
            ->where('type', self::TYPE)
            ->where('event_key', $eventKey)
            ->first();
    }

    private function runWorker(): void
    {
        $this->artisan('outbox:process', ['--limit' => 10])
            ->assertExitCode(0)
            ->run();
    }

    private function usingConnection(
        Connection $connection,
        callable $callback,
    ): mixed {
        $originalConnection = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection($connection->getName());

            return $callback();
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
