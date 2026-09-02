<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('staff cannot manage users', function () {
    $this->actingAs(staff())
        ->post('/settings/users', ['name' => 'A', 'email' => 'a@x.io', 'role' => 'staff', 'password' => 'password123'])
        ->assertForbidden();
});

test('owner can add a user', function () {
    $this->actingAs(owner())
        ->post('/settings/users', ['name' => 'Reception 2', 'email' => 'r2@wowsalon.local', 'role' => 'staff', 'password' => 'password123'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'r2@wowsalon.local')->firstOrFail();
    expect($user->role)->toBe('staff')
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});

test('user creation validates email uniqueness, role and password length', function () {
    $existing = staff();

    $this->actingAs(owner())
        ->post('/settings/users', ['name' => '', 'email' => $existing->email, 'role' => 'admin', 'password' => 'short'])
        ->assertSessionHasErrors(['name', 'email', 'role', 'password']);
});

test('owner can deactivate, promote and reset password of another user', function () {
    $owner = owner();
    $user = staff();

    $this->actingAs($owner)->patch("/settings/users/{$user->id}", ['is_active' => false])->assertSessionHas('success');
    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)->patch("/settings/users/{$user->id}", ['role' => 'owner', 'is_active' => true]);
    expect($user->fresh()->role)->toBe('owner')->and($user->fresh()->is_active)->toBeTrue();

    $this->actingAs($owner)->patch("/settings/users/{$user->id}", ['password' => 'brand-new-pass'])->assertSessionHas('success', 'Password reset.');
    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();

    // empty password is ignored, not applied
    $this->actingAs($owner)->patch("/settings/users/{$user->id}", ['name' => 'Renamed', 'password' => '']);
    expect($user->fresh()->name)->toBe('Renamed')
        ->and(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

test('owner cannot deactivate or demote themselves', function () {
    $owner = owner();

    $this->actingAs($owner)->patch("/settings/users/{$owner->id}", ['is_active' => false])->assertSessionHas('error');
    expect($owner->fresh()->is_active)->toBeTrue();

    $this->actingAs($owner)->patch("/settings/users/{$owner->id}", ['role' => 'staff'])->assertSessionHas('error');
    expect($owner->fresh()->role)->toBe('owner');
});

test('the last active owner cannot be demoted or deactivated by another owner', function () {
    $me = owner();
    $other = User::factory()->owner()->create();

    // two owners: demoting the other is fine
    $this->actingAs($me)->patch("/settings/users/{$other->id}", ['role' => 'staff'])->assertSessionHas('success');
    expect($other->fresh()->role)->toBe('staff');

    // now $me is the last owner; a second owner account is created inactive and tries to demote $me
    $inactiveOwner = User::factory()->owner()->inactive()->create();
    $this->actingAs($inactiveOwner);
    $this->patch("/settings/users/{$me->id}", ['role' => 'staff'])->assertSessionHas('error', 'At least one active owner is required.');
    expect($me->fresh()->role)->toBe('owner');
});
