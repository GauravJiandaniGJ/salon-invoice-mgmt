<?php

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\PdfRenderer;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 11:00', 'Asia/Kolkata'));
});

afterEach(fn () => Carbon::setTestNow());

test('owner can view any daily statement', function () {
    Invoice::factory()->create(['invoice_date' => '2026-09-01', 'total' => 900, 'payment_mode' => 'upi']);

    $this->actingAs(owner())
        ->get('/reports/daily?date=2026-09-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Daily', false)
            ->where('can_pick_date', true)
            ->where('report.date', '2026-09-01')
            ->where('report.earnings.total', 900)
            ->where('report.earnings.by_mode.upi', 900)
        );
});

test('staff daily report is pinned to today', function () {
    Invoice::factory()->create(['invoice_date' => '2026-09-01', 'total' => 900]);
    Invoice::factory()->create(['invoice_date' => '2026-09-10', 'total' => 250]);

    $this->actingAs(staff())
        ->get('/reports/daily?date=2026-09-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_pick_date', false)
            ->where('report.date', '2026-09-10')
            ->where('report.earnings.total', 250)
        );
});

test('invalid date falls back to today', function () {
    $this->actingAs(owner())
        ->get('/reports/daily?date=2026-13-45')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.date', '2026-09-10'));
});

test('daily csv streams invoices, expenses and totals', function () {
    Invoice::factory()->create(['invoice_date' => '2026-09-10', 'invoice_number' => 'WS-0007', 'total' => 400, 'payment_mode' => 'cash']);
    Expense::factory()->create(['expense_date' => '2026-09-10', 'amount' => 100, 'payment_mode' => 'cash', 'description' => 'Shampoo stock']);

    $response = $this->actingAs(staff())->get('/reports/daily.csv');
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('WS-0007')
        ->toContain('Shampoo stock')
        ->toContain('"Cash in hand",,,,,300');
});

test('daily pdf uses the PdfRenderer daily statement', function () {
    $this->mock(PdfRenderer::class)
        ->shouldReceive('reportPdf')
        ->once()
        ->withArgs(fn (string $view, array $data) => $view === 'pdf.daily-statement' && $data['report']['date'] === '2026-09-10')
        ->andReturn('%PDF-fake');

    $this->actingAs(staff())
        ->get('/reports/daily/pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Statement-2026-09-10.pdf"');
});

test('daily statement blade view renders with a real report', function () {
    $inv = Invoice::factory()->create(['invoice_date' => '2026-09-10', 'invoice_number' => 'WS-0042', 'total' => 1500, 'payment_mode' => 'card']);
    Invoice::factory()->void('Duplicate entry')->create(['invoice_date' => '2026-09-10', 'invoice_number' => 'WS-0043', 'total' => 100]);
    InvoiceItem::factory()->create(['invoice_id' => $inv->id]);
    Expense::factory()->create(['expense_date' => '2026-09-10', 'amount' => 75, 'description' => 'Electricity bill']);

    $report = app(ReportService::class)->daily(CarbonImmutable::parse('2026-09-10'));
    $html = view('pdf.daily-statement', ['report' => $report])->render();

    expect($html)->toContain('WS-0042')
        ->toContain('WS-0043')
        ->toContain('Duplicate entry')
        ->toContain('Electricity bill')
        ->toContain('₹1,500')
        ->toContain('Thu, 10 Sep 2026');
});

test('monthly and services reports are owner only', function () {
    $this->actingAs(staff())->get('/reports/monthly')->assertForbidden();
    $this->actingAs(staff())->get('/reports/monthly.csv')->assertForbidden();
    $this->actingAs(staff())->get('/reports/services')->assertForbidden();
    $this->actingAs(staff())->get('/reports/services.csv')->assertForbidden();
});

test('owner monthly report page and csv', function () {
    Invoice::factory()->create(['invoice_date' => '2026-09-03', 'total' => 1000]);
    Expense::factory()->create(['expense_date' => '2026-09-03', 'amount' => 100]);

    $this->actingAs(owner())
        ->get('/reports/monthly?month=2026-09')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Monthly', false)
            ->where('report.month', '2026-09')
            ->has('report.days', 30)
            ->where('report.totals.net', 900)
        );

    $csv = $this->actingAs(owner())->get('/reports/monthly.csv?month=2026-09')->assertOk()->streamedContent();
    expect($csv)->toContain('2026-09-03,1,1000,100,900')->toContain('Total,1,1000,100,900');
});

test('owner services report defaults to the current month and exports csv', function () {
    $inv = Invoice::factory()->create(['invoice_date' => '2026-09-02', 'total' => 300]);
    InvoiceItem::factory()->create(['invoice_id' => $inv->id, 'description' => 'Beard Crafting', 'quantity' => 2, 'unit_price' => 150, 'line_total' => 300]);

    $this->actingAs(owner())
        ->get('/reports/services')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Services', false)
            ->where('report.from', '2026-09-01')
            ->where('report.to', '2026-09-30')
            ->where('report.rows.0.description', 'Beard Crafting')
            ->where('report.rows.0.quantity', 2)
            ->where('report.totals.revenue', 300)
        );

    $csv = $this->actingAs(owner())->get('/reports/services.csv?from=2026-09-01&to=2026-09-30')->assertOk()->streamedContent();
    expect($csv)->toContain('"Beard Crafting",1,2,300');
});

test('services report swaps a reversed range', function () {
    $this->actingAs(owner())
        ->get('/reports/services?from=2026-09-30&to=2026-09-01')
        ->assertInertia(fn ($page) => $page->where('report.from', '2026-09-01')->where('report.to', '2026-09-30'));
});
