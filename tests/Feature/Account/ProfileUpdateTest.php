<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())->get('/account/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/account/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/account/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com');
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/account/password')
        ->put('/account/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/account/password');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct current password must be provided to update password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/account/password')
        ->put('/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/account/password');
});
