<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Storage;

function outputInvoice(array $attrs = []): Invoice
{
    $customer = Customer::factory()->create(['name' => 'Priya Sharma', 'phone' => '919876543210']);

    return Invoice::factory()
        ->for($customer)
        ->has(InvoiceItem::factory()->count(2), 'items')
        ->create(array_merge(['invoice_number' => 'WS-0007', 'public_code' => 'AbC123xYz9', 'total' => 1400, 'subtotal' => 1400], $attrs));
}

beforeEach(fn () => Storage::fake('local'));

test('public page renders for a valid code and masks the phone', function () {
    Setting::set('salon_name', 'Wow Salon');
    $invoice = outputInvoice();

    $this->get('/i/AbC123xYz9')
        ->assertOk()
        ->assertSee('WS-0007')
        ->assertSee('Priya')
        ->assertSee('98XXXX3210')
        ->assertDontSee('919876543210')
        ->assertDontSee('98765 43210')
        ->assertSee('₹1,400')
        ->assertSee('Download PDF')
        ->assertSee('noindex', false)
        ->assertSee('og:title', false)
        ->assertSee('Wow Salon – Invoice WS-0007 – ₹1,400', false)
        ->assertSee($invoice->items->first()->description);
});

test('public page 404s for an unknown or malformed code', function () {
    outputInvoice();

    $this->get('/i/ZZZZZZZZZZ')->assertNotFound()->assertSee('This invoice link is not valid');
    $this->get('/i/short')->assertNotFound();
    $this->get('/i/ZZZZZZZZZZ/pdf')->assertNotFound();
});

test('public pdf downloads as an attachment and regenerates a missing file', function () {
    Setting::set('salon_name', 'Wow Salon');
    $invoice = outputInvoice();

    $response = $this->get('/i/AbC123xYz9/pdf');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="WowSalon-WS-0007.pdf"');

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
    Storage::disk('local')->assertExists('invoices/WS-0007.pdf');
    expect($invoice->fresh()->pdf_path)->toBe('invoices/WS-0007.pdf');
});

test('void invoices show a VOID banner on the public page', function () {
    outputInvoice(['status' => Invoice::STATUS_VOID, 'void_reason' => 'Wrong customer', 'voided_at' => now()]);

    $this->get('/i/AbC123xYz9')
        ->assertOk()
        ->assertSee('VOID')
        ->assertSee('Wrong customer');
});

test('public routes need no authentication and are throttled', function () {
    outputInvoice();

    $this->get('/i/AbC123xYz9')->assertOk()->assertHeader('X-RateLimit-Limit', '60');
});

test('public page and pdf carry the technology partner footer and an og image', function () {
    Setting::set('footer_text', 'Powered by TodoIT');
    $customer = Customer::factory()->create(['phone' => '919876543210']);
    $invoice = Invoice::factory()->for($customer)->has(InvoiceItem::factory()->count(1), 'items')->create(['public_code' => 'Footer0001']);

    $this->get('/i/Footer0001')
        ->assertOk()
        ->assertSee('href="https://todoitservices.com"', false)
        ->assertSee('Powered by TodoIT')
        ->assertSee('todoitservices.com')
        ->assertSee('property="og:site_name"', false)
        ->assertSee('brand/wow-logo');

    $pdf = app(PdfRenderer::class);
    expect(view('pdf.invoice', ['invoice' => $invoice->fresh(['customer', 'items']), 'salon' => $pdf::salonDetails()])->render())
        ->toContain('Powered by TodoIT')->toContain('todoitservices.com');
});
