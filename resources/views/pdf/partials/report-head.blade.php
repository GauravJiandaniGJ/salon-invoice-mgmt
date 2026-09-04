@php $salonName = \App\Models\Setting::get('salon_name'); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} – {{ $subtitle }}</title>
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
        .big { font-size: 15px; font-weight: bold; }
        .kv td { border: 1px solid #ddd; }
        tr.row td { border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h1>{{ $salonName }}</h1>
                <div class="muted">{{ $title }}</div>
            </td>
            <td class="num">
                <div class="big">{{ $subtitle }}</div>
                @if(!empty($meta))<div class="muted">{{ $meta }}</div>@endif
            </td>
        </tr>
    </table>
