{{-- Shared invoice body: used by the public HTML page and the DomPDF template.
     Tables + inline styles only (DomPDF has no flexbox/grid). Expects $invoice, $salon. --}}
@php
    use App\Services\PdfRenderer;
    $isVoid = $invoice->isVoid();
    $discountLabel = $invoice->discount_type === 'percent'
        ? 'Discount ('.rtrim(rtrim(number_format((float) $invoice->discount_value, 2, '.', ''), '0'), '.').'%)'
        : 'Discount';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr>
        <td valign="top" style="width:60%;">
            @if($salon['logo_src'])
                <img src="{{ $salon['logo_src'] }}" alt="" style="height:56px;max-width:160px;margin-bottom:6px;">
            @endif
            <div style="font-size:20px;font-weight:bold;">{{ $salon['name'] }}</div>
            @if($salon['tagline'])<div style="font-size:12px;color:#555;">{{ $salon['tagline'] }}</div>@endif
            @if($salon['address'])<div style="font-size:11px;color:#555;margin-top:4px;">{!! nl2br(e($salon['address'])) !!}</div>@endif
            @if($salon['phone'])<div style="font-size:11px;color:#555;">Phone: {{ $salon['phone'] }}</div>@endif
        </td>
        <td valign="top" align="right" style="width:40%;">
            <div style="font-size:22px;font-weight:bold;letter-spacing:1px;color:{{ $isVoid ? '#b91c1c' : '#111' }};">{{ $isVoid ? 'VOID' : 'INVOICE' }}</div>
            <div style="font-size:13px;margin-top:4px;"><strong>{{ $invoice->invoice_number }}</strong></div>
            <div style="font-size:12px;color:#555;">{{ $invoice->invoice_date->format('j M Y') }}</div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:16px;border-top:1px solid #ddd;border-bottom:1px solid #ddd;">
    <tr>
        <td valign="top" style="padding:8px 0;font-size:12px;">
            <div style="color:#777;font-size:10px;text-transform:uppercase;letter-spacing:1px;">Billed to</div>
            <div style="font-size:14px;font-weight:bold;">{{ $invoice->customer->first_name ?: $invoice->customer->name }}</div>
            <div style="color:#555;">{{ $invoice->customer->phone_masked }}</div>
        </td>
        <td valign="top" align="right" style="padding:8px 0;font-size:12px;">
            @if($invoice->staffMember)
                <div style="color:#777;font-size:10px;text-transform:uppercase;letter-spacing:1px;">Served by</div>
                <div>{{ $invoice->staffMember->name }}</div>
            @endif
            <div style="color:#777;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-top:{{ $invoice->staffMember ? '6px' : '0' }};">Payment</div>
            <div>{{ strtoupper($invoice->payment_mode) }} &middot; {{ ucfirst($invoice->payment_status) }}</div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:12px;">
    <thead>
        <tr style="background:#f3f4f6;">
            <th align="left" style="border-bottom:1px solid #ddd;">Description</th>
            <th align="right" style="border-bottom:1px solid #ddd;width:50px;">Qty</th>
            <th align="right" style="border-bottom:1px solid #ddd;width:90px;">Rate</th>
            <th align="right" style="border-bottom:1px solid #ddd;width:100px;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td style="border-bottom:1px solid #eee;">{{ $item->description }}</td>
                <td align="right" style="border-bottom:1px solid #eee;">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                <td align="right" style="border-bottom:1px solid #eee;">{{ PdfRenderer::money($item->unit_price) }}</td>
                <td align="right" style="border-bottom:1px solid #eee;">{{ PdfRenderer::money($item->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table width="100%" cellpadding="4" cellspacing="0" style="border-collapse:collapse;margin-top:8px;font-size:12px;">
    <tr>
        <td style="width:55%;"></td>
        <td align="right" style="color:#555;">Subtotal</td>
        <td align="right" style="width:110px;">{{ PdfRenderer::money($invoice->subtotal) }}</td>
    </tr>
    @if((float) $invoice->discount_amount > 0)
        <tr>
            <td></td>
            <td align="right" style="color:#555;">{{ $discountLabel }}</td>
            <td align="right">- {{ PdfRenderer::money($invoice->discount_amount) }}</td>
        </tr>
    @endif
    @if((float) $invoice->tax_amount > 0)
        <tr>
            <td></td>
            <td align="right" style="color:#555;">GST ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2, '.', ''), '0'), '.') }}%)</td>
            <td align="right">{{ PdfRenderer::money($invoice->tax_amount) }}</td>
        </tr>
    @endif
    @if(abs((float) $invoice->round_off) >= 0.005)
        <tr>
            <td></td>
            <td align="right" style="color:#555;">Round off</td>
            <td align="right">{{ (float) $invoice->round_off < 0 ? '- ' : '' }}{{ PdfRenderer::money(abs((float) $invoice->round_off)) }}</td>
        </tr>
    @endif
    <tr>
        <td></td>
        <td align="right" style="font-size:15px;font-weight:bold;border-top:2px solid #111;padding-top:6px;">Total</td>
        <td align="right" style="font-size:16px;font-weight:bold;border-top:2px solid #111;padding-top:6px;">{{ PdfRenderer::money($invoice->total) }}</td>
    </tr>
</table>

@if($isVoid)
    <div style="margin-top:14px;padding:8px 10px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;font-size:12px;">
        This invoice was voided{{ $invoice->void_reason ? ': '.$invoice->void_reason : '' }}.
    </div>
@endif

<div style="margin-top:22px;font-size:12px;text-align:center;color:#333;">Thank you for visiting {{ $salon['name'] }}!</div>
