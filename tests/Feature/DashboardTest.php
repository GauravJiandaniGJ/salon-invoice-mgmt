<?php

use App\Models\User;
use Database\Seeders\SettingsSeeder;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
});

test('the root url redirects to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

test('shared props include the user role and salon name', function () {
    $this->seed(SettingsSeeder::class);

    $this->actingAs(owner())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.role', 'owner')
            ->where('salon.name', 'Wow Salon')
        );
});
