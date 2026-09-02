<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]); // Vue pages are delivered by the frontend agent
});

test('invoices index defaults to this month and paginates', function () {
    $customer = Customer::factory()->create(['name' => 'Priya Sharma']);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'total' => 560]);
    InvoiceItem::factory()->count(3)->sequence(['description' => 'A'], ['description' => 'B'], ['description' => 'C'])->create(['invoice_id' => $invoice->id]);
    Invoice::factory()->create(['invoice_date' => now()->subMonths(2)->toDateString()]);

    $this->actingAs(staff())->get('/invoices')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/Index')
            ->where('filters.from', now()->startOfMonth()->toDateString())
            ->where('filters.to', now()->endOfMonth()->toDateString())
            ->where('filters.status', '')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.invoice_number', $invoice->invoice_number)
            ->where('invoices.data.0.customer.name', 'Priya Sharma')
            ->where('invoices.data.0.items_summary', 'A, B +1')
            ->where('invoices.data.0.total', 560)
            ->where('invoices.total', 1)
        );
});

test('invoices index filters by status, mode, sent and search', function () {
    $today = now()->toDateString();
    $c1 = Customer::factory()->create(['name' => 'Priya', 'phone' => '919876543210']);
    $c2 = Customer::factory()->create(['name' => 'Rahul', 'phone' => '919123456789']);
    Invoice::factory()->create(['customer_id' => $c1->id, 'invoice_date' => $today, 'payment_mode' => 'cash', 'whatsapp_sent_at' => now(), 'invoice_number' => 'WS-0001']);
    Invoice::factory()->void()->create(['customer_id' => $c2->id, 'invoice_date' => $today, 'payment_mode' => 'upi', 'invoice_number' => 'WS-0002']);

    $as = fn () => $this->actingAs(staff());

    $as()->get('/invoices?status=void')->assertInertia(fn ($p) => $p->has('invoices.data', 1)->where('invoices.data.0.invoice_number', 'WS-0002'));
    $as()->get('/invoices?payment_mode=cash')->assertInertia(fn ($p) => $p->has('invoices.data', 1)->where('invoices.data.0.invoice_number', 'WS-0001'));
    $as()->get('/invoices?sent=sent')->assertInertia(fn ($p) => $p->has('invoices.data', 1)->where('invoices.data.0.invoice_number', 'WS-0001'));
    $as()->get('/invoices?sent=unsent')->assertInertia(fn ($p) => $p->has('invoices.data', 1)->where('invoices.data.0.invoice_number', 'WS-0002'));
    $as()->get('/invoices?q=rahul')->assertInertia(fn ($p) => $p->has('invoices.data', 1)->where('invoices.data.0.invoice_number', 'WS-0002'));
    $as()->get('/invoices?q=98765')->assertInertia(fn ($p) => $p->has('invoices.data', 1)->where('invoices.data.0.invoice_number', 'WS-0001'));
    $as()->get('/invoices?q=WS-0002')->assertInertia(fn ($p) => $p->has('invoices.data', 1));
    $as()->get('/invoices?from=2020-01-01&to=2020-01-31')->assertInertia(fn ($p) => $p->has('invoices.data', 0));
});

test('owner can export csv, staff cannot', function () {
    $customer = Customer::factory()->create(['name' => 'Priya Sharma', 'phone' => '919876543210']);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'total' => 560, 'invoice_number' => 'WS-0001']);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => 'Haircut']);

    $this->actingAs(staff())->get('/invoices/export.csv')->assertForbidden();

    $response = $this->actingAs(owner())->get('/invoices/export.csv');
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Invoice,Date,Customer,Phone,Items,Subtotal,Discount,Tax,Total,Payment,Status,Sent')
        ->toContain('WS-0001')
        ->toContain('Priya Sharma')
        ->toContain('Haircut');
});
