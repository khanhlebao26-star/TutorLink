<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NotificationOutboxWriter;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
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

        try {
            $this->runWorker();

            $this->fail('Expected a notification conflict.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Notification key conflicts with existing data.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('notifications', 1);

        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'processed_at' => null,
        ]);
    }

    public function test_uncommitted_outbox_is_invisible_to_another_worker(): void
    {
        $recipient = User::factory()->create();

        PostgresConnections::run(function (Connection $connectionA, Connection $connectionB, ) use ($recipient): void {
            $connectionA->beginTransaction();

            try {
                $this->usingConnection(
                    $connectionA,
                    fn() => $this->recordOutbox($recipient),
                );

                $this->usingConnection(
                    $connectionB,
                    fn() => $this->runWorker(),
                );

                $this->assertDatabaseCount('notifications', 0);

                $connectionA->commit();

                $this->usingConnection(
                    $connectionB,
                    fn() => $this->runWorker(),
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

        PostgresConnections::run(function (Connection $connectionA, Connection $connectionB, ) use ($first, $second): void {
            $connectionA->beginTransaction();

            try {
                $connectionA->table('notification_outbox')
                    ->where('id', $first->id)
                    ->lockForUpdate()
                    ->first();

                $this->usingConnection(
                    $connectionB,
                    fn() => $this->runWorker(),
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
                fn() => $this->runWorker(),
            );

            $this->assertDatabaseCount('notifications', 2);
        });
    }

    public function test_rolled_back_processing_can_be_retried(): void
    {
        $recipient = User::factory()->create();
        $outbox = $this->recordOutbox($recipient);

        PostgresConnections::run(function (Connection $connectionA, Connection $connectionB, ) use ($outbox): void {
            $connectionA->beginTransaction();

            try {
                $this->usingConnection(
                    $connectionA,
                    fn() => $this->runWorker(),
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
                fn() => $this->runWorker(),
            );

            $this->assertDatabaseCount('notifications', 1);

            $this->assertNotNull(
                DB::table('notification_outbox')
                    ->where('id', $outbox->id)
                    ->value('processed_at'),
            );
        });
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
