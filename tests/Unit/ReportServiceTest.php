<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StaffMember;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function reports(): ReportService
{
    return app(ReportService::class);
}

function invoiceOn(string $date, float $total, string $mode = 'cash', array $extra = []): Invoice
{
    return Invoice::factory()->create([
        'invoice_date' => $date,
        'subtotal' => $total,
        'total' => $total,
        'payment_mode' => $mode,
        ...$extra,
    ]);
}

test('daily totals are split by payment mode and voids are excluded but listed', function () {
    $day = '2026-09-02';
    invoiceOn($day, 500, 'cash');
    invoiceOn($day, 300, 'cash');
    invoiceOn($day, 1200, 'upi');
    invoiceOn($day, 700, 'card');
    invoiceOn($day, 999, 'cash', ['status' => Invoice::STATUS_VOID, 'void_reason' => 'Wrong customer', 'voided_at' => now()]);
    invoiceOn('2026-09-01', 5000, 'cash'); // other day

    Expense::factory()->create(['expense_date' => $day, 'amount' => 200, 'payment_mode' => 'cash']);
    Expense::factory()->create(['expense_date' => $day, 'amount' => 150, 'payment_mode' => 'upi']);

    $r = reports()->daily(CarbonImmutable::parse($day));

    expect($r['date'])->toBe($day)
        ->and($r['invoices_count'])->toBe(4)
        ->and($r['customers_served'])->toBe(4)
        ->and($r['earnings']['total'])->toBe(2700.0)
        ->and($r['earnings']['by_mode'])->toBe(['cash' => 800.0, 'upi' => 1200.0, 'card' => 700.0, 'other' => 0.0])
        ->and($r['expenses']['total'])->toBe(350.0)
        ->and($r['expenses']['by_mode']['cash'])->toBe(200.0)
        ->and($r['net'])->toBe(2350.0)
        ->and($r['cash_in_hand'])->toBe(600.0)
        ->and($r['invoices'])->toHaveCount(4)
        ->and($r['voided'])->toHaveCount(1)
        ->and($r['voided'][0]['void_reason'])->toBe('Wrong customer')
        ->and($r['expense_lines'])->toHaveCount(2);
});

test('customers served counts distinct customers', function () {
    $customer = Customer::factory()->create();
    invoiceOn('2026-09-02', 100, 'cash', ['customer_id' => $customer->id]);
    invoiceOn('2026-09-02', 100, 'cash', ['customer_id' => $customer->id]);

    $r = reports()->daily(CarbonImmutable::parse('2026-09-02'));

    expect($r['invoices_count'])->toBe(2)->and($r['customers_served'])->toBe(1);
});

test('an invoice billed at 00:30 IST belongs to that IST day, not the UTC day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-03 00:30', 'Asia/Kolkata'));

    expect(now()->toDateString())->toBe('2026-09-03')
        ->and(now()->utc()->toDateString())->toBe('2026-09-02');

    $invoice = invoiceOn(now()->toDateString(), 450);

    $today = reports()->daily(CarbonImmutable::today());
    $yesterday = reports()->daily(CarbonImmutable::yesterday());

    expect($today['date'])->toBe('2026-09-03')
        ->and(collect($today['invoices'])->pluck('id'))->toContain($invoice->id)
        ->and($yesterday['invoices'])->toBeEmpty();

    Carbon::setTestNow();
});

test('an invoice billed at 23:30 IST still belongs to that day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-02 23:30', 'Asia/Kolkata'));

    $invoice = invoiceOn(now()->toDateString(), 450);
    $r = reports()->daily(CarbonImmutable::today());

    expect($r['date'])->toBe('2026-09-02')
        ->and(collect($r['invoices'])->pluck('id'))->toContain($invoice->id);

    Carbon::setTestNow();
});

test('monthly report has one row per day, totals, top services and staff split', function () {
    $stylist = StaffMember::factory()->create(['name' => 'Priya']);

    $a = invoiceOn('2026-09-01', 1000, 'cash', ['staff_member_id' => $stylist->id]);
    $b = invoiceOn('2026-09-15', 2000, 'upi');
    invoiceOn('2026-09-20', 777, 'cash', ['status' => Invoice::STATUS_VOID]);
    invoiceOn('2026-10-01', 5000, 'cash'); // next month

    InvoiceItem::factory()->create(['invoice_id' => $a->id, 'description' => 'Haircut – Men', 'line_total' => 1000, 'unit_price' => 1000]);
    InvoiceItem::factory()->create(['invoice_id' => $b->id, 'description' => 'Keratin – Upto Waist', 'line_total' => 2000, 'unit_price' => 2000]);

    Expense::factory()->create(['expense_date' => '2026-09-15', 'amount' => 300, 'payment_mode' => 'cash']);

    $r = reports()->monthly('2026-09');

    expect($r['month'])->toBe('2026-09')
        ->and($r['month_label'])->toBe('September 2026')
        ->and($r['days'])->toHaveCount(30)
        ->and($r['days'][0])->toBe(['date' => '2026-09-01', 'invoices_count' => 1, 'earnings' => 1000.0, 'expenses' => 0.0, 'net' => 1000.0])
        ->and($r['days'][14])->toBe(['date' => '2026-09-15', 'invoices_count' => 1, 'earnings' => 2000.0, 'expenses' => 300.0, 'net' => 1700.0])
        ->and($r['totals'])->toBe(['invoices_count' => 2, 'earnings' => 3000.0, 'expenses' => 300.0, 'net' => 2700.0])
        ->and($r['earnings_by_mode']['cash'])->toBe(1000.0)
        ->and($r['earnings_by_mode']['upi'])->toBe(2000.0)
        ->and($r['top_services'][0])->toBe(['description' => 'Keratin – Upto Waist', 'count' => 1, 'revenue' => 2000.0])
        ->and(collect($r['by_staff'])->pluck('name')->all())->toBe(['Unassigned', 'Priya'])
        ->and($r['by_staff'][1]['revenue'])->toBe(1000.0);
});

test('services report groups issued line items by service in range', function () {
    $in = invoiceOn('2026-09-05', 500);
    $void = invoiceOn('2026-09-06', 500, 'cash', ['status' => Invoice::STATUS_VOID]);
    $out = invoiceOn('2026-08-30', 500);

    InvoiceItem::factory()->create(['invoice_id' => $in->id, 'service_id' => null, 'description' => 'Shave', 'quantity' => 2, 'unit_price' => 150, 'line_total' => 300]);
    InvoiceItem::factory()->create(['invoice_id' => $in->id, 'service_id' => null, 'description' => 'Shave', 'quantity' => 1, 'unit_price' => 150, 'line_total' => 150]);
    InvoiceItem::factory()->create(['invoice_id' => $void->id, 'description' => 'Shave', 'line_total' => 150]);
    InvoiceItem::factory()->create(['invoice_id' => $out->id, 'description' => 'Shave', 'line_total' => 150]);

    $r = reports()->services(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'));

    expect($r['from'])->toBe('2026-09-01')
        ->and($r['rows'])->toHaveCount(1)
        ->and($r['rows'][0])->toBe(['service_id' => null, 'description' => 'Shave', 'count' => 2, 'quantity' => 3.0, 'revenue' => 450.0])
        ->and($r['totals'])->toBe(['count' => 2, 'revenue' => 450.0]);
});

test('dashboard gives month totals to owner only and last 10 invoices', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 12:00', 'Asia/Kolkata'));

    $customer = Customer::factory()->create(['name' => 'Asha Patel', 'phone' => '919876543210']);
    for ($i = 0; $i < 12; $i++) {
        $inv = invoiceOn('2026-09-10', 100, 'cash', ['customer_id' => $customer->id]);
        InvoiceItem::factory()->count(3)->create(['invoice_id' => $inv->id]);
    }

    $ownerData = reports()->dashboard(owner());
    $staffData = reports()->dashboard(staff());

    expect($ownerData['today']['invoices_count'])->toBe(12)
        ->and($ownerData['today']['earnings'])->toBe(1200.0)
        ->and($ownerData['today']['by_mode']['cash'])->toBe(1200.0)
        ->and($ownerData['month'])->toBe(['invoices_count' => 12, 'earnings' => 1200.0, 'expenses' => 0.0, 'net' => 1200.0])
        ->and($ownerData['recent_invoices'])->toHaveCount(10)
        ->and($ownerData['recent_invoices'][0]['customer']['phone_display'])->toBe('+91 98765 43210')
        ->and($ownerData['recent_invoices'][0]['items_summary'])->toContain('+1')
        ->and($staffData['month'])->toBeNull();

    Carbon::setTestNow();
});
