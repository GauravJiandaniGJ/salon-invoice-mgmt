<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]); // Vue pages are delivered by the frontend agent
});

test('lookup finds an existing customer with last invoice', function () {
    $customer = Customer::factory()->create(['phone' => '919876543210', 'name' => 'Priya Sharma', 'total_spent' => 1400]);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'total' => 1400, 'invoice_date' => '2026-08-12']);

    $this->actingAs(staff())
        ->getJson('/customers/lookup?phone=98765-43210')
        ->assertOk()
        ->assertJson([
            'found' => true,
            'normalised_phone' => '919876543210',
            'error' => null,
            'customer' => [
                'id' => $customer->id,
                'name' => 'Priya Sharma',
                'phone_display' => '+91 98765 43210',
                'total_spent' => 1400,
                'visits' => 1,
                'last_invoice' => ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'total' => 1400, 'invoice_date' => '2026-08-12'],
            ],
        ]);
});

test('lookup reports not found and invalid numbers', function () {
    $this->actingAs(staff())
        ->getJson('/customers/lookup?phone=9876543210')
        ->assertOk()
        ->assertJson(['found' => false, 'customer' => null, 'normalised_phone' => '919876543210', 'error' => null]);

    $this->actingAs(staff())
        ->getJson('/customers/lookup?phone=123')
        ->assertOk()
        ->assertJson(['found' => false, 'customer' => null, 'normalised_phone' => null])
        ->assertJsonPath('error', fn ($e) => is_string($e) && $e !== '');
});

test('lookup suggests customers by name or phone fragment', function () {
    $priya = Customer::factory()->create(['name' => 'Priya Sharma', 'phone' => '919876543210', 'last_visit_at' => now()->subDay()]);
    $prince = Customer::factory()->create(['name' => 'Prince Kumar', 'phone' => '919123456789', 'last_visit_at' => now()]);
    Customer::factory()->create(['name' => 'Rahul Verma', 'phone' => '919555512345']);

    // name match, case-insensitive, most recent visit first
    $this->actingAs(staff())
        ->getJson('/customers/lookup?q=pri')
        ->assertOk()
        ->assertJson(['found' => false, 'customer' => null, 'normalised_phone' => null, 'error' => null])
        ->assertJsonCount(2, 'matches')
        ->assertJsonPath('matches.0.id', $prince->id)
        ->assertJsonPath('matches.1.id', $priya->id)
        ->assertJsonPath('matches.1.phone_display', '+91 98765 43210')
        ->assertJsonPath('matches.1.visits', 0);

    // phone fragment match (digits only, formatting ignored)
    $this->actingAs(staff())
        ->getJson('/customers/lookup?q=555-12')
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.name', 'Rahul Verma');

    // too short → no matches
    $this->actingAs(staff())->getJson('/customers/lookup?q=p')->assertOk()->assertJsonCount(0, 'matches');
});

test('lookup suggestions are capped at eight', function () {
    Customer::factory()->count(12)->sequence(fn ($seq) => ['name' => 'Anita '.$seq->index])->create();

    $this->actingAs(staff())->getJson('/customers/lookup?q=anita')->assertOk()->assertJsonCount(8, 'matches');
});

test('customers index searches by name or phone', function () {
    Customer::factory()->create(['name' => 'Priya Sharma', 'phone' => '919876543210']);
    Customer::factory()->create(['name' => 'Rahul Verma', 'phone' => '919123456789']);

    $this->actingAs(staff())->get('/customers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('customers/Index')->has('customers.data', 2));

    $this->actingAs(staff())->get('/customers?q=rahul')
        ->assertInertia(fn ($page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Rahul Verma')->where('filters.q', 'rahul'));

    $this->actingAs(staff())->get('/customers?q=98765')
        ->assertInertia(fn ($page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Priya Sharma'));
});

test('customer detail shows profile and invoice history', function () {
    $customer = Customer::factory()->create(['notes' => 'Allergic to ammonia']);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'total' => 700]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => 'Shave']);

    $this->actingAs(staff())->get("/customers/{$customer->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/Show')
            ->where('customer.id', $customer->id)
            ->where('customer.notes', 'Allergic to ammonia')
            ->where('customer.visits', 1)
            ->has('invoices.data', 1)
            ->where('invoices.data.0.items_summary', 'Shave')
            ->where('invoices.data.0.total', 700)
        );
});

test('customer can be updated with a normalised unique phone', function () {
    $customer = Customer::factory()->create(['phone' => '919876543210']);
    Customer::factory()->create(['phone' => '919123456789']);

    $this->actingAs(staff())
        ->patch("/customers/{$customer->id}", ['name' => 'Priya S', 'phone' => '09876543211', 'gender' => 'female', 'notes' => 'VIP'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $customer->refresh();
    expect($customer->name)->toBe('Priya S')->and($customer->phone)->toBe('919876543211')->and($customer->notes)->toBe('VIP');

    $this->actingAs(staff())
        ->patch("/customers/{$customer->id}", ['name' => 'Priya S', 'phone' => '9123456789'])
        ->assertSessionHasErrors(['phone']);

    $this->actingAs(staff())
        ->patch("/customers/{$customer->id}", ['name' => 'Priya S', 'phone' => '123'])
        ->assertSessionHasErrors(['phone']);
});

test('a customer can be added from the customers page', function () {
    $this->actingAs(staff())
        ->post('/customers', ['name' => 'Asha Patel', 'phone' => '98765 43210', 'gender' => 'female', 'notes' => null])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $customer = Customer::where('phone', '919876543210')->first();
    expect($customer)->not->toBeNull()->and($customer->name)->toBe('Asha Patel');
});

test('adding a customer with an existing phone is rejected', function () {
    Customer::factory()->create(['phone' => '919876543210']);

    $this->actingAs(staff())
        ->from('/customers')
        ->post('/customers', ['name' => 'Dup', 'phone' => '9876543210'])
        ->assertSessionHasErrors('phone');

    expect(Customer::where('phone', '919876543210')->count())->toBe(1);
});
