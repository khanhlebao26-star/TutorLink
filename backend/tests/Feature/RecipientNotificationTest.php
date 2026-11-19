<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
it('scopes notifications, hides raw payload and preserves the first read timestamp on replay', function (): void {
    $owner = TestActors::customer();
    $outside = TestActors::customer();
    $id = (string) Str::uuid();
    DB::table('notifications')->insert(['id' => $id, 'user_id' => $owner->id, 'type' => 'task_assigned', 'payload' => json_encode(['deep_link' => 'https://evil.example', 'private' => 'SECRET PAYLOAD']), 'event_key' => 'test:'.$id, 'created_at' => now()]);
    $this->actingAs($outside)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
    $this->patchJson("/api/v1/notifications/{$id}/read")->assertNotFound();
    $this->actingAs($owner)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.deep_link', null)->assertDontSee('SECRET PAYLOAD');
    $first = $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk();
    $stamp = DB::table('notifications')->where('id', $id)->value('read_at');
    $this->travel(2)->minutes();
    $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk();
    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->toBe($stamp);
    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 0);
});
it('requires an active session to list notifications', function (): void {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});
