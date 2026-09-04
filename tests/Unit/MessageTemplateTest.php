<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\WhatsApp\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function templateInvoice(int $items = 1, float $total = 1400): Invoice
{
    $customer = Customer::factory()->create(['name' => 'Priya Sharma Rao', 'phone' => '919876543210']);

    return Invoice::factory()
        ->for($customer)
        ->has(InvoiceItem::factory()->count($items)->sequence(fn ($s) => ['description' => 'Item '.($s->index + 1)]), 'items')
        ->create(['invoice_number' => 'WS-0003', 'public_code' => 'Abcdefghij', 'total' => $total, 'invoice_date' => '2026-09-02']);
}

test('renders every placeholder', function () {
    Carbon::setTestNow('2026-09-02 09:30:00');
    Setting::set('salon_name', 'Wow Salon');
    Setting::set('app_url', 'https://wowsalon.example/');

    $invoice = templateInvoice(items: 5, total: 140000);

    $out = (new MessageTemplate)->render(
        '{greeting} {customer_name}! {salon_name} {invoice_number} ₹{total} {invoice_link} {date} [{items}]',
        $invoice
    );

    // The partner credit is appended automatically on its own line.
    expect($out)->toBe(
        'Good morning Priya! Wow Salon WS-0003 ₹1,40,000 https://wowsalon.example/i/Abcdefghij 2 Sep 2026 [Item 1, Item 2, Item 3 +2 more]'
        ."\n_".MessageTemplate::poweredBy().'_'
    );
});

test('default template keeps newlines, stays short and carries partner branding', function () {
    Carbon::setTestNow('2026-09-02 15:00:00');
    Setting::set('salon_name', 'Wow Salon');
    Setting::set('app_url', 'https://wowsalon.example');
    $invoice = templateInvoice();

    $out = (new MessageTemplate)->render(config('salon.defaults.whatsapp_template'), $invoice);

    expect($out)->toBe("Good afternoon Priya 😃\nThank you for visiting Wow Salon. Your invoice WS-0003 for ₹1,400 is ready:\nhttps://wowsalon.example/i/Abcdefghij\n\nSee you again soon!\n_Powered by TodoIT · todoitservices.com_")
        ->and(mb_strlen($out))->toBeLessThan(400)
        ->and(rawurlencode($out))->toContain('%0A')->toContain('%F0%9F%98%83');
});

test('powered_by placeholder uses the config label and host', function () {
    expect(MessageTemplate::poweredBy())->toBe('Powered by TodoIT · todoitservices.com');
});

test('greeting follows the IST hour', function () {
    Carbon::setTestNow('2026-09-02 11:59:00');
    expect(MessageTemplate::greeting())->toBe('Good morning');

    Carbon::setTestNow('2026-09-02 12:00:00');
    expect(MessageTemplate::greeting())->toBe('Good afternoon');

    Carbon::setTestNow('2026-09-02 16:59:00');
    expect(MessageTemplate::greeting())->toBe('Good afternoon');

    Carbon::setTestNow('2026-09-02 17:00:00');
    expect(MessageTemplate::greeting())->toBe('Good evening');

    Carbon::setTestNow('2026-09-02 23:30:00');
    expect(MessageTemplate::greeting())->toBe('Good evening');
});

test('amounts use Indian grouping and drop decimals when whole', function () {
    expect(MessageTemplate::formatAmount(1400))->toBe('1,400')
        ->and(MessageTemplate::formatAmount(140000))->toBe('1,40,000')
        ->and(MessageTemplate::formatAmount(12345678.5))->toBe('1,23,45,678.50')
        ->and(MessageTemplate::formatAmount(999))->toBe('999')
        ->and(MessageTemplate::formatAmount(0))->toBe('0')
        ->and(MessageTemplate::formatAmount(1400, forceDecimals: true))->toBe('1,400.00')
        ->and(MessageTemplate::formatAmount(-1500))->toBe('-1,500');
});

test('items summary caps at three', function () {
    expect(MessageTemplate::itemsSummary(['A']))->toBe('A')
        ->and(MessageTemplate::itemsSummary(['A', 'B', 'C']))->toBe('A, B, C')
        ->and(MessageTemplate::itemsSummary(['A', 'B', 'C', 'D', 'E']))->toBe('A, B, C +2 more');
});

test('the technology partner credit cannot be removed from the template', function () {
    $invoice = Invoice::factory()
        ->for(Customer::factory()->create(['name' => 'Asha']))
        ->create(['invoice_number' => 'WS-0009', 'total' => 500]);

    $render = fn (string $template) => app(MessageTemplate::class)->render($template, $invoice);
    $signature = '_'.MessageTemplate::poweredBy().'_';

    // Removed entirely, replaced with a competitor, or duplicated — the credit still stands exactly once.
    expect($render('Hi {customer_name}'))->toEndWith($signature)
        ->and($render("Hi {customer_name}\n_Powered by SomeoneElse_"))->toEndWith($signature)
        ->and($render("Hi {customer_name}\n_Powered by SomeoneElse_"))->not->toContain('SomeoneElse')
        ->and(substr_count($render("Hi\nPowered by TodoIT · todoitservices.com"), 'TodoIT'))->toBe(1);
});

test('the credit is not advertised as an editable placeholder', function () {
    expect(MessageTemplate::PLACEHOLDERS)->not->toContain('{powered_by}');
});
