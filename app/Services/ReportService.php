<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StaffMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Single source of truth for dashboard / daily / monthly / services numbers.
 * Returns plain arrays shaped exactly like the TS types in resources/js/types/index.ts.
 * All ranges use invoice_date / expense_date (Asia/Kolkata calendar dates); only issued invoices count.
 */
class ReportService
{
    public const MODES = ['cash', 'upi', 'card', 'other'];

    /** @return array<string, mixed> DailyReport */
    public function daily(CarbonInterface $date): array
    {
        $day = $date->toDateString();

        $invoices = Invoice::query()
            ->with(['customer', 'staffMember'])
            ->whereDate('invoice_date', $day)
            ->orderBy('id')
            ->get();

        $issued = $invoices->where('status', Invoice::STATUS_ISSUED)->values();
        $voided = $invoices->where('status', Invoice::STATUS_VOID)->values();

        $expenses = Expense::query()->whereDate('expense_date', $day)->orderBy('id')->get();

        $earningsByMode = $this->byMode($issued, 'total');
        $expensesByMode = $this->byMode($expenses, 'amount');
        $earnings = $this->round((float) $issued->sum('total'));
        $expenseTotal = $this->round((float) $expenses->sum('amount'));

        return [
            'date' => $day,
            'date_label' => CarbonImmutable::parse($day)->format('D, j M Y'),
            'invoices_count' => $issued->count(),
            'customers_served' => $issued->pluck('customer_id')->unique()->count(),
            'earnings' => ['total' => $earnings, 'by_mode' => $earningsByMode],
            'expenses' => ['total' => $expenseTotal, 'by_mode' => $expensesByMode],
            'net' => $this->round($earnings - $expenseTotal),
            'cash_in_hand' => $this->round($earningsByMode['cash'] - $expensesByMode['cash']),
            'invoices' => $issued->map(fn (Invoice $i) => $this->invoiceLine($i))->all(),
            'voided' => $voided->map(fn (Invoice $i) => $this->invoiceLine($i, true))->all(),
            'expense_lines' => $expenses->map(fn (Expense $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'payment_mode' => $e->payment_mode,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> MonthlyReport */
    public function monthly(string $ym): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m', $ym)->startOfMonth();
        $end = $start->endOfMonth();

        $invoices = Invoice::query()
            ->issued()
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'invoice_date', 'total', 'payment_mode', 'staff_member_id']);

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'expense_date', 'amount', 'payment_mode']);

        $invoicesByDay = $invoices->groupBy(fn (Invoice $i) => $i->invoice_date->toDateString());
        $expensesByDay = $expenses->groupBy(fn (Expense $e) => $e->expense_date->toDateString());

        $days = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $key = $d->toDateString();
            $dayInvoices = $invoicesByDay->get($key, collect());
            $earnings = $this->round((float) $dayInvoices->sum('total'));
            $spent = $this->round((float) $expensesByDay->get($key, collect())->sum('amount'));
            $days[] = [
                'date' => $key,
                'invoices_count' => $dayInvoices->count(),
                'earnings' => $earnings,
                'expenses' => $spent,
                'net' => $this->round($earnings - $spent),
            ];
        }

        $earnings = $this->round((float) $invoices->sum('total'));
        $spent = $this->round((float) $expenses->sum('amount'));

        $topServices = InvoiceItem::query()
            ->selectRaw('description, COUNT(*) as cnt, SUM(line_total) as revenue')
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->groupBy('description')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'description' => $row->description,
                'count' => (int) $row->cnt,
                'revenue' => $this->round((float) $row->revenue),
            ])->all();

        $staffNames = StaffMember::query()->pluck('name', 'id');
        $byStaff = $invoices->groupBy(fn (Invoice $i) => $i->staff_member_id ?? 0)
            ->map(fn (Collection $group, $staffId) => [
                'staff_member' => $staffId ? ($staffNames[$staffId] ?? 'Unknown') : 'Unassigned',
                'invoices_count' => $group->count(),
                'revenue' => $this->round((float) $group->sum('total')),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();

        return [
            'month' => $start->format('Y-m'),
            'month_label' => $start->format('F Y'),
            'days' => $days,
            'totals' => [
                'invoices_count' => $invoices->count(),
                'earnings' => $earnings,
                'expenses' => $spent,
                'net' => $this->round($earnings - $spent),
            ],
            'earnings_by_mode' => $this->byMode($invoices, 'total'),
            'expenses_by_mode' => $this->byMode($expenses, 'amount'),
            'top_services' => $topServices,
            'by_staff' => $byStaff,
        ];
    }

    /** @return array<string, mixed> ServicesReport */
    public function services(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = InvoiceItem::query()
            ->selectRaw('invoice_items.service_id, invoice_items.description, COUNT(*) as cnt, SUM(invoice_items.quantity) as qty, SUM(invoice_items.line_total) as revenue')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', Invoice::STATUS_ISSUED)
            ->whereBetween('invoices.invoice_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('invoice_items.service_id', 'invoice_items.description')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'service_id' => $row->service_id === null ? null : (int) $row->service_id,
                'description' => $row->description,
                'count' => (int) $row->cnt,
                'quantity' => (float) $row->qty,
                'revenue' => $this->round((float) $row->revenue),
            ]);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows->all(),
            'totals' => [
                'count' => (int) $rows->sum('count'),
                'revenue' => $this->round((float) $rows->sum('revenue')),
            ],
        ];
    }

    /** @return array<string, mixed> DashboardData */
    public function dashboard(User $user): array
    {
        $today = $this->daily(CarbonImmutable::today());

        $month = null;
        if ($user->isOwner()) {
            $m = $this->monthly(CarbonImmutable::today()->format('Y-m'));
            $month = $m['totals'];
        }

        $recent = Invoice::query()
            ->with(['customer', 'items'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Invoice $i) => $this->invoiceRow($i))
            ->all();

        return [
            'today' => [
                'invoices_count' => $today['invoices_count'],
                'earnings' => $today['earnings']['total'],
                'expenses' => $today['expenses']['total'],
                'net' => $today['net'],
                'by_mode' => $today['earnings']['by_mode'],
            ],
            'month' => $month,
            'recent_invoices' => $recent,
        ];
    }

    /** InvoiceRow shape (see TS types). Requires customer + items loaded. */
    public function invoiceRow(Invoice $invoice): array
    {
        $descriptions = $invoice->items->pluck('description');
        $summary = $descriptions->take(2)->implode(', ');
        if ($descriptions->count() > 2) {
            $summary .= ' +'.($descriptions->count() - 2);
        }

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'customer' => [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'phone_display' => $invoice->customer->phone_display,
            ],
            'items_summary' => $summary,
            'total' => (float) $invoice->total,
            'payment_mode' => $invoice->payment_mode,
            'payment_status' => $invoice->payment_status,
            'status' => $invoice->status,
            'whatsapp_sent_at' => $invoice->whatsapp_sent_at?->toISOString(),
        ];
    }

    /** ReportInvoiceLine shape. */
    protected function invoiceLine(Invoice $invoice, bool $withReason = false): array
    {
        $line = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_name' => $invoice->customer->name,
            'total' => (float) $invoice->total,
            'payment_mode' => $invoice->payment_mode,
            'staff_member' => $invoice->staffMember?->name,
        ];

        if ($withReason) {
            $line['void_reason'] = $invoice->void_reason;
        }

        return $line;
    }

    /** @return array<string, float> keyed by every payment mode */
    protected function byMode(Collection $rows, string $column): array
    {
        $out = [];
        foreach (self::MODES as $mode) {
            $out[$mode] = $this->round((float) $rows->where('payment_mode', $mode)->sum($column));
        }

        return $out;
    }

    protected function round(float $n): float
    {
        return round($n, 2);
    }
}
