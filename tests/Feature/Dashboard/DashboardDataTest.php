<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-09-10 15:00', 'Asia/Kolkata')));
afterEach(fn () => Carbon::setTestNow());

test('dashboard props for owner include month totals and recent invoices', function () {
    $inv = Invoice::factory()->create(['invoice_date' => '2026-09-10', 'total' => 600, 'payment_mode' => 'upi', 'invoice_number' => 'WS-0100']);
    InvoiceItem::factory()->create(['invoice_id' => $inv->id, 'description' => 'Haircut – Men']);

    $this->actingAs(owner())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('today.invoices_count', 1)
            ->where('today.earnings', 600)
            ->where('today.expenses', 0)
            ->where('today.net', 600)
            ->where('today.by_mode.upi', 600)
            ->where('month.earnings', 600)
            ->has('recent_invoices', 1)
            ->where('recent_invoices.0.invoice_number', 'WS-0100')
            ->where('recent_invoices.0.items_summary', 'Haircut – Men')
            ->where('recent_invoices.0.whatsapp_sent_at', null)
        );
});

test('dashboard props for staff have month null', function () {
    $this->actingAs(staff())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('month', null)->has('today')->has('recent_invoices'));
});
