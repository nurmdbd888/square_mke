@extends('print.layout')

@php
    $paidLines = $document->lines->reject(fn ($line) => $line->isComboBonus())->values();
    $bonusLines = $document->lines->filter(fn ($line) => $line->isComboBonus())->values();
@endphp

@push('print-styles')
    <style>
        body { max-width: 72mm; margin: 0 auto; font-size: 11px; }
        @page { size: 80mm auto; margin: 4mm; }
        .firm { font-size: 14px; text-align: center; }
        .center { text-align: center; }
        table.lines th, table.lines td { padding: 2px 3px; font-size: 10px; }
        .qr-wrap { text-align: center; margin: 8px 0; }
        .qr-wrap svg { max-width: 88px; height: auto; }
        .section-title { margin: 8px 0 4px; font-weight: 700; font-size: 11px; }
    </style>
@endpush

@section('title', $document->number)

@section('content')
    <div class="firm">{{ $firmName }}</div>
    <p class="center meta" style="margin: 4px 0;">{{ __('Square distributor') }} {{ local_digits($distributorCode) }}</p>
    <p class="center" style="margin: 8px 0;"><strong>{{ $docTitle }}</strong></p>
    <p class="center mono">{{ $document->number }}</p>
    <p class="center">{{ format_app_date($document->doc_date) }}</p>
    <p>{{ $document->retailer?->name }}</p>

    <div class="qr-wrap">{!! $qrSvg !!}</div>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('SL. NO.') }}</th>
                <th>{{ __('Product') }}</th>
                <th class="text-right">{{ __('Qty') }}</th>
                <th class="text-right">{{ __('Value') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($paidLines as $index => $line)
                <tr>
                    <td class="mono">{{ $index + 1 }}</td>
                    <td>
                        {{ $line->product?->name_en }}
                        @if ($line->product?->pack_size)
                            <span class="meta" style="display:block;font-size:9px;">{{ __('Pack') }}: {{ $line->product->pack_size }}</span>
                        @endif
                    </td>
                    <td class="text-right mono">{{ local_digits($line->qty) }}</td>
                    <td class="text-right mono">{{ format_money($line->line_value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($bonusLines->isNotEmpty())
        <p class="section-title">{{ __('Invoice Combo Bonus') }} :</p>
        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('SL. NO.') }}</th>
                    <th>{{ __('Product') }}</th>
                    <th class="text-right">{{ __('Qty') }}</th>
                    <th class="text-right">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bonusLines as $index => $line)
                    <tr>
                        <td class="mono">{{ $index + 1 }}</td>
                        <td>
                            {{ $line->product?->name_en }}
                            @if ($line->product?->pack_size)
                                <span class="meta" style="display:block;font-size:9px;">{{ __('Pack') }}: {{ $line->product->pack_size }}</span>
                            @endif
                        </td>
                        <td class="text-right mono">{{ local_digits($line->qty) }}</td>
                        <td class="text-right mono">{{ format_money(0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="totals" style="max-width: none;">
        @if ((float) ($document->tds_amount ?? 0) != 0.0 || (float) $document->slab_adjustment != 0.0 || (float) $document->combo_bonus_discount != 0.0)
            @if ((float) $document->slab_adjustment != 0.0)
                <div><span>{{ __('Loading Adjustment') }}</span><span class="mono">−{{ format_money($document->slab_adjustment) }}</span></div>
            @endif
            @if ((float) $document->combo_bonus_discount != 0.0)
                <div><span>{{ __('Combo Discount') }}</span><span class="mono">−{{ format_money($document->combo_bonus_discount) }}</span></div>
            @endif
            @if ((float) ($document->tds_amount ?? 0) != 0.0)
                <div><span>{{ __('Total TDS Amount') }}</span><span class="mono">+{{ format_money($document->tds_amount) }}</span></div>
            @endif
        @endif
        <div class="net"><span>{{ __('Total payable') }}</span><span class="mono">{{ format_money($document->net_total) }}</span></div>
    </div>
@endsection
