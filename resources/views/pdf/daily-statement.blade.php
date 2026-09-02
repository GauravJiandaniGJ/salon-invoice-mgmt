@php
    $money = fn ($n) => '₹' . number_format((float) $n, abs($n - round($n)) < 0.005 ? 0 : 2);
    $modes = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'other' => 'Other'];
    $salonName = \App\Models\Setting::get('salon_name');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Statement – {{ $report['date_label'] }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 2px 0; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; border-bottom: 1px solid #999; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; vertical-align: top; }
        th { background: #f0f0f0; text-align: left; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: bold; border-top: 1px solid #999; }
        .muted { color: #666; }
        .summary td { padding: 6px 8px; }
        .summary .big { font-size: 15px; font-weight: bold; }
        .kv td { border: 1px solid #ddd; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h1>{{ $salonName }}</h1>
                <div class="muted">Daily Statement</div>
            </td>
            <td class="num">
                <div class="big" style="font-size:15px;font-weight:bold;">{{ $report['date_label'] }}</div>
                <div class="muted">{{ $report['invoices_count'] }} invoices · {{ $report['customers_served'] }} customers</div>
            </td>
        </tr>
    </table>

    <h2>Summary</h2>
    <table class="summary kv">
        <tr>
            <td>Earnings</td><td class="num big">{{ $money($report['earnings']['total']) }}</td>
            <td>Expenses</td><td class="num big">{{ $money($report['expenses']['total']) }}</td>
        </tr>
        <tr>
            <td>Net</td><td class="num big">{{ $money($report['net']) }}</td>
            <td>Cash in hand</td><td class="num big">{{ $money($report['cash_in_hand']) }}</td>
        </tr>
    </table>

    <h2>By payment mode</h2>
    <table class="kv">
        <tr>
            <th></th>
            @foreach ($modes as $key => $label)<th class="num">{{ $label }}</th>@endforeach
        </tr>
        <tr>
            <td>Earnings</td>
            @foreach ($modes as $key => $label)<td class="num">{{ $money($report['earnings']['by_mode'][$key]) }}</td>@endforeach
        </tr>
        <tr>
            <td>Expenses</td>
            @foreach ($modes as $key => $label)<td class="num">{{ $money($report['expenses']['by_mode'][$key]) }}</td>@endforeach
        </tr>
    </table>

    <h2>Invoices</h2>
    @if (count($report['invoices']) === 0)
        <p class="muted">No invoices on this day.</p>
    @else
        <table>
            <tr><th>#</th><th>Customer</th><th>Staff</th><th>Payment</th><th class="num">Total</th></tr>
            @foreach ($report['invoices'] as $inv)
                <tr>
                    <td>{{ $inv['invoice_number'] }}</td>
                    <td>{{ $inv['customer_name'] }}</td>
                    <td>{{ $inv['staff_member'] ?? '—' }}</td>
                    <td>{{ $modes[$inv['payment_mode']] ?? $inv['payment_mode'] }}</td>
                    <td class="num">{{ $money($inv['total']) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="4">Total earnings</td><td class="num">{{ $money($report['earnings']['total']) }}</td></tr>
        </table>
    @endif

    @if (count($report['voided']) > 0)
        <h2>Voided (excluded from totals)</h2>
        <table>
            <tr><th>#</th><th>Customer</th><th>Reason</th><th class="num">Amount</th></tr>
            @foreach ($report['voided'] as $inv)
                <tr>
                    <td>{{ $inv['invoice_number'] }}</td>
                    <td>{{ $inv['customer_name'] }}</td>
                    <td>{{ $inv['void_reason'] ?? '' }}</td>
                    <td class="num">{{ $money($inv['total']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Expenses</h2>
    @if (count($report['expense_lines']) === 0)
        <p class="muted">No expenses on this day.</p>
    @else
        <table>
            <tr><th>Category</th><th>Description</th><th>Payment</th><th class="num">Amount</th></tr>
            @foreach ($report['expense_lines'] as $exp)
                <tr>
                    <td>{{ $exp['category'] }}</td>
                    <td>{{ $exp['description'] }}</td>
                    <td>{{ $modes[$exp['payment_mode']] ?? $exp['payment_mode'] }}</td>
                    <td class="num">{{ $money($exp['amount']) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="3">Total expenses</td><td class="num">{{ $money($report['expenses']['total']) }}</td></tr>
        </table>
    @endif

    <p class="muted" style="margin-top:24px;">Generated {{ now()->format('j M Y, g:i A') }} · {{ \App\Models\Setting::get('footer_text') }}</p>
</body>
</html>
