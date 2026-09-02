<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:owner'])->get('/_test/owner-only', fn () => 'ok');
});

test('guests are redirected to login', function () {
    $this->get('/_test/owner-only')->assertRedirect('/login');
});

test('staff get 403 on owner-only routes', function () {
    $this->actingAs(staff())->get('/_test/owner-only')->assertForbidden();
});

test('owner can access owner-only routes', function () {
    $this->actingAs(owner())->get('/_test/owner-only')->assertOk()->assertSee('ok');
});
