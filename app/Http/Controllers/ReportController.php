<?php

namespace App\Http\Controllers;

use App\Models\StaffMember;
use App\Services\CsvExporter;
use App\Services\PdfRenderer;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    // ---------- Statement (daily / date range) ----------

    public function daily(Request $request): Response
    {
        [$from, $to] = $this->statementRange($request);

        return Inertia::render('reports/Daily', [
            'report' => $this->reports->statement($from, $to),
            'can_pick_date' => $request->user()->isOwner(),
        ]);
    }

    public function dailyPdf(Request $request, PdfRenderer $pdf): HttpResponse
    {
        [$from, $to] = $this->statementRange($request);
        $report = $this->reports->statement($from, $to);

        return $this->pdfResponse($pdf->reportPdf('pdf.daily-statement', ['report' => $report]), 'Statement-'.$this->rangeSlug($from, $to).'.pdf');
    }

    public function dailyCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->statementRange($request);
        $report = $this->reports->statement($from, $to);

        $rows = [];
        foreach ($report['invoices'] as $inv) {
            $rows[] = ['Invoice', $inv['invoice_number'], $inv['customer_name'], $inv['staff_member'], $inv['payment_mode'], $inv['total']];
        }
        foreach ($report['voided'] as $inv) {
            $rows[] = ['Void', $inv['invoice_number'], $inv['customer_name'], $inv['void_reason'] ?? '', $inv['payment_mode'], 0];
        }
        foreach ($report['expense_lines'] as $exp) {
            $rows[] = ['Expense', $exp['category'], $exp['description'], '', $exp['payment_mode'], -$exp['amount']];
        }
        $rows[] = [];
        foreach ($report['by_staff'] as $st) {
            $rows[] = ['Staff', $st['name'], $st['services_count'].' services', $st['invoices_count'].' invoices', '', $st['revenue']];
        }
        $rows[] = [];
        $rows[] = ['Earnings', '', '', '', '', $report['earnings']['total']];
        $rows[] = ['Expenses', '', '', '', '', $report['expenses']['total']];
        $rows[] = ['Net', '', '', '', '', $report['net']];
        $rows[] = ['Cash in hand', '', '', '', '', $report['cash_in_hand']];

        return CsvExporter::stream(
            'statement-'.$this->rangeSlug($from, $to).'.csv',
            ['Type', 'Number / Category', 'Customer / Description', 'Staff / Reason', 'Payment', 'Amount'],
            $rows,
        );
    }

    // ---------- Monthly (owner) ----------

    public function monthly(Request $request): Response
    {
        return Inertia::render('reports/Monthly', [
            'report' => $this->reports->monthly($this->month($request)),
        ]);
    }

    public function monthlyCsv(Request $request): StreamedResponse
    {
        $report = $this->reports->monthly($this->month($request));

        $rows = array_map(fn ($d) => [$d['date'], $d['invoices_count'], $d['earnings'], $d['expenses'], $d['net']], $report['days']);
        $t = $report['totals'];
        $rows[] = ['Total', $t['invoices_count'], $t['earnings'], $t['expenses'], $t['net']];

        return CsvExporter::stream(
            'monthly-'.$report['month'].'.csv',
            ['Date', 'Invoices', 'Earnings', 'Expenses', 'Net'],
            $rows,
        );
    }

    public function monthlyPdf(Request $request, PdfRenderer $pdf): HttpResponse
    {
        $report = $this->reports->monthly($this->month($request));

        return $this->pdfResponse($pdf->reportPdf('pdf.monthly-report', ['report' => $report]), 'Monthly-'.$report['month'].'.pdf');
    }

    // ---------- Services (owner) ----------

    public function services(Request $request): Response
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('reports/Services', [
            'report' => $this->reports->services($from, $to),
        ]);
    }

    public function servicesCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->reports->services($from, $to);

        $rows = array_map(fn ($r) => [$r['description'], $r['count'], $r['quantity'], $r['revenue']], $report['rows']);
        $rows[] = ['Total', $report['totals']['count'], '', $report['totals']['revenue']];

        return CsvExporter::stream(
            'services-'.$from->toDateString().'-to-'.$to->toDateString().'.csv',
            ['Service', 'Times billed', 'Quantity', 'Revenue'],
            $rows,
        );
    }

    public function servicesPdf(Request $request, PdfRenderer $pdf): HttpResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->reports->services($from, $to);

        return $this->pdfResponse($pdf->reportPdf('pdf.services-report', ['report' => $report]), 'Services-'.$this->rangeSlug($from, $to).'.pdf');
    }

    // ---------- Staff (owner) ----------

    public function staff(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $staffId = $request->integer('staff_member_id') ?: null;

        return Inertia::render('reports/Staff', [
            'report' => $this->reports->staff($from, $to, $staffId),
            'staff_members' => StaffMember::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all(),
        ]);
    }

    public function staffCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->reports->staff($from, $to);

        $rows = array_map(fn ($r) => [$r['name'], $r['services_count'], $r['invoices_count'], $r['revenue'], $r['average_ticket'], $r['commission_percent'], $r['commission']], $report['rows']);
        $t = $report['totals'];
        $rows[] = ['Total', $t['services_count'], $t['invoices_count'], $t['revenue'], '', '', $t['commission']];

        return CsvExporter::stream(
            'staff-'.$this->rangeSlug($from, $to).'.csv',
            ['Staff', 'Services', 'Invoices', 'Revenue', 'Avg ticket', 'Commission %', 'Commission'],
            $rows,
        );
    }

    public function staffPdf(Request $request, PdfRenderer $pdf): HttpResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->reports->staff($from, $to);

        return $this->pdfResponse($pdf->reportPdf('pdf.staff-report', ['report' => $report]), 'Staff-'.$this->rangeSlug($from, $to).'.pdf');
    }

    // ---------- helpers ----------

    /** Staff are pinned to today; the owner may pass from/to (or a single date). */
    protected function statementRange(Request $request): array
    {
        $today = CarbonImmutable::today();

        if (! $request->user()->isOwner()) {
            return [$today, $today];
        }

        if ($request->filled('from') || $request->filled('to')) {
            $from = $this->parseDate($request->query('from')) ?? $today;
            $to = $this->parseDate($request->query('to')) ?? $from;

            return $to->lt($from) ? [$to, $from] : [$from, $to];
        }

        $date = $this->parseDate($request->query('date')) ?? $today;

        return [$date, $date];
    }

    protected function pdfResponse(string $binary, string $filename): HttpResponse
    {
        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function rangeSlug(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->toDateString() === $to->toDateString() ? $from->toDateString() : $from->toDateString().'_'.$to->toDateString();
    }

    protected function month(Request $request): string
    {
        $input = $request->query('month');

        if (is_string($input) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $input)) {
            return $input;
        }

        return CarbonImmutable::today()->format('Y-m');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    protected function range(Request $request): array
    {
        $today = CarbonImmutable::today();
        $from = $this->parseDate($request->query('from')) ?? $today->startOfMonth();
        $to = $this->parseDate($request->query('to')) ?? $today->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    protected function parseDate(mixed $input): ?CarbonImmutable
    {
        if (! is_string($input) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $input);

        return $date && $date->format('Y-m-d') === $input ? $date->startOfDay() : null;
    }
}
