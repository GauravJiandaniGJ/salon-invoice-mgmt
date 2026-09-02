<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $salon['name'] }} – Invoice {{ $invoice->invoice_number }}</title>
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $salon['name'] }} – Invoice {{ $invoice->invoice_number }} – {{ App\Services\PdfRenderer::money($invoice->total) }}">
    <meta property="og:description" content="{{ $invoice->isVoid() ? 'This invoice has been voided.' : 'Your invoice from '.$salon['name'].' dated '.$invoice->invoice_date->format('j M Y').'. Tap to view or download the PDF.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ $salon['name'] }}">
    <meta property="og:image" content="{{ $salon['logo_url'] ?: asset('brand/wow-logo.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "DejaVu Sans", sans-serif; color: #111; -webkit-print-color-adjust: exact; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 16px 12px 40px; }
        .card { background: #fff; border-radius: 12px; padding: 20px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .void-banner { background: #b91c1c; color: #fff; text-align: center; font-weight: 700; letter-spacing: 2px; padding: 10px; border-radius: 10px; margin-bottom: 12px; }
        .btn { display: block; text-align: center; background: #111; color: #fff; text-decoration: none; font-weight: 600; padding: 14px; border-radius: 10px; margin-top: 16px; font-size: 16px; }
        .btn:active { opacity: .85; }
        .footer { text-align: center; color: #888; font-size: 12px; margin-top: 18px; }
        .footer a { color: #666; text-decoration: none; font-weight: 600; }
        .footer a:hover { text-decoration: underline; }
        table { width: 100%; }
        @media print {
            body { background: #fff; }
            .wrap { max-width: none; padding: 0; }
            .card { box-shadow: none; border-radius: 0; padding: 0; }
            .btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        @if($invoice->isVoid())
            <div class="void-banner">VOID</div>
        @endif

        <div class="card">
            @include('partials.invoice-body', ['invoice' => $invoice, 'salon' => $salon])
        </div>

        <a class="btn" href="{{ $pdfUrl }}">Download PDF</a>

        <div class="footer">
            @if($salon['footer_text'])
                <a href="{{ config('salon.powered_by.url') }}" target="_blank" rel="noopener">{{ $salon['footer_text'] }}</a>
                &middot;
            @endif
            <a href="{{ config('salon.powered_by.url') }}" target="_blank" rel="noopener">{{ parse_url(config('salon.powered_by.url'), PHP_URL_HOST) }}</a>
        </div>
    </div>
</body>
</html>
