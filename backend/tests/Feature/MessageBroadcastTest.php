<?php

use App\Events\ConversationMessagesChanged;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, DatabaseTruncation::class);

beforeEach(function (): void {
    $this->travelTo(
        CarbonImmutable::parse('2026-10-30T08:00:00.123456Z'),
    );

    $this->customer = TestActors::customer();
    $this->tutor = ProgramApiFixtures::eligibleTutor();

    $this->chat = Fixtures::conversation(
        $this->customer,
        $this->tutor,
    );

    $this->url = '/api/v1/conversations/'
        .$this->chat.'/messages';

    Queue::fake();
});

afterEach(function (): void {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    $this->travelBack();
});

test('event uses a private channel and exposes only the conversation ID', function (): void {
    $event = new ConversationMessagesChanged($this->chat);

    expect($event)
        ->toBeInstanceOf(ShouldBroadcast::class)
        ->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->toBeInstanceOf(ShouldRescue::class);

    expect($event->queue)->toBe('broadcasts');

    expect($event->broadcastAs())
        ->toBe('conversation.messages_changed');

    expect($event->broadcastWith())->toBe([
        'conversation_id' => $this->chat,
    ]);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);

    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);

    expect($channels[0]->name)
        ->toBe('private-conversations.'.$this->chat);
});

test('new message dispatches and queues exactly once after outer commit', function (): void {
    $observed = [];

    Event::listen(
        ConversationMessagesChanged::class,
        function (ConversationMessagesChanged $event) use (&$observed): void {
            $observed[] = $event;
        },
    );

    DB::beginTransaction();

    $response = $this->actingAs($this->customer)
        ->postJson($this->url, Fixtures::payload())
        ->assertCreated();

    $this->assertDatabaseHas('messages', [
        'id' => $response->json('data.id'),
        'conversation_id' => $this->chat,
    ]);

    expect($observed)->toBe([]);

    Queue::assertNothingPushed();

    DB::commit();

    expect($observed)->toHaveCount(1);

    Queue::assertPushed(BroadcastEvent::class, 1);

    Queue::assertPushedOn(
        'broadcasts',
        BroadcastEvent::class,
        fn (BroadcastEvent $job): bool => $job->event instanceof ConversationMessagesChanged
            && $job->event->conversationId === $this->chat,
    );
});

test('outer rollback discards both message and broadcast', function (): void {
    $observed = [];

    Event::listen(
        ConversationMessagesChanged::class,
        function (ConversationMessagesChanged $event) use (&$observed): void {
            $observed[] = $event;
        },
    );

    DB::beginTransaction();

    $this->actingAs($this->customer)
        ->postJson($this->url, Fixtures::payload())
        ->assertCreated();

    expect($observed)->toBe([]);

    Queue::assertNothingPushed();

    DB::rollBack();

    expect($observed)->toBe([]);

    Queue::assertNothingPushed();

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('jobs', 0);
});

test('normalized replay does not enqueue another broadcast or reopen chat', function (): void {
    $payload = Fixtures::payload("Hello\r\nTutor");

    $first = $this->actingAs($this->customer)
        ->postJson($this->url, $payload)
        ->assertCreated();

    Queue::assertPushed(BroadcastEvent::class, 1);

    DB::table('conversations')
        ->where('id', $this->chat)
        ->update([
            'status' => 'cancelled',
            'ended_at' => now('UTC'),
        ]);

    DB::table('users')
        ->where('id', $this->tutor->id)
        ->update(['status' => 'suspended']);

    $retry = array_replace($payload, [
        'body' => "Hello\nTutor",
    ]);

    $this->postJson($this->url, $retry)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    Queue::assertPushed(BroadcastEvent::class, 1);

    $this->assertDatabaseCount('messages', 1);

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => 'cancelled',
    ]);
});

test('conflicting payload does not enqueue another broadcast', function (): void {
    $payload = Fixtures::payload('First body');

    $this->actingAs($this->customer)
        ->postJson($this->url, $payload)
        ->assertCreated();

    $this->postJson(
        $this->url,
        array_replace($payload, ['body' => 'Different body']),
    )
        ->assertConflict()
        ->assertJsonPath('code', 'client_message_id_conflict');

    Queue::assertPushed(BroadcastEvent::class, 1);

    $this->assertDatabaseCount('messages', 1);
});

test('validation failure creates neither message nor broadcast', function (array $changes): void {
    $payload = array_replace(Fixtures::payload(), $changes);

    $this->actingAs($this->customer)
        ->postJson($this->url, $payload)
        ->assertUnprocessable();

    Queue::assertNothingPushed();

    $this->assertDatabaseCount('messages', 0);
})->with([
    'blank body' => [['body' => '   ']],
    'system message' => [['message_type' => 'system']],
    'attachment attempt' => [['file_ids' => [123]]],
]);

test('quota rejection does not dispatch or enqueue a broadcast', function (): void {
    for ($index = 0; $index < 30; $index++) {
        Fixtures::seed($this->chat, $this->customer);
    }

    $this->actingAs($this->customer)
        ->postJson($this->url, Fixtures::payload())
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');

    Queue::assertNothingPushed();

    $this->assertDatabaseCount('messages', 30);
});

test('outsider send creates neither message nor broadcast', function (): void {
    $this->actingAs(TestActors::customer())
        ->postJson($this->url, Fixtures::payload())
        ->assertNotFound();

    Queue::assertNothingPushed();

    $this->assertDatabaseCount('messages', 0);
});

test('queue push failure is reported without losing the committed message', function (): void {
    Exceptions::fake();

    $failure = new RuntimeException(
        'Injected broadcast queue failure.',
    );

    $queue = Mockery::mock(QueueContract::class);

    $queue->shouldReceive('pushOn')
        ->once()
        ->with(
            'broadcasts',
            Mockery::type(BroadcastEvent::class),
        )
        ->andThrow($failure);

    Queue::shouldReceive('connection')
        ->once()
        ->with(null)
        ->andReturn($queue);

    $payload = Fixtures::payload('Stored despite queue failure');

    $response = $this->actingAs($this->customer)
        ->postJson($this->url, $payload)
        ->assertCreated();

    $messageId = $response->json('data.id');

    Exceptions::assertReported(
        fn (RuntimeException $exception): bool => $exception === $failure,
    );

    $this->assertDatabaseHas('messages', [
        'id' => $messageId,
        'body' => 'Stored despite queue failure',
    ]);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.id', $messageId);

    $this->postJson($this->url, $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $messageId);

    $this->assertDatabaseCount('messages', 1);
});

test('broadcast transport failure leaves the committed message readable', function (): void {
    $response = $this->actingAs($this->customer)
        ->postJson($this->url, Fixtures::payload())
        ->assertCreated();

    Queue::assertPushed(BroadcastEvent::class, 1);

    $job = Queue::pushed(BroadcastEvent::class)->first();

    expect($job)->toBeInstanceOf(BroadcastEvent::class);

    $driver = Mockery::mock(Broadcaster::class);

    $driver->shouldReceive('broadcast')
        ->once()
        ->andThrow(new RuntimeException('Reverb unavailable.'));

    $factory = Mockery::mock(BroadcastFactory::class);

    $factory->shouldReceive('connection')
        ->once()
        ->with(null)
        ->andReturn($driver);

    expect(fn () => $job->handle($factory))
        ->toThrow(RuntimeException::class, 'Reverb unavailable.');

    $this->assertDatabaseHas('messages', [
        'id' => $response->json('data.id'),
    ]);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.id', $response->json('data.id'));
});
