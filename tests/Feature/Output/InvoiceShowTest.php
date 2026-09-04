<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\InvoiceService;
use App\Services\PdfRenderer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

test('invoice page builds device links with a normalised phone, encoded newlines and intact emoji', function () {
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
            ->where('whatsapp_mode', 'link')
            ->where('whatsapp_mobile_url', fn ($url) => str_starts_with($url, 'https://wa.me/919876543210?text=')
                && str_contains($url, '%0A')
                && ! str_contains($url, "\n")
                && str_contains($url, rawurlencode('https://wowsalon.example/i/Qwe123Rty4')))
            ->where('whatsapp_web_url', fn ($url) => str_starts_with($url, 'https://web.whatsapp.com/send?phone=919876543210&text=')
                && str_contains($url, '%F0%9F%98%83') // 😃 survives as raw UTF-8 percent-encoding
                && str_contains($url, '%20') && ! str_contains($url, '+')
                && ! preg_match('/[^\x20-\x7E]/', $url) // no literal non-ASCII in the URL
                && str_contains($url, '%0A'))
            ->where('whatsapp_message', fn ($m) => str_contains($m, 'Priya 😃') && str_contains($m, 'WS-0011') && str_contains($m, '₹1,400') && str_contains($m, 'Powered by TodoIT · todoitservices.com'))
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

test('send endpoint returns the desktop link in link mode without marking sent', function () {
    Setting::set('app_url', 'https://wowsalon.example');
    $invoice = showInvoice();

    $this->actingAs(staff())
        ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120')
        ->postJson("/invoices/{$invoice->id}/send")
        ->assertOk()
        ->assertJson(['sent' => false, 'whatsapp_sent_at' => null, 'error' => null])
        ->assertJsonPath('fallback_url', fn ($url) => str_starts_with($url, 'https://web.whatsapp.com/send?phone=919876543210&text='));

    expect($invoice->fresh()->whatsapp_sent_at)->toBeNull();
});

test('send endpoint returns the wa.me link for phones', function () {
    $invoice = showInvoice();

    $this->actingAs(staff())
        ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1')
        ->postJson("/invoices/{$invoice->id}/send")
        ->assertOk()
        ->assertJsonPath('fallback_url', fn ($url) => str_starts_with($url, 'https://wa.me/919876543210?text='));
});

test('send endpoint refuses void invoices', function () {
    $invoice = showInvoice();
    $invoice->update(['status' => 'void', 'void_reason' => 'x', 'voided_at' => now()]);

    $this->actingAs(staff())->postJson("/invoices/{$invoice->id}/send")->assertStatus(422)->assertJson(['sent' => false]);
});

test('cloud driver sends through the graph api and marks sent', function () {
    Setting::set('whatsapp_driver', 'cloud');
    Setting::set('whatsapp_cloud_phone_id', '123456789');
    Setting::set('whatsapp_cloud_token', 'EAAtoken');
    Setting::set('whatsapp_cloud_template', 'invoice_ready');
    Setting::set('app_url', 'https://wowsalon.example');
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
    ]);
    $invoice = showInvoice();

    $this->actingAs(staff())
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page->where('whatsapp_mode', 'cloud'));

    $this->actingAs(staff())
        ->postJson("/invoices/{$invoice->id}/send")
        ->assertOk()
        ->assertJson(['sent' => true, 'fallback_url' => null, 'error' => null])
        ->assertJsonPath('whatsapp_sent_at', fn ($v) => $v !== null);

    expect($invoice->fresh()->whatsapp_sent_at)->not->toBeNull();

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://graph.facebook.com/v20.0/123456789/messages'
            && $request->hasHeader('Authorization', 'Bearer EAAtoken')
            && $body['to'] === '919876543210'
            && $body['type'] === 'template'
            && $body['template']['name'] === 'invoice_ready'
            && $body['template']['components'][0]['parameters'][0]['text'] === 'Priya'
            && $body['template']['components'][0]['parameters'][1]['text'] === 'WS-0011'
            && $body['template']['components'][0]['parameters'][2]['text'] === 'https://wowsalon.example/i/Qwe123Rty4';
    });
});

test('cloud driver falls back to a link when the api rejects the message', function () {
    Setting::set('whatsapp_driver', 'cloud');
    Setting::set('whatsapp_cloud_phone_id', '123456789');
    Setting::set('whatsapp_cloud_token', 'EAAtoken');
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template name does not exist']], 400),
    ]);
    $invoice = showInvoice();

    $this->actingAs(staff())
        ->postJson("/invoices/{$invoice->id}/send")
        ->assertOk()
        ->assertJson(['sent' => false, 'error' => 'Template name does not exist'])
        ->assertJsonPath('fallback_url', fn ($url) => str_contains($url, '919876543210'));

    expect($invoice->fresh()->whatsapp_sent_at)->toBeNull();
});

test('cloud driver without credentials falls back to a link', function () {
    Setting::set('whatsapp_driver', 'cloud');
    Http::fake();
    $invoice = showInvoice();

    $this->actingAs(staff())
        ->postJson("/invoices/{$invoice->id}/send")
        ->assertOk()
        ->assertJson(['sent' => false])
        ->assertJsonPath('error', fn ($e) => str_contains($e, 'not configured'));

    Http::assertNothingSent();
});
