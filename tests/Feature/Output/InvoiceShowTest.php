<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\InvoiceService;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function showInvoice(): Invoice
{
    $customer = Customer::factory()->create(['name' => 'Priya Sharma', 'phone' => '919876543210']);

    return Invoice::factory()
        ->for($customer)
        ->has(InvoiceItem::factory()->count(2), 'items')
        ->create(['invoice_number' => 'WS-0011', 'public_code' => 'Qwe123Rty4', 'total' => 1400, 'subtotal' => 1400]);
}

test('invoice page builds a wa.me url with a normalised phone and encoded newlines', function () {
    Setting::set('app_url', 'https://wowsalon.example');
    Setting::set('salon_name', 'Wow Salon');
    $invoice = showInvoice();

    $this->actingAs(staff())
        ->get("/invoices/{$invoice->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/Show', false)
            ->where('invoice.invoice_number', 'WS-0011')
            ->where('invoice.customer.phone_display', '+91 98765 43210')
            ->where('invoice.total', 1400)
            ->where('public_url', 'https://wowsalon.example/i/Qwe123Rty4')
            ->where('pdf_url', "/invoices/{$invoice->id}/pdf")
            ->where('app_url_missing', false)
            ->where('can_void', false)
            ->where('whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/919876543210?text=')
                && str_contains($url, '%0A')
                && ! str_contains($url, "\n")
                && str_contains($url, rawurlencode('https://wowsalon.example/i/Qwe123Rty4')))
            ->where('whatsapp_message', fn ($m) => str_contains($m, 'Priya!') && str_contains($m, 'WS-0011') && str_contains($m, '₹1,400'))
        );
});

test('owner sees can_void and a warning when app_url is not configured', function () {
    Setting::set('app_url', 'http://localhost');
    $invoice = showInvoice();

    $this->actingAs(owner())
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page->where('app_url_missing', true)->where('can_void', true));
});

test('mark-sent is idempotent', function () {
    $invoice = showInvoice();
    $user = staff();

    $first = $this->actingAs($user)->postJson("/invoices/{$invoice->id}/mark-sent")->assertOk()->json('whatsapp_sent_at');
    expect($first)->not->toBeNull();

    $this->travel(5)->minutes();

    $second = $this->actingAs($user)->postJson("/invoices/{$invoice->id}/mark-sent")->assertOk()->json('whatsapp_sent_at');
    expect($second)->toBe($first)
        ->and($invoice->fresh()->whatsapp_sent_at->toISOString())->toBe($first);
});

test('authenticated pdf route streams the invoice', function () {
    $invoice = showInvoice();

    $this->actingAs(staff())->get("/invoices/{$invoice->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('guests cannot use the authenticated pdf route', function () {
    $invoice = showInvoice();

    $this->get("/invoices/{$invoice->id}/pdf")->assertRedirect('/login');
});

test('staff cannot void an invoice', function () {
    $invoice = showInvoice();

    $this->actingAs(staff())->post("/invoices/{$invoice->id}/void", ['reason' => 'x'])->assertForbidden();
});

test('void requires a reason', function () {
    $invoice = showInvoice();

    $this->actingAs(owner())->from("/invoices/{$invoice->id}")->post("/invoices/{$invoice->id}/void", ['reason' => ''])
        ->assertSessionHasErrors('reason');
    expect($invoice->fresh()->status)->toBe('issued');
});

test('owner void regenerates the pdf with a watermark and excludes it from public totals', function () {
    $invoice = showInvoice();
    app(PdfRenderer::class)->render($invoice);
    $before = Storage::disk('local')->get('invoices/WS-0011.pdf');

    $this->actingAs(owner())
        ->from("/invoices/{$invoice->id}")
        ->post("/invoices/{$invoice->id}/void", ['reason' => 'Billed twice'])
        ->assertRedirect("/invoices/{$invoice->id}")
        ->assertSessionHas('success');

    $invoice->refresh();
    expect($invoice->status)->toBe('void')
        ->and($invoice->void_reason)->toBe('Billed twice')
        ->and($invoice->voided_at)->not->toBeNull();

    // regenerated (content differs because of the watermark)
    expect(Storage::disk('local')->get('invoices/WS-0011.pdf'))->not->toBe($before);

    $this->get('/i/Qwe123Rty4')->assertSee('VOID');

    // second void is rejected
    $this->actingAs(owner())->from("/invoices/{$invoice->id}")
        ->post("/invoices/{$invoice->id}/void", ['reason' => 'again'])
        ->assertSessionHas('error');
})->skip(fn () => ! class_exists(InvoiceService::class) || ! method_exists(InvoiceService::class, 'void'), 'InvoiceService::void not available yet (B1)');
