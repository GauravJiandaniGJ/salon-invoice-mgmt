@php
    $money = fn ($n) => '₹' . number_format((float) $n, abs($n - round($n)) < 0.005 ? 0 : 2);
    $modes = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'other' => 'Other'];
@endphp
@php $label = $report['from'] === $report['to'] ? \Carbon\CarbonImmutable::parse($report['from'])->format('D, j M Y') : \Carbon\CarbonImmutable::parse($report['from'])->format('j M Y').' – '.\Carbon\CarbonImmutable::parse($report['to'])->format('j M Y'); @endphp
@include('pdf.partials.report-head', ['title' => 'Staff Report', 'subtitle' => $label, 'meta' => $report['totals']['invoices_count'].' invoices · '.$money($report['totals']['revenue']).' revenue'])

    <h2>Revenue by staff member</h2>
    <p class="muted" style="margin:0 0 6px 0;">Service revenue is the sum of each staff member's service lines, before bill-level discounts. Commission = revenue × commission %.</p>
    @include('pdf.partials.staff-table', ['rows' => $report['rows'], 'totals' => $report['totals']])

@include('pdf.partials.report-foot')
