<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NotificationOutboxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class NotificationOutboxWriterTest extends TestCase
{
    use RefreshDatabase;

    private const TYPE = 'listing_moderation_restored';

    private const EVENT_KEY = 'listing:17:restore:123';

    private const PAYLOAD = [
        'deep_link' => '/me/listings/17',
        'result_status' => 'active',
    ];

    public function test_commit_records_once_per_recipient_without_creating_notifications(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        DB::transaction(fn () => $this->record([$first->id, $second->id, $first->id]));

        $this->assertDatabaseCount('notification_outbox', 2);
        $this->assertDatabaseHas('notification_outbox', [
            'user_id' => $first->id,
            'type' => self::TYPE,
            'resource_type' => 'listing',
            'resource_id' => 17,
            'event_key' => self::EVENT_KEY,
        ]);
        $this->assertDatabaseHas('notification_outbox', [
            'user_id' => $second->id,
            'type' => self::TYPE,
            'event_key' => self::EVENT_KEY,
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_rollback_removes_the_outbox_event(): void
    {
        $recipient = User::factory()->create();

        try {
            DB::transaction(function () use ($recipient): void {
                $this->record([$recipient->id]);
                throw new RuntimeException('rollback');
            });
            $this->fail('Expected the transaction to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_replay_does_not_create_a_second_outbox_event(): void
    {
        $recipient = User::factory()->create();

        DB::transaction(fn () => $this->record([$recipient->id]));
        DB::transaction(fn () => $this->record([$recipient->id]));

        $this->assertDatabaseCount('notification_outbox', 1);
    }

    public function test_reusing_an_event_key_with_different_payload_is_rejected(): void
    {
        $recipient = User::factory()->create();
        DB::transaction(fn () => $this->record([$recipient->id]));

        try {
            DB::transaction(fn () => $this->record(
                [$recipient->id],
                ['deep_link' => '/me/listings/17', 'result_status' => 'inactive'],
            ));
            $this->fail('Expected a conflicting replay to be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Event key was reused with different data.', $exception->getMessage());
        }

        $this->assertDatabaseCount('notification_outbox', 1);
        $saved = DB::table('notification_outbox')->first();
        $this->assertSame('active', json_decode($saved->payload, true, 512, JSON_THROW_ON_ERROR)['result_status']);
    }

    public function test_payload_outside_the_allow_list_is_rejected(): void
    {
        $recipient = User::factory()->create();

        try {
            DB::transaction(fn () => $this->record(
                [$recipient->id],
                [...self::PAYLOAD, 'body' => 'private content'],
            ));
            $this->fail('Expected an unsupported payload field to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unsupported notification payload field.', $exception->getMessage());
        }

        $this->assertDatabaseCount('notification_outbox', 0);
    }

    public function test_accepted_invitation_requires_the_program_deep_link_to_match_its_id(): void
    {
        try {
            DB::transaction(fn () => app(NotificationOutboxWriter::class)->record(
                'invitation_accepted',
                'program_invitation',
                17,
                'invitation:17:accepted',
                [],
                ['deep_link' => '/programs/17', 'program_id' => 18],
            ));
            $this->fail('Expected a mismatched Program deep link to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid accepted Program deep link.', $exception->getMessage());
        }

        $this->assertDatabaseCount('notification_outbox', 0);
    }

    private function record(array $recipientIds, array $payload = self::PAYLOAD): void
    {
        app(NotificationOutboxWriter::class)->record(
            self::TYPE,
            'listing',
            17,
            self::EVENT_KEY,
            $recipientIds,
            $payload,
        );
    }
}
