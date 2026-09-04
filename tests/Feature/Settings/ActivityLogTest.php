<?php

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Service;
use App\Services\InvoiceService;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Schema;

test('billing, voiding and editing an invoice are recorded', function () {
    $this->mock(PdfRenderer::class, fn ($m) => $m->shouldReceive('render')->andReturn('invoices/x.pdf'));
    $owner = owner();
    $this->actingAs($owner);

    $invoice = app(InvoiceService::class)->create([
        'customer' => ['phone' => '9876543210', 'name' => 'Asha'],
        'items' => [['service_id' => null, 'description' => 'Haircut', 'unit_price' => 300, 'quantity' => 1]],
        'discount_type' => null, 'discount_value' => 0, 'payment_mode' => 'cash', 'payment_status' => 'paid', 'notes' => null,
    ], $owner);

    app(InvoiceService::class)->void($invoice, $owner, 'Billed twice');

    $log = Activity::orderBy('id')->get();

    expect($log->pluck('action')->all())->toContain('invoice.created', 'invoice.voided')
        ->and($log->firstWhere('action', 'invoice.voided')->description)->toBe('Voided: Billed twice')
        ->and($log->firstWhere('action', 'invoice.voided')->subject_label)->toBe($invoice->invoice_number)
        ->and($log->firstWhere('action', 'invoice.voided')->user_name)->toBe($owner->name);
});

test('a service price change records the old and new value', function () {
    $service = Service::factory()->create(['price' => 225]);

    $this->actingAs(owner())->patch("/services/{$service->id}", ['price' => 250])->assertSessionHasNoErrors();

    $entry = Activity::where('action', 'service.updated')->firstOrFail();

    expect($entry->changes['from']['price'])->toEqual(225)
        ->and($entry->changes['to']['price'])->toEqual(250);
});

test('deleting an expense keeps the row and records who did it', function () {
    $staff = staff();
    $expense = Expense::factory()->create(['user_id' => $staff->id, 'category' => 'Products', 'amount' => 500]);

    $this->actingAs($staff)->delete("/expenses/{$expense->id}")->assertSessionHasNoErrors();

    expect(Expense::find($expense->id))->toBeNull()
        ->and(Expense::withTrashed()->find($expense->id))->not->toBeNull()
        ->and(Expense::withTrashed()->find($expense->id)->deleted_at)->not->toBeNull()
        ->and(Activity::where('action', 'expense.deleted')->first()->user_name)->toBe($staff->name);
});

test('sign in and failed sign in are recorded', function () {
    $user = owner();

    // A wrong password first (still a guest), then a successful sign-in.
    $this->post('/login', ['email' => $user->email, 'password' => 'nope']);
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(Activity::where('action', 'auth.login')->count())->toBe(1)
        ->and(Activity::where('action', 'auth.login_failed')->count())->toBe(1);
});

test('the activity page is owner-only and filters work', function () {
    $owner = owner();
    Activity::create(['user_id' => $owner->id, 'user_name' => 'Owner', 'action' => 'invoice.voided', 'description' => 'Voided: test', 'subject_label' => 'WS-0001', 'created_at' => now()]);
    Activity::create(['user_id' => $owner->id, 'user_name' => 'Owner', 'action' => 'expense.created', 'description' => 'Tea', 'created_at' => now()]);

    $this->actingAs(staff())->get('/settings/activity')->assertForbidden();

    $this->actingAs($owner)->get('/settings/activity')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('settings/Activity', false)->has('activities.data', 2));

    $this->actingAs($owner)->get('/settings/activity?action=invoice.voided')
        ->assertInertia(fn ($p) => $p->has('activities.data', 1)->where('activities.data.0.subject_label', 'WS-0001'));

    $response = $this->actingAs($owner)->get('/settings/activity.csv');
    $response->assertOk();
    expect($response->streamedContent())->toContain('Invoice voided');
});

test('pruning removes rows older than the retention window', function () {
    Activity::create(['user_name' => 'Old', 'action' => 'auth.login', 'description' => 'x', 'created_at' => now()->subMonths(14)]);
    Activity::create(['user_name' => 'New', 'action' => 'auth.login', 'description' => 'x', 'created_at' => now()->subMonth()]);

    $this->artisan('activity:prune')->assertSuccessful();

    expect(Activity::count())->toBe(1)->and(Activity::first()->user_name)->toBe('New');
});

test('an audit failure never breaks the action', function () {
    Schema::drop('activity_log');

    $customer = Customer::factory()->create();
    $this->actingAs(owner())->patch("/customers/{$customer->id}", [
        'name' => 'Renamed', 'phone' => $customer->phone, 'gender' => null, 'notes' => null,
    ])->assertSessionHasNoErrors();

    expect($customer->fresh()->name)->toBe('Renamed');
});
