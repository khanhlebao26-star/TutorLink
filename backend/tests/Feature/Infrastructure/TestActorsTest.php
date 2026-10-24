<?php

use App\Support\AdminPermissions;
use Tests\Support\TestActors;

it('authenticates a basic user fixture', function () {
    $user = TestActors::user();

    $this->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->actingAs($user, 'web')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('provides a customer profile without tutor access', function () {
    $customer = TestActors::customer();

    $this->assertDatabaseHas('customer_profiles', [
        'user_id' => $customer->id,
    ]);

    $this->actingAs($customer, 'web')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonPath(
            'data.profile.id',
            $customer->customerProfile->id,
        );

    $this->getJson('/api/v1/tutor/profile')
        ->assertForbidden();
});

it('provides a tutor draft accessible to its owner', function () {
    $tutor = TestActors::tutor();

    $this->actingAs($tutor, 'web')
        ->getJson('/api/v1/tutor/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $tutor->tutorProfile->id)
        ->assertJsonPath('data.status', 'draft');

    expect($tutor->tutorProfile->public_slug)
        ->toBeString()
        ->not->toBeEmpty();
});

it('requires explicit permissions for an admin fixture', function () {
    $admin = TestActors::admin([]);

    $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/tutor-profiles')
        ->assertForbidden();

    $admin->update([
        'permissions' => [AdminPermissions::VIEW_TUTOR_PROFILES],
    ]);

    $this->actingAs($admin->fresh(), 'web')
        ->getJson('/api/v1/admin/tutor-profiles')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
