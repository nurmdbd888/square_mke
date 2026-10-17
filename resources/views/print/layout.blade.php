<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $firmName }}</title>
    <style>
        @font-face {
            font-family: 'Noto Sans Bengali';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('{{ asset('fonts/noto-sans-bengali-400.woff2') }}') format('woff2');
        }
        @font-face {
            font-family: 'Noto Sans Bengali';
            font-style: normal;
            font-weight: 600;
            font-display: swap;
            src: url('{{ asset('fonts/noto-sans-bengali-600.woff2') }}') format('woff2');
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, 'Noto Sans Bengali', 'Hind Siliguri', sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        @media print {
            .no-print { display: none !important; }
            @page { margin: 12mm; }
        }
        .mono { font-family: ui-monospace, monospace; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border: 1px solid #cbd5e1; padding: 4px 6px; vertical-align: top; }
        table.lines th { background: #f1f5f9; text-align: left; font-weight: 600; }
        .text-right { text-align: right; }
        .header-grid { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
        .firm { font-size: 18px; font-weight: 600; }
        .meta { margin-top: 8px; color: #475569; }
        .totals { margin-top: 12px; max-width: 280px; margin-left: auto; }
        .totals div { display: flex; justify-content: space-between; gap: 12px; padding: 2px 0; }
        .totals .net { font-weight: 700; font-size: 14px; border-top: 1px solid #94a3b8; margin-top: 4px; padding-top: 6px; }
    </style>
    @stack('print-styles')
</head>
<body>
    <p class="no-print" style="margin: 0 0 12px;">
        <button type="button" onclick="window.print()" style="padding: 8px 14px; cursor: pointer;">{{ __('Print') }}</button>
        <a href="javascript:history.back()" style="margin-left: 8px;">{{ __('Back') }}</a>
    </p>
    @yield('content')
</body>
</html>
