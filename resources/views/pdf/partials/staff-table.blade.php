@if (count($rows) === 0)
    <p class="muted">No services in this period.</p>
@else
    <table>
        <tr><th>Staff</th><th class="num">Services</th><th class="num">Invoices</th><th class="num">Revenue</th><th class="num">Avg ticket</th><th class="num">Commission</th></tr>
        @foreach ($rows as $r)
            <tr class="row">
                <td>{{ $r['name'] }}</td>
                <td class="num">{{ $r['services_count'] }}</td>
                <td class="num">{{ $r['invoices_count'] }}</td>
                <td class="num">{{ $money($r['revenue']) }}</td>
                <td class="num">{{ $money($r['average_ticket']) }}</td>
                <td class="num">{{ $r['commission_percent'] > 0 ? $money($r['commission']).' ('.rtrim(rtrim(number_format($r['commission_percent'], 2), '0'), '.').'%)' : '—' }}</td>
            </tr>
        @endforeach
        @if(!empty($totals))
            <tr class="total"><td>Total</td><td class="num">{{ $totals['services_count'] }}</td><td class="num">{{ $totals['invoices_count'] }}</td><td class="num">{{ $money($totals['revenue']) }}</td><td></td><td class="num">{{ $money($totals['commission']) }}</td></tr>
        @endif
    </table>
@endif
