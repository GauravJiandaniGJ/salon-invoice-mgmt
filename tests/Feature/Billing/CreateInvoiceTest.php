<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffMember;
use App\Services\InvoiceService;
use App\Services\PdfRenderer;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]); // Vue pages are delivered by the frontend agent
    $this->seed(SettingsSeeder::class);
    $this->mock(PdfRenderer::class, fn ($m) => $m->shouldReceive('render')->andReturn('invoices/x.pdf'));
});

function billPayload(array $overrides = []): array
{
    $items = $overrides['items'] ?? null;
    unset($overrides['items']);

    $payload = array_replace_recursive([
        'customer' => ['phone' => '98765 43210', 'name' => 'Priya Sharma', 'gender' => 'female'],
        'staff_member_id' => null,
        'invoice_date' => null,
        'items' => [
            ['service_id' => null, 'description' => 'Female Haircut', 'unit_price' => 500, 'quantity' => 1],
        ],
        'discount_type' => null,
        'discount_value' => 0,
        'payment_mode' => 'upi',
        'payment_status' => 'paid',
        'notes' => '',
    ], $overrides);

    if ($items !== null) {
        $payload['items'] = $items;
    }

    return $payload;
}

test('new bill page loads with catalog, staff and defaults', function () {
    $category = ServiceCategory::factory()->create(['audience' => 'men']);
    Service::factory()->create(['service_category_id' => $category->id, 'name' => 'Shave', 'price' => 150]);
    Service::factory()->create(['service_category_id' => $category->id, 'name' => 'Hidden', 'is_active' => false]);
    StaffMember::factory()->create(['name' => 'Asha']);
    StaffMember::factory()->create(['name' => 'Gone', 'is_active' => false]);

    $this->actingAs(staff())
        ->get('/bills/new')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bills/New')
            ->has('catalog', 1)
            ->has('catalog.0.services', 1)
            ->where('catalog.0.services.0.display_name', 'Shave')
            ->has('staff_members', 1)
            ->where('tax_rate', 0)
            ->where('can_edit_date', false)
            ->where('prefill', null)
            ->where('today', now()->toDateString())
        );
});

test('billing a new customer creates the customer and the invoice', function () {
    $user = staff();

    $response = $this->actingAs($user)->post('/invoices', billPayload([
        'items' => [
            ['service_id' => null, 'description' => 'Female Haircut', 'unit_price' => 500, 'quantity' => 1],
            ['service_id' => null, 'description' => 'Eyebrows', 'unit_price' => 60, 'quantity' => 1],
        ],
    ]));

    $invoice = Invoice::with('items')->firstOrFail();
    $response->assertRedirect("/invoices/{$invoice->id}")->assertSessionHas('success');

    $customer = Customer::where('phone', '919876543210')->firstOrFail();
    expect($customer->name)->toBe('Priya Sharma')
        ->and($customer->gender)->toBe('female')
        ->and((float) $customer->total_spent)->toBe(560.0)
        ->and($customer->last_visit_at)->not->toBeNull();

    expect($invoice->invoice_number)->toBe('WS-0001')
        ->and(strlen($invoice->public_code))->toBe(10)
        ->and($invoice->customer_id)->toBe($customer->id)
        ->and($invoice->user_id)->toBe($user->id)
        ->and($invoice->invoice_date->toDateString())->toBe(now()->toDateString())
        ->and((float) $invoice->subtotal)->toBe(560.0)
        ->and((float) $invoice->total)->toBe(560.0)
        ->and($invoice->payment_mode)->toBe('upi')
        ->and($invoice->status)->toBe('issued')
        ->and($invoice->items)->toHaveCount(2)
        ->and($invoice->items[1]->description)->toBe('Eyebrows');
});

test('an existing phone reuses the customer and keeps their name', function () {
    $existing = Customer::factory()->create(['phone' => '919876543210', 'name' => 'Priya S', 'gender' => null]);

    $this->actingAs(staff())->post('/invoices', billPayload(['customer' => ['name' => 'Someone Else', 'gender' => 'female']]))
        ->assertRedirect();

    expect(Customer::count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Priya S')
        ->and($existing->fresh()->gender)->toBe('female')
        ->and(Invoice::first()->customer_id)->toBe($existing->id);
});

test('name is optional for an existing customer but required for a new one', function () {
    Customer::factory()->create(['phone' => '919876543210']);

    $this->actingAs(staff())->post('/invoices', billPayload(['customer' => ['name' => '']]))->assertSessionHasNoErrors();

    $this->actingAs(staff())
        ->post('/invoices', billPayload(['customer' => ['phone' => '9123456789', 'name' => '']]))
        ->assertSessionHasErrors(['customer.name']);
});

test('line items are snapshots and survive later price edits', function () {
    $service = Service::factory()->create(['group_name' => 'Hair Wash', 'name' => 'Upto Shoulder', 'price' => 200]);

    $this->actingAs(staff())->post('/invoices', billPayload([
        'items' => [['service_id' => $service->id, 'description' => '', 'unit_price' => 200, 'quantity' => 2]],
    ]))->assertSessionHasNoErrors();

    $service->update(['price' => 999, 'name' => 'Renamed']);

    $item = Invoice::first()->items->first();
    expect($item->service_id)->toBe($service->id)
        ->and($item->description)->toBe('Hair Wash – Upto Shoulder')
        ->and((float) $item->unit_price)->toBe(200.0)
        ->and((float) $item->quantity)->toBe(2.0)
        ->and((float) $item->line_total)->toBe(400.0)
        ->and($service->fresh()->isBilled())->toBeTrue();
});

test('invoice numbers are sequential', function () {
    $user = staff();

    foreach (['9876543210', '9876543211', '9876543212'] as $phone) {
        $this->actingAs($user)->post('/invoices', billPayload(['customer' => ['phone' => $phone]]));
    }

    expect(Invoice::orderBy('id')->pluck('invoice_number')->all())->toBe(['WS-0001', 'WS-0002', 'WS-0003']);
});

test('totals with percent discount are stored to the paisa', function () {
    $this->actingAs(staff())->post('/invoices', billPayload([
        'items' => [['service_id' => null, 'description' => 'Keratin', 'unit_price' => 1235, 'quantity' => 1]],
        'discount_type' => 'percent',
        'discount_value' => 10,
    ]))->assertSessionHasNoErrors();

    $invoice = Invoice::first();
    expect((float) $invoice->subtotal)->toBe(1235.0)
        ->and((float) $invoice->discount_amount)->toBe(123.5)
        ->and((float) $invoice->round_off)->toBe(0.5)
        ->and((float) $invoice->total)->toBe(1112.0);
});

test('validation errors', function () {
    $user = staff();

    $this->actingAs($user)->post('/invoices', billPayload(['customer' => ['phone' => '12345']]))
        ->assertSessionHasErrors(['customer.phone']);

    $this->actingAs($user)->post('/invoices', billPayload(['items' => []]))
        ->assertSessionHasErrors(['items']);

    $this->actingAs($user)->post('/invoices', billPayload(['discount_type' => 'flat', 'discount_value' => 600]))
        ->assertSessionHasErrors(['discount_value']);

    $this->actingAs($user)->post('/invoices', billPayload(['discount_type' => 'percent', 'discount_value' => 150]))
        ->assertSessionHasErrors(['discount_value']);

    $this->actingAs($user)->post('/invoices', billPayload(['items' => [['description' => 'X', 'unit_price' => -1, 'quantity' => 0]]]))
        ->assertSessionHasErrors(['items.0.unit_price', 'items.0.quantity']);

    $this->actingAs($user)->post('/invoices', billPayload(['payment_mode' => 'crypto']))
        ->assertSessionHasErrors(['payment_mode']);

    expect(Invoice::count())->toBe(0)->and(Customer::count())->toBe(0);
});

test('only the owner can set the invoice date', function () {
    $this->actingAs(staff())->post('/invoices', billPayload(['invoice_date' => '2026-01-15']));
    expect(Invoice::first()->invoice_date->toDateString())->toBe(now()->toDateString());

    $this->actingAs(owner())->post('/invoices', billPayload(['customer' => ['phone' => '9123456789'], 'invoice_date' => '2026-01-15']));
    expect(Invoice::latest('id')->first()->invoice_date->toDateString())->toBe('2026-01-15');
});

test('duplicate and customer prefill', function () {
    $user = staff();
    $this->actingAs($user)->post('/invoices', billPayload(['discount_type' => 'flat', 'discount_value' => 50, 'notes' => 'vip']));
    $invoice = Invoice::first();

    $this->actingAs($user)->get("/bills/new?duplicate={$invoice->id}")
        ->assertInertia(fn ($page) => $page
            ->where('prefill.customer.phone', '919876543210')
            ->where('prefill.customer.name', 'Priya Sharma')
            ->where('prefill.items.0.description', 'Female Haircut')
            ->where('prefill.items.0.unit_price', 500)
            ->where('prefill.discount_type', 'flat')
            ->where('prefill.discount_value', 50)
            ->where('prefill.payment_mode', 'upi')
            ->where('prefill.notes', 'vip')
        );

    $this->actingAs($user)->get("/bills/new?customer_id={$invoice->customer_id}")
        ->assertInertia(fn ($page) => $page
            ->where('prefill.customer.name', 'Priya Sharma')
            ->where('prefill.items', [])
        );
});

test('voiding an invoice reverses customer spend and regenerates the pdf', function () {
    $this->actingAs(staff())->post('/invoices', billPayload());
    $invoice = Invoice::first();
    $owner = owner();

    app(InvoiceService::class)->void($invoice, $owner, 'Wrong customer');

    $invoice->refresh();
    expect($invoice->status)->toBe('void')
        ->and($invoice->void_reason)->toBe('Wrong customer')
        ->and($invoice->voided_by)->toBe($owner->id)
        ->and($invoice->voided_at)->not->toBeNull()
        ->and((float) $invoice->customer->total_spent)->toBe(0.0);
});

test('guests cannot bill', function () {
    $this->get('/bills/new')->assertRedirect('/login');
    $this->post('/invoices', billPayload())->assertRedirect('/login');
});
