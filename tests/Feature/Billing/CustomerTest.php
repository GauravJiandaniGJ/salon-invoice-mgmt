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
