<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\InvoiceService;
use App\Services\PdfRenderer;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(SettingsSeeder::class);
    $this->mock(PdfRenderer::class, fn ($m) => $m->shouldReceive('render')->andReturn('invoices/x.pdf'));
});

function editPayload(array $overrides = []): array
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

function issuedInvoice(array $overrides = []): Invoice
{
    return app(InvoiceService::class)->create(editPayload($overrides), staff());
}

test('edit page loads with prefill and editing props', function () {
    $invoice = issuedInvoice(['notes' => 'first visit', 'payment_status' => 'unpaid']);
    $invoice->forceFill(['whatsapp_sent_at' => now()])->save();

    $this->actingAs(staff())
        ->get("/invoices/{$invoice->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bills/New')
            ->where('editing.id', $invoice->id)
            ->where('editing.invoice_number', $invoice->invoice_number)
            ->whereNot('editing.whatsapp_sent_at', null)
            ->where('prefill.customer.phone', '919876543210')
            ->where('prefill.customer.name', 'Priya Sharma')
            ->where('prefill.items.0.description', 'Female Haircut')
            ->where('prefill.items.0.unit_price', 500)
            ->where('prefill.payment_mode', 'upi')
            ->where('prefill.payment_status', 'unpaid')
            ->where('prefill.notes', 'first visit')
            ->has('catalog')
        );
});

test('update replaces items, recomputes totals and keeps the number and public code', function () {
    $invoice = issuedInvoice();
    $service = Service::factory()->create(['name' => 'Beard Crafting', 'price' => 175]);
    $number = $invoice->invoice_number;
    $code = $invoice->public_code;
    $originalItemIds = $invoice->items->pluck('id')->all();

    $this->actingAs(staff())
        ->put("/invoices/{$invoice->id}", editPayload([
            'items' => [
                ['service_id' => $service->id, 'description' => '', 'unit_price' => 175, 'quantity' => 2],
                ['service_id' => null, 'description' => 'Custom', 'unit_price' => 99.5, 'quantity' => 1],
            ],
            'discount_type' => 'percent',
            'discount_value' => 10,
            'payment_mode' => 'cash',
        ]))
        ->assertRedirect("/invoices/{$invoice->id}")
        ->assertSessionHas('success', "Invoice {$number} updated.");

    $invoice->refresh()->load('items');

    expect($invoice->invoice_number)->toBe($number)
        ->and($invoice->public_code)->toBe($code)
        ->and($invoice->items)->toHaveCount(2)
        ->and($invoice->items->pluck('id')->intersect($originalItemIds))->toHaveCount(0)
        ->and($invoice->items[0]->description)->toBe('Beard Crafting')
        ->and((float) $invoice->items[0]->line_total)->toBe(350.0)
        ->and((float) $invoice->subtotal)->toBe(449.5)
        ->and((float) $invoice->discount_amount)->toBe(44.95)
        ->and((float) $invoice->total)->toBe(405.0) // 404.55 rounded
        ->and((float) $invoice->round_off)->toBe(0.45)
        ->and($invoice->payment_mode)->toBe('cash')
        ->and($invoice->status)->toBe('issued');
});

test('update adjusts the customer total_spent and resets whatsapp_sent_at', function () {
    $invoice = issuedInvoice();
    $invoice->forceFill(['whatsapp_sent_at' => now()])->save();
    $customer = $invoice->customer;
    expect((float) $customer->fresh()->total_spent)->toBe(500.0);

    $this->actingAs(staff())->put("/invoices/{$invoice->id}", editPayload([
        'items' => [['service_id' => null, 'description' => 'Shave', 'unit_price' => 150, 'quantity' => 1]],
    ]))->assertRedirect();

    expect((float) $customer->fresh()->total_spent)->toBe(150.0)
        ->and($invoice->fresh()->whatsapp_sent_at)->toBeNull();
});

test('update can move the invoice to another customer', function () {
    $invoice = issuedInvoice();
    $old = $invoice->customer;
    $other = Customer::factory()->create(['phone' => '919999900001', 'total_spent' => 1000]);

    $this->actingAs(staff())->put("/invoices/{$invoice->id}", editPayload([
        'customer' => ['phone' => '9999900001', 'name' => '', 'gender' => null],
        'items' => [['service_id' => null, 'description' => 'Shave', 'unit_price' => 200, 'quantity' => 1]],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect($invoice->fresh()->customer_id)->toBe($other->id)
        ->and((float) $old->fresh()->total_spent)->toBe(0.0)
        ->and((float) $other->fresh()->total_spent)->toBe(1200.0)
        ->and($other->fresh()->last_visit_at)->not->toBeNull();
});

test('only the owner can change the invoice date on edit', function () {
    $invoice = issuedInvoice();
    $original = $invoice->invoice_date->toDateString();

    $this->actingAs(staff())->put("/invoices/{$invoice->id}", editPayload(['invoice_date' => '2026-01-15']));
    expect($invoice->fresh()->invoice_date->toDateString())->toBe($original);

    $this->actingAs(owner())->put("/invoices/{$invoice->id}", editPayload(['invoice_date' => '2026-01-15']));
    expect($invoice->fresh()->invoice_date->toDateString())->toBe('2026-01-15');
});

test('void invoices cannot be edited', function () {
    $invoice = issuedInvoice();
    app(InvoiceService::class)->void($invoice, owner(), 'mistake');

    $this->actingAs(staff())
        ->get("/invoices/{$invoice->id}/edit")
        ->assertRedirect("/invoices/{$invoice->id}")
        ->assertSessionHas('error');

    $this->actingAs(staff())
        ->put("/invoices/{$invoice->id}", editPayload())
        ->assertRedirect("/invoices/{$invoice->id}")
        ->assertSessionHas('error');

    expect((float) $invoice->fresh()->total)->toBe(500.0);
});

test('update surfaces validation errors and leaves the invoice untouched', function () {
    $invoice = issuedInvoice();

    $this->actingAs(staff())
        ->from("/invoices/{$invoice->id}/edit")
        ->put("/invoices/{$invoice->id}", editPayload([
            'items' => [],
            'discount_type' => 'flat',
            'discount_value' => 5000,
        ]))
        ->assertRedirect("/invoices/{$invoice->id}/edit")
        ->assertSessionHasErrors(['items']);

    $invoice->refresh();
    expect((float) $invoice->total)->toBe(500.0)->and($invoice->items()->count())->toBe(1);
});

test('guests cannot edit invoices', function () {
    $invoice = issuedInvoice();

    $this->get("/invoices/{$invoice->id}/edit")->assertRedirect('/login');
    $this->put("/invoices/{$invoice->id}", editPayload())->assertRedirect('/login');
});
