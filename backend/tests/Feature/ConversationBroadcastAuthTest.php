<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-reverb-key',
            'secret' => 'test-reverb-secret',
            'app_id' => 'test-reverb-app',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
            'client_options' => [],
        ],
    ]);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');

    $this->customer = TestActors::customer();
    $this->tutor = ProgramApiFixtures::eligibleTutor();

    $this->chat = Fixtures::conversation(
        $this->customer,
        $this->tutor,
    );

    $this->authPayload = [
        'socket_id' => '123.456',
        'channel_name' => 'private-conversations.'.$this->chat,
    ];
});

test('broadcast authorization requires an authenticated session', function (): void {
    $this->postJson('/broadcasting/auth', $this->authPayload)
        ->assertUnauthorized();
});

test('either active member receives the exact private channel signature', function (string $role): void {
    $actor = $role === 'customer'
        ? $this->customer
        : $this->tutor;

    $signature = hash_hmac(
        'sha256',
        $this->authPayload['socket_id']
        .':'.$this->authPayload['channel_name'],
        'test-reverb-secret',
    );

    $this->actingAs($actor)
        ->postJson('/broadcasting/auth', $this->authPayload)
        ->assertOk()
        ->assertExactJson([
            'auth' => 'test-reverb-key:'.$signature,
        ]);
})->with(['customer', 'tutor']);

test('outsider and admin cannot authorize a conversation channel', function (string $role): void {
    $actor = $role === 'admin'
        ? TestActors::admin()
        : TestActors::customer();

    $this->actingAs($actor)
        ->postJson('/broadcasting/auth', $this->authPayload)
        ->assertForbidden();
})->with(['customer', 'admin']);

test('a missing conversation cannot be authorized', function (): void {
    $payload = array_replace($this->authPayload, [
        'channel_name' => 'private-conversations.'.PHP_INT_MAX,
    ]);

    $this->actingAs($this->customer)
        ->postJson('/broadcasting/auth', $payload)
        ->assertForbidden();
});

test('current database account state overrides a stale authenticated actor', function (string $change): void {
    $this->actingAs($this->customer);

    $changes = match ($change) {
        'suspended' => ['status' => 'suspended'],
        'deleted' => ['deleted_at' => now('UTC')],
        'admin' => ['role' => 'admin'],
    };

    DB::table('users')
        ->where('id', $this->customer->id)
        ->update($changes);

    $this->postJson('/broadcasting/auth', $this->authPayload)
        ->assertForbidden();
})->with(['suspended', 'deleted', 'admin']);

test('channel authorization checks membership again on every request', function (): void {
    $this->actingAs($this->customer)
        ->postJson('/broadcasting/auth', $this->authPayload)
        ->assertOk();

    DB::table('conversation_members')
        ->where('conversation_id', $this->chat)
        ->where('user_id', $this->customer->id)
        ->delete();

    $this->postJson('/broadcasting/auth', $this->authPayload)
        ->assertForbidden();
});

test('an active member may subscribe to cancelled chat with suspended peer', function (): void {
    DB::table('conversations')
        ->where('id', $this->chat)
        ->update([
            'status' => 'cancelled',
            'ended_at' => now('UTC'),
        ]);

    DB::table('users')
        ->where('id', $this->tutor->id)
        ->update(['status' => 'suspended']);

    $this->actingAs($this->customer)
        ->postJson('/broadcasting/auth', $this->authPayload)
        ->assertOk();

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => 'cancelled',
    ]);
});

test('invalid socket or channel input is rejected', function (string $field, mixed $value): void {
    $payload = array_replace($this->authPayload, [
        $field => $value,
    ]);

    $this->actingAs($this->customer)
        ->postJson('/broadcasting/auth', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'null socket' => ['socket_id', null],
    'malformed socket' => ['socket_id', 'invalid-socket'],
    'array socket' => ['socket_id', ['123.456']],
    'null channel' => ['channel_name', null],
    'public channel' => ['channel_name', 'conversations.1'],
    'another channel namespace' => [
        'channel_name',
        'private-users.1',
    ],
    'zero conversation ID' => [
        'channel_name',
        'private-conversations.0',
    ],
    'negative conversation ID' => [
        'channel_name',
        'private-conversations.-1',
    ],
]);

test('missing authorization fields are rejected', function (): void {
    $this->actingAs($this->customer)
        ->postJson('/broadcasting/auth', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'socket_id',
            'channel_name',
        ]);
});
