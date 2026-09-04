@php
    $money = fn ($n) => '₹' . number_format((float) $n, abs($n - round($n)) < 0.005 ? 0 : 2);
    $modes = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'other' => 'Other'];
@endphp
@include('pdf.partials.report-head', ['title' => $report['from'] === $report['to'] ? 'Daily Statement' : 'Statement', 'subtitle' => $report['date_label'], 'meta' => $report['invoices_count'].' invoices · '.$report['customers_served'].' customers'])

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
        <p class="muted">No invoices in this period.</p>
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
        <p class="muted">No expenses in this period.</p>
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

    <h2>By staff</h2>
    @include('pdf.partials.staff-table', ['rows' => $report['by_staff'], 'totals' => null])

@include('pdf.partials.report-foot')
