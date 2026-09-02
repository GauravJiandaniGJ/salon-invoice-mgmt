<?php

namespace App\Http\Controllers;

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

    // ---------- Daily ----------

    public function daily(Request $request): Response
    {
        $date = $this->dailyDate($request);

        return Inertia::render('reports/Daily', [
            'report' => $this->reports->daily($date),
            'can_pick_date' => $request->user()->isOwner(),
        ]);
    }

    public function dailyPdf(Request $request, PdfRenderer $pdf): HttpResponse
    {
        $date = $this->dailyDate($request);
        $report = $this->reports->daily($date);

        return response($pdf->dailyStatement($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Daily-Statement-'.$date->toDateString().'.pdf"',
        ]);
    }

    public function dailyCsv(Request $request): StreamedResponse
    {
        $date = $this->dailyDate($request);
        $report = $this->reports->daily($date);

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
        $rows[] = ['Earnings', '', '', '', '', $report['earnings']['total']];
        $rows[] = ['Expenses', '', '', '', '', $report['expenses']['total']];
        $rows[] = ['Net', '', '', '', '', $report['net']];
        $rows[] = ['Cash in hand', '', '', '', '', $report['cash_in_hand']];

        return CsvExporter::stream(
            'daily-statement-'.$date->toDateString().'.csv',
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

    // ---------- helpers ----------

    /** Staff are always pinned to today; owner may pick any date. */
    protected function dailyDate(Request $request): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if (! $request->user()->isOwner()) {
            return $today;
        }

        return $this->parseDate($request->query('date')) ?? $today;
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
