<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #111; margin: 0; }
        .watermark {
            position: fixed; top: 38%; left: 8%; width: 84%;
            text-align: center; font-size: 110px; font-weight: bold; color: #f3b4b4;
            transform: rotate(-28deg); z-index: -1;
        }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    @if($invoice->isVoid())
        <div class="watermark">VOID</div>
    @endif

    @include('partials.invoice-body', ['invoice' => $invoice, 'salon' => $salon])

    @if($salon['footer_text'])
        <div class="footer">{{ $salon['footer_text'] }}</div>
    @endif
</body>
</html>
