<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StaffMember;
use App\Services\InvoiceService;
use App\Services\PdfRenderer;
use App\Services\ReportService;
use Carbon\CarbonImmutable;

function staffTestInvoice(array $attrs = []): Invoice
{
    return Invoice::factory()->for(Customer::factory())->create(['invoice_date' => '2026-09-10', ...$attrs]);
}

test('line staff defaults to the invoice staff and an explicit line override wins', function () {
    $this->mock(PdfRenderer::class, fn ($m) => $m->shouldReceive('render')->andReturn('invoices/x.pdf'));
    [$raj, $priya] = [StaffMember::factory()->create(['name' => 'Raj']), StaffMember::factory()->create(['name' => 'Priya'])];

    $invoice = app(InvoiceService::class)->create([
        'customer' => ['phone' => '9876543210', 'name' => 'Asha'],
        'staff_member_id' => $raj->id,
        'items' => [
            ['service_id' => null, 'description' => 'Haircut', 'unit_price' => 300, 'quantity' => 1],
            ['service_id' => null, 'description' => 'Facial', 'unit_price' => 1000, 'quantity' => 1, 'staff_member_id' => $priya->id],
        ],
        'discount_type' => null, 'discount_value' => 0, 'payment_mode' => 'cash', 'payment_status' => 'paid', 'notes' => null,
    ], owner());

    expect($invoice->items[0]->staff_member_id)->toBe($raj->id)
        ->and($invoice->items[1]->staff_member_id)->toBe($priya->id);

    $this->actingAs(owner())->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page->where('invoice.items.1.staff_member.name', 'Priya'));
});

test('staff report attributes line revenue with invoice-staff fallback, unassigned row and commission', function () {
    $raj = StaffMember::factory()->create(['name' => 'Raj', 'commission_percent' => 10]);
    $priya = StaffMember::factory()->create(['name' => 'Priya', 'commission_percent' => 0]);

    $a = staffTestInvoice(['staff_member_id' => $raj->id, 'total' => 1300]);
    InvoiceItem::factory()->create(['invoice_id' => $a->id, 'staff_member_id' => null, 'unit_price' => 300, 'line_total' => 300]); // falls back to Raj
    InvoiceItem::factory()->create(['invoice_id' => $a->id, 'staff_member_id' => $priya->id, 'unit_price' => 1000, 'line_total' => 1000]);

    $b = staffTestInvoice(['staff_member_id' => null, 'total' => 200]);
    InvoiceItem::factory()->create(['invoice_id' => $b->id, 'staff_member_id' => null, 'unit_price' => 200, 'line_total' => 200]); // unassigned

    $void = Invoice::factory()->for(Customer::factory())->void()->create(['invoice_date' => '2026-09-10', 'staff_member_id' => $raj->id]);
    InvoiceItem::factory()->create(['invoice_id' => $void->id, 'unit_price' => 999, 'line_total' => 999]);

    $report = app(ReportService::class)->staff(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'), $priya->id);
    $rows = collect($report['rows'])->keyBy('name');

    expect($rows['Priya']['revenue'])->toBe(1000.0)
        ->and($rows['Raj']['revenue'])->toBe(300.0)
        ->and($rows['Raj']['commission'])->toBe(30.0)
        ->and($rows['Raj']['invoices_count'])->toBe(1)
        ->and($rows['Unassigned']['revenue'])->toBe(200.0)
        ->and($rows['Unassigned']['staff_member_id'])->toBeNull()
        ->and($report['totals']['revenue'])->toBe(1500.0)
        ->and($report['totals']['invoices_count'])->toBe(2)
        ->and($report['selected']['name'])->toBe('Priya')
        ->and($report['selected']['invoices'])->toHaveCount(1)
        ->and($report['selected']['invoices'][0]['invoice_number'])->toBe($a->invoice_number);
});

test('statement covers a date range with a range label and staff rows', function () {
    $raj = StaffMember::factory()->create(['name' => 'Raj']);
    foreach (['2026-09-01', '2026-09-03', '2026-09-08'] as $day) {
        $inv = staffTestInvoice(['invoice_date' => $day, 'staff_member_id' => $raj->id, 'total' => 100]);
        InvoiceItem::factory()->create(['invoice_id' => $inv->id, 'unit_price' => 100, 'line_total' => 100]);
    }

    $report = app(ReportService::class)->statement(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-07'));

    expect($report['invoices_count'])->toBe(2)
        ->and($report['earnings']['total'])->toBe(200.0)
        ->and($report['from'])->toBe('2026-09-01')->and($report['to'])->toBe('2026-09-07')
        ->and($report['date_label'])->toBe('1 Sep – 7 Sep 2026')
        ->and($report['by_staff'][0]['name'])->toBe('Raj')
        ->and($report['by_staff'][0]['revenue'])->toBe(200.0);
});

test('owner can request a range on the daily page, staff stays pinned to today', function () {
    $this->actingAs(owner())->get('/reports/daily?from=2026-09-01&to=2026-09-07')
        ->assertOk()->assertInertia(fn ($p) => $p->where('report.from', '2026-09-01')->where('report.to', '2026-09-07'));

    $this->actingAs(staff())->get('/reports/daily?from=2026-09-01&to=2026-09-07')
        ->assertOk()->assertInertia(fn ($p) => $p->where('report.from', now()->toDateString()));
});

test('every report pdf downloads as an attachment', function () {
    $owner = owner();
    foreach ([
        '/reports/daily/pdf?from=2026-09-01&to=2026-09-07' => 'Statement-2026-09-01_2026-09-07.pdf',
        '/reports/monthly/pdf?month=2026-09' => 'Monthly-2026-09.pdf',
        '/reports/services/pdf?from=2026-09-01&to=2026-09-30' => 'Services-2026-09-01_2026-09-30.pdf',
        '/reports/staff/pdf?from=2026-09-01&to=2026-09-30' => 'Staff-2026-09-01_2026-09-30.pdf',
    ] as $url => $filename) {
        $response = $this->actingAs($owner)->get($url);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
        expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
    }

    $this->actingAs(staff())->get('/reports/staff')->assertForbidden();
    $this->actingAs($owner)->get('/reports/staff?from=2026-09-01&to=2026-09-30')->assertOk()
        ->assertInertia(fn ($p) => $p->component('reports/Staff', false)->has('report.rows')->has('staff_members'));
});

test('staff commission percent is validated and saved', function () {
    $member = StaffMember::factory()->create();
    $this->actingAs(owner())->patch("/settings/staff-members/{$member->id}", ['commission_percent' => 12.5])->assertSessionHasNoErrors();
    expect((float) $member->fresh()->commission_percent)->toBe(12.5);
    $this->actingAs(owner())->patch("/settings/staff-members/{$member->id}", ['commission_percent' => 150])->assertSessionHasErrors('commission_percent');
});
