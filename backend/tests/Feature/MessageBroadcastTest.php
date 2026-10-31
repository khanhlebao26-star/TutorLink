<?php

namespace Tests\Feature;

use App\Jobs\BroadcastConversationChanged;
use App\Support\RealtimeSessionChannels;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\Support\InteractsWithRealtimeSessions;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class MessageBroadcastTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithRealtimeSessions;

    public function test_notification_is_enqueued_only_after_outer_commit(): void
    {
        [$customer, $chat] = $this->messagingFixture();

        DB::beginTransaction();

        try {
            $this->actingAs($customer)
                ->postJson($this->url($chat), Fixtures::payload())
                ->assertCreated();

            Queue::assertNothingPushed();

            DB::commit();
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        Queue::assertPushedOn(
            'broadcasts',
            BroadcastConversationChanged::class,
            fn (BroadcastConversationChanged $job): bool => $job->conversationId === $chat,
        );

        Queue::assertPushed(BroadcastConversationChanged::class, 1);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_outer_rollback_discards_message_and_notification(): void
    {
        [$customer, $chat] = $this->messagingFixture();

        DB::beginTransaction();

        try {
            $this->actingAs($customer)
                ->postJson($this->url($chat), Fixtures::payload())
                ->assertCreated();

            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_replay_and_conflict_do_not_enqueue_another_notification(): void
    {
        [$customer, $chat, $tutor] = $this->messagingFixture();

        $payload = Fixtures::payload("Hello\r\nTutor");

        $first = $this->actingAs($customer)
            ->postJson($this->url($chat), $payload)
            ->assertCreated();

        $this->postJson(
            $this->url($chat),
            array_replace($payload, ['body' => 'Different']),
        )->assertConflict();

        DB::table('conversations')->where('id', $chat)->update([
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        DB::table('users')->where('id', $tutor->id)
            ->update(['status' => 'suspended']);

        $this->postJson(
            $this->url($chat),
            array_replace($payload, ['body' => "Hello\nTutor"]),
        )->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        Queue::assertPushed(BroadcastConversationChanged::class, 1);
        $this->assertDatabaseCount('messages', 1);

        $this->assertDatabaseHas('conversations', [
            'id' => $chat,
            'status' => 'cancelled',
        ]);
    }

    public function test_validation_quota_and_outsider_failures_do_not_enqueue(): void
    {
        [$customer, $chat] = $this->messagingFixture();

        foreach ([
            ['body' => '   '],
            ['message_type' => 'system'],
            ['file_ids' => [123]],
        ] as $changes) {
            $this->actingAs($customer)
                ->postJson(
                    $this->url($chat),
                    array_replace(Fixtures::payload(), $changes),
                )->assertUnprocessable();
        }

        $this->actingAs(TestActors::customer())
            ->postJson($this->url($chat), Fixtures::payload())
            ->assertNotFound();

        for ($index = 0; $index < 30; $index++) {
            Fixtures::seed($chat, $customer);
        }

        $this->actingAs($customer)
            ->postJson($this->url($chat), Fixtures::payload())
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('messages', 30);
    }

    public function test_queue_failure_does_not_lose_the_committed_message(): void
    {
        [$customer, $chat] = $this->messagingFixture();
        Exceptions::fake();

        $failure = new RuntimeException('Injected queue failure.');

        Queue::shouldReceive('pushOn')
            ->once()
            ->with(
                'broadcasts',
                Mockery::type(BroadcastConversationChanged::class),
            )
            ->andThrow($failure);

        $payload = Fixtures::payload('Stored despite queue failure');

        $first = $this->actingAs($customer)
            ->postJson($this->url($chat), $payload)
            ->assertCreated();

        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => $exception === $failure,
        );

        $this->getJson($this->url($chat))
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->json('data.id'));

        $this->postJson($this->url($chat), $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_transport_uses_only_session_channels_and_minimal_payload(): void
    {
        [$customer, $chat] = $this->messagingFixture();
        $fixture = $this->realtimeFixture($customer);

        $expected = 'private-'.app(RealtimeSessionChannels::class)
            ->channel($chat, $fixture['token']);

        $driver = Mockery::mock(Broadcaster::class);

        $driver->shouldReceive('broadcast')
            ->once()
            ->with(
                [$expected],
                'conversation.messages_changed',
                ['conversation_id' => $chat],
            );

        Broadcast::shouldReceive('connection')->once()->andReturn($driver);

        (new BroadcastConversationChanged($chat))
            ->handle(app(RealtimeSessionChannels::class));
    }

    public function test_transport_failure_leaves_the_committed_message_readable(): void
    {
        [$customer, $chat] = $this->messagingFixture();
        $this->realtimeFixture($customer);

        $first = $this->actingAs($customer)
            ->postJson($this->url($chat), Fixtures::payload())
            ->assertCreated();

        $driver = Mockery::mock(Broadcaster::class);
        $driver->shouldReceive('broadcast')
            ->once()
            ->andThrow(new RuntimeException('Injected transport failure.'));

        Broadcast::shouldReceive('connection')->once()->andReturn($driver);

        $failure = null;

        try {
            (new BroadcastConversationChanged($chat))
                ->handle(app(RealtimeSessionChannels::class));
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        $this->assertNotNull($failure);

        $this->getJson($this->url($chat))
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->json('data.id'));

        $this->assertDatabaseCount('messages', 1);
    }

    private function messagingFixture(): array
    {
        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);

        Queue::fake();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = Fixtures::conversation($customer, $tutor);

        return [$customer, $chat, $tutor];
    }

    private function url(int $chat): string
    {
        return '/api/v1/conversations/'.$chat.'/messages';
    }
}
