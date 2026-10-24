<?php

use App\Models\ServiceListing;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('issues a one-time token and only lists metadata for its owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $created = $this->actingAs($owner, 'web')
        ->postJson('/api/v1/me/tokens', [
            'name' => 'CLI laptop',
            'abilities' => ['account:read', 'my-listings:read'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'CLI laptop')
        ->assertJsonPath('data.abilities.0', 'account:read')
        ->assertJsonStructure(['data' => ['id', 'created_at', 'expires_at'], 'token'])
        ->json();

    $plainTextToken = $created['token'];
    $tokenId = $created['data']['id'];

    expect($plainTextToken)->toContain('|');
    $this->assertEquals(
        30,
        Carbon::parse($created['data']['expires_at'])
            ->diffInDays(Carbon::parse($created['data']['created_at']), true),
    );
    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $tokenId,
        'token' => hash('sha256', explode('|', $plainTextToken, 2)[1]),
    ]);

    $listResponse = $this->actingAs($owner, 'web')
        ->getJson('/api/v1/me/tokens')
        ->assertOk()
        ->assertJsonPath('data.0.id', $tokenId)
        ->assertJsonMissingPath('data.0.token');

    expect($listResponse->getContent())->not->toContain($plainTextToken);

    $this->actingAs($other, 'web')
        ->getJson('/api/v1/me/tokens')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($other, 'web')
        ->deleteJson("/api/v1/me/tokens/{$tokenId}")
        ->assertNotFound();

    $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

    $this->actingAs($owner, 'web')
        ->deleteJson("/api/v1/me/tokens/{$tokenId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

it('validates token names abilities and expiry bounds', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->postJson('/api/v1/me/tokens', [
            'name' => str_repeat('x', 101),
            'abilities' => ['admin:*'],
            'expires_in_days' => 91,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'abilities.0', 'expires_in_days']);

    $this->actingAs($user, 'web')
        ->postJson('/api/v1/me/tokens', [
            'name' => 'duplicate abilities',
            'abilities' => ['account:read', 'account:read'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['abilities.1']);
});

it('enforces PAT abilities and rejects PAT use on session-only token management routes', function () {
    $user = User::factory()->create();
    $readOnly = $user->createToken(
        'read-only',
        ['my-listings:read'],
        now()->addDay(),
    );

    $this->withToken($readOnly->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertForbidden();

    $this->withToken($readOnly->plainTextToken)
        ->postJson('/api/v1/me/listings', [])
        ->assertForbidden();

    $this->withToken($readOnly->plainTextToken)
        ->getJson('/api/v1/me/tokens')
        ->assertUnauthorized();

    $accountReader = $user->createToken(
        'account-reader',
        ['account:read'],
        now()->addDay(),
    );

    $this->app['auth']->forgetGuards();
    $this->withToken($accountReader->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('combines listing abilities with tutor eligibility and resource ownership', function () {
    $this->seed(CatalogSeeder::class);
    $specialization = Specialization::query()->firstOrFail();

    $owner = User::factory()->create(['role' => 'tutor']);
    $ownerProfile = $owner->tutorProfile()->create([
        'headline' => 'Owner tutor',
        'bio' => 'Test profile.',
        'experience_years' => 2,
        'status' => 'approved',
    ]);

    $other = User::factory()->create(['role' => 'tutor']);
    $otherProfile = $other->tutorProfile()->create([
        'headline' => 'Other tutor',
        'bio' => 'Test profile.',
        'experience_years' => 2,
        'status' => 'approved',
    ]);

    $otherListing = ServiceListing::create([
        'tutor_profile_id' => $otherProfile->id,
        'specialization_id' => $specialization->id,
        'slug' => 'other-'.Str::lower(Str::random(8)),
        'title' => 'Other tutor listing',
        'description' => 'A listing owned by another tutor.',
        'delivery_mode' => 'online',
        'city' => 'Ho Chi Minh City',
        'price_from_minor' => 200000,
        'currency' => 'VND',
        'price_unit' => 'session',
        'status' => 'draft',
    ]);

    $token = $owner->createToken(
        'listing-reader',
        ['my-listings:read'],
        now()->addDay(),
    );

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me/listings')
        ->assertOk();

    $this->withToken($token->plainTextToken)
        ->getJson("/api/v1/me/listings/{$otherListing->id}")
        ->assertNotFound();

    $ownerProfile->update([
        'status' => 'suspended',
        'suspended_at' => now(),
    ]);

    $this->app['auth']->forgetGuards();
    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me/listings')
        ->assertForbidden();
});

it('rejects expired tokens and revokes PATs when account is suspended', function () {
    $user = User::factory()->create();
    $expired = $user->createToken(
        'expired',
        ['account:read'],
        now()->subMinute(),
    );

    $this->withToken($expired->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $target = User::factory()->create();
    $token = $target->createToken('suspension-check', ['account:read'], now()->addDay());
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/users/{$target->id}/suspend", [
            'reason' => 'PAT revocation test.',
        ])
        ->assertOk();

    $this->app['auth']->guard('web')->logout();
    $this->app['auth']->forgetGuards();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});
