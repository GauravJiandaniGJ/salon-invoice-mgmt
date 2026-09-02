<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('render stores the pdf and records pdf_path', function () {
    $invoice = Invoice::factory()->has(InvoiceItem::factory()->count(3), 'items')->create(['invoice_number' => 'WS-0042']);

    $path = app(PdfRenderer::class)->render($invoice);

    expect($path)->toBe('invoices/WS-0042.pdf');
    Storage::disk('local')->assertExists($path);
    expect($invoice->fresh()->pdf_path)->toBe($path)
        ->and(substr(Storage::disk('local')->get($path), 0, 4))->toBe('%PDF');
});

test('void invoices are rendered with a watermark', function () {
    $invoice = Invoice::factory()->void('Duplicate')->has(InvoiceItem::factory(), 'items')->create();

    $html = view('pdf.invoice', ['invoice' => $invoice->load(['customer', 'items', 'staffMember']), 'salon' => PdfRenderer::salonDetails()])->render();

    expect($html)->toContain('class="watermark"')->toContain('VOID')->toContain('Duplicate');

    $issued = Invoice::factory()->has(InvoiceItem::factory(), 'items')->create();
    $html = view('pdf.invoice', ['invoice' => $issued->load(['customer', 'items', 'staffMember']), 'salon' => PdfRenderer::salonDetails()])->render();

    expect($html)->not->toContain('class="watermark"');
});

test('artisan invoice:regenerate-pdf rewrites the file', function () {
    $invoice = Invoice::factory()->has(InvoiceItem::factory(), 'items')->create(['invoice_number' => 'WS-0009']);

    $this->artisan('invoice:regenerate-pdf', ['id' => $invoice->id])
        ->expectsOutputToContain('WS-0009')
        ->assertSuccessful();

    Storage::disk('local')->assertExists('invoices/WS-0009.pdf');

    $this->artisan('invoice:regenerate-pdf', ['id' => 999999])->assertFailed();
});
