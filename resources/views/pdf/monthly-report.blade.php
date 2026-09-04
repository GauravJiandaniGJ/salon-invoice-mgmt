@php
    $money = fn ($n) => '₹' . number_format((float) $n, abs($n - round($n)) < 0.005 ? 0 : 2);
    $modes = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'other' => 'Other'];
@endphp
@include('pdf.partials.report-head', ['title' => 'Monthly Report', 'subtitle' => $report['month_label'], 'meta' => $report['totals']['invoices_count'].' invoices'])

    <h2>Summary</h2>
    <table class="summary kv">
        <tr>
            <td>Earnings</td><td class="num big">{{ $money($report['totals']['earnings']) }}</td>
            <td>Expenses</td><td class="num big">{{ $money($report['totals']['expenses']) }}</td>
            <td>Net</td><td class="num big">{{ $money($report['totals']['net']) }}</td>
        </tr>
    </table>

    <h2>By payment mode</h2>
    <table class="kv">
        <tr><th></th>@foreach ($modes as $key => $label)<th class="num">{{ $label }}</th>@endforeach</tr>
        <tr><td>Earnings</td>@foreach ($modes as $key => $label)<td class="num">{{ $money($report['earnings_by_mode'][$key]) }}</td>@endforeach</tr>
        <tr><td>Expenses</td>@foreach ($modes as $key => $label)<td class="num">{{ $money($report['expenses_by_mode'][$key]) }}</td>@endforeach</tr>
    </table>

    <h2>Day by day</h2>
    <table>
        <tr><th>Date</th><th class="num">Invoices</th><th class="num">Earnings</th><th class="num">Expenses</th><th class="num">Net</th></tr>
        @foreach ($report['days'] as $d)
            @if ($d['invoices_count'] > 0 || $d['expenses'] > 0)
            <tr class="row">
                <td>{{ \Carbon\CarbonImmutable::parse($d['date'])->format('D, j M') }}</td>
                <td class="num">{{ $d['invoices_count'] }}</td>
                <td class="num">{{ $money($d['earnings']) }}</td>
                <td class="num">{{ $money($d['expenses']) }}</td>
                <td class="num">{{ $money($d['net']) }}</td>
            </tr>
            @endif
        @endforeach
        <tr class="total"><td>Total</td><td class="num">{{ $report['totals']['invoices_count'] }}</td><td class="num">{{ $money($report['totals']['earnings']) }}</td><td class="num">{{ $money($report['totals']['expenses']) }}</td><td class="num">{{ $money($report['totals']['net']) }}</td></tr>
    </table>

    <h2>Top services</h2>
    @if (count($report['top_services']) === 0)
        <p class="muted">No services billed this month.</p>
    @else
        <table>
            <tr><th>Service</th><th class="num">Times</th><th class="num">Revenue</th></tr>
            @foreach ($report['top_services'] as $svc)
                <tr class="row"><td>{{ $svc['description'] }}</td><td class="num">{{ $svc['count'] }}</td><td class="num">{{ $money($svc['revenue']) }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>By staff</h2>
    @include('pdf.partials.staff-table', ['rows' => $report['by_staff'], 'totals' => null])

@include('pdf.partials.report-foot')
