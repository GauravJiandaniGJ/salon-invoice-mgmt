<?php

use App\Models\Expense;
use Carbon\CarbonImmutable;

test('guests are redirected', function () {
    $this->get('/expenses')->assertRedirect('/login');
});

test('expenses page lists the month with totals and categories', function () {
    $user = staff();
    Expense::factory()->create(['expense_date' => '2026-09-03', 'amount' => 100, 'payment_mode' => 'cash', 'category' => 'Rent', 'user_id' => $user->id]);
    Expense::factory()->create(['expense_date' => '2026-09-04', 'amount' => 50, 'payment_mode' => 'upi', 'category' => 'Custom Thing', 'user_id' => owner()->id]);
    Expense::factory()->create(['expense_date' => '2026-08-31', 'amount' => 999, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/expenses?month=2026-09')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('expenses/Index', false)
            ->where('month', '2026-09')
            ->has('expenses', 2)
            ->where('expenses.0.expense_date', '2026-09-04')
            ->where('expenses.0.can_edit', false)
            ->where('expenses.1.can_edit', true)
            ->where('totals.total', 150)
            ->where('totals.by_mode.cash', 100)
            ->where('totals.by_mode.upi', 50)
            ->where('totals.by_mode.card', 0)
            ->where('categories', fn ($c) => collect($c)->contains('Custom Thing') && collect($c)->contains('Products'))
            ->where('payment_modes', ['cash', 'upi', 'card', 'other'])
        );
});

test('invalid month falls back to the current month', function () {
    $this->actingAs(staff())
        ->get('/expenses?month=nope')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('month', CarbonImmutable::today()->format('Y-m')));
});

test('staff can add an expense', function () {
    $user = staff();

    $this->actingAs($user)
        ->from('/expenses')
        ->post('/expenses', [
            'expense_date' => '2026-09-02',
            'category' => 'Tea/Snacks',
            'description' => 'Chai for team',
            'amount' => 120,
            'payment_mode' => 'cash',
        ])
        ->assertRedirect('/expenses')
        ->assertSessionHas('success');

    $this->assertDatabaseHas('expenses', ['description' => 'Chai for team', 'amount' => 120, 'user_id' => $user->id]);
});

test('expense validation rejects bad input', function () {
    $this->actingAs(staff())
        ->from('/expenses')
        ->post('/expenses', ['expense_date' => '02-09-2026', 'category' => '', 'description' => '', 'amount' => 0, 'payment_mode' => 'cheque'])
        ->assertSessionHasErrors(['expense_date', 'category', 'description', 'amount', 'payment_mode']);
});

test('staff can edit and delete only their own expenses', function () {
    $me = staff();
    $mine = Expense::factory()->create(['user_id' => $me->id, 'amount' => 100]);
    $theirs = Expense::factory()->create(['user_id' => staff()->id, 'amount' => 100]);

    $payload = ['expense_date' => '2026-09-02', 'category' => 'Misc', 'description' => 'Edited', 'amount' => 250, 'payment_mode' => 'upi'];

    $this->actingAs($me)->patch("/expenses/{$mine->id}", $payload)->assertRedirect();
    expect((float) $mine->fresh()->amount)->toBe(250.0);

    $this->actingAs($me)->patch("/expenses/{$theirs->id}", $payload)->assertForbidden();
    $this->actingAs($me)->delete("/expenses/{$theirs->id}")->assertForbidden();
    expect(Expense::find($theirs->id))->not->toBeNull();

    $this->actingAs($me)->delete("/expenses/{$mine->id}")->assertRedirect();
    expect(Expense::find($mine->id))->toBeNull();
});

test('owner can edit and delete anyone\'s expense', function () {
    $theirs = Expense::factory()->create(['user_id' => staff()->id]);
    $payload = ['expense_date' => '2026-09-02', 'category' => 'Misc', 'description' => 'Owner edit', 'amount' => 10, 'payment_mode' => 'cash'];

    $this->actingAs(owner())->patch("/expenses/{$theirs->id}", $payload)->assertRedirect();
    expect($theirs->fresh()->description)->toBe('Owner edit');

    $this->actingAs(owner())->delete("/expenses/{$theirs->id}")->assertRedirect();
    expect(Expense::find($theirs->id))->toBeNull();
});
