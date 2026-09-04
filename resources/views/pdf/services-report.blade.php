@php
    $money = fn ($n) => '₹' . number_format((float) $n, abs($n - round($n)) < 0.005 ? 0 : 2);
    $modes = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'other' => 'Other'];
@endphp
@php $label = $report['from'] === $report['to'] ? \Carbon\CarbonImmutable::parse($report['from'])->format('D, j M Y') : \Carbon\CarbonImmutable::parse($report['from'])->format('j M Y').' – '.\Carbon\CarbonImmutable::parse($report['to'])->format('j M Y'); @endphp
@include('pdf.partials.report-head', ['title' => 'Services Report', 'subtitle' => $label, 'meta' => $report['totals']['count'].' services billed'])

    <h2>Service-wise revenue</h2>
    @if (count($report['rows']) === 0)
        <p class="muted">No services billed in this period.</p>
    @else
        <table>
            <tr><th>Service</th><th class="num">Times billed</th><th class="num">Qty</th><th class="num">Revenue</th></tr>
            @foreach ($report['rows'] as $r)
                <tr class="row">
                    <td>{{ $r['description'] }}@if($r['service_id'] === null) <span class="muted">(custom)</span>@endif</td>
                    <td class="num">{{ $r['count'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $r['quantity'], 2, '.', ''), '0'), '.') }}</td>
                    <td class="num">{{ $money($r['revenue']) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td>Total</td><td class="num">{{ $report['totals']['count'] }}</td><td></td><td class="num">{{ $money($report['totals']['revenue']) }}</td></tr>
        </table>
    @endif

@include('pdf.partials.report-foot')
