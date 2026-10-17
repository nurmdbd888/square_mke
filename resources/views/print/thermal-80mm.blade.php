@extends('print.layout')

@push('print-styles')
    <style>
        body { max-width: 72mm; margin: 0 auto; font-size: 11px; }
        @page { size: 80mm auto; margin: 4mm; }
        .firm { font-size: 14px; text-align: center; }
        .center { text-align: center; }
        table.lines th, table.lines td { padding: 2px 3px; font-size: 10px; }
        .qr-wrap { text-align: center; margin: 8px 0; }
        .qr-wrap svg { max-width: 88px; height: auto; }
    </style>
@endpush

@section('title', $document->number)

@section('content')
    <div class="firm">{{ $firmName }}</div>
    <p class="center meta" style="margin: 4px 0;">{{ __('Square distributor') }} {{ local_digits($distributorCode) }}</p>
    <p class="center" style="margin: 8px 0;"><strong>{{ $docTitle }}</strong></p>
    <p class="center mono">{{ $document->number }}</p>
    <p class="center">{{ local_digits($document->doc_date->format('d-m-Y')) }}</p>
    <p>{{ $document->retailer?->name }}</p>

    <div class="qr-wrap">{!! $qrSvg !!}</div>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('Product') }}</th>
                <th class="text-right">{{ __('Qty') }}</th>
                <th class="text-right">{{ __('Value') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->lines as $line)
                <tr>
                    <td>{{ $line->product?->name_en }}</td>
                    <td class="text-right mono">{{ local_digits($line->qty) }}</td>
                    <td class="text-right mono">{{ format_money($line->line_value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals" style="max-width: none;">
        <div class="net"><span>{{ __('Net total') }}</span><span class="mono">{{ format_money($document->net_total) }}</span></div>
    </div>
@endsection
