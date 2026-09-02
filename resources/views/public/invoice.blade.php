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
        .btn { display: flex; align-items: center; justify-content: center; gap: 10px; background: #111; color: #fff; border: 1.5px solid #111; text-decoration: none; font-weight: 600; padding: 14px 20px; border-radius: 999px; margin-top: 16px; font-size: 16px; transition: background-color .3s ease, color .3s ease, transform .3s ease; }
        .btn svg { width: 20px; height: 20px; transition: transform .3s ease; }
        .btn:hover { background: #25D366; color: #111; }
        .btn:hover svg { transform: translateY(3px); }
        .btn:active { transform: scale(.98); }
        .footer { text-align: center; margin-top: 22px; }
        .powered { display: inline-flex; align-items: center; gap: 8px; font-size: 11px; color: #898A93; text-decoration: none; text-transform: uppercase; letter-spacing: .06em; }
        .powered img { height: 24px; width: auto; }
        .powered:hover { color: #565868; }
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

        <a class="btn" href="{{ $pdfUrl }}">
            <span>Download PDF</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
        </a>

        <div class="footer">
            <a href="{{ config('salon.powered_by.url') }}" target="_blank" rel="noopener" class="powered" title="{{ $salon['footer_text'] ?: config('salon.powered_by.label') }} · {{ parse_url(config('salon.powered_by.url'), PHP_URL_HOST) }}">
                <span>Powered by</span>
                <img src="{{ asset('brand/todoit-logo.png') }}" alt="{{ config('salon.powered_by.name') }}">
            </a>
        </div>
    </div>
</body>
</html>
