@extends('print.layout')

@php
    $pageSize = $printSize ?? (setting('default_print_size') ?: 'a5');
@endphp

@push('print-styles')
    <style>
        @page {
            size: {{ $pageSize === 'a4' ? 'A4' : '148mm 210mm' }};
            margin: 10mm;
        }
        .brand-bar {
            background: linear-gradient(135deg, #1666cc 0%, #0f2f5c 100%);
            color: #fff;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 14px;
        }
        .brand-bar .firm { font-size: {{ $pageSize === 'a4' ? '22px' : '18px' }}; font-weight: 700; margin: 0; }
        .brand-bar .tagline { opacity: 0.9; font-size: 11px; margin-top: 4px; }
        .doc-badge {
            display: inline-block;
            background: #eef7ff;
            color: #1352a5;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .party-box {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 12px;
            margin-top: 12px;
            background: #f8fafc;
        }
        table.lines th { background: #1352a5; color: #fff; border-color: #1352a5; }
        .collection-pill {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            background: #fef3c7;
            color: #92400e;
        }
    </style>
@endpush

@section('title', $docTitle.' '.$document->number)

@section('content')
    <div class="brand-bar header-grid">
        <div>
            <p class="firm">{{ $firmName }}</p>
            <p class="tagline">{{ __('Square distributor') }} · {{ local_digits($distributorCode) }}</p>
        </div>
        <div>{!! $qrSvg !!}</div>
    </div>

    <div class="header-grid">
        <div>
            <span class="doc-badge">{{ $docTitle }}</span>
            <p style="margin: 8px 0 0; font-size: 15px;" class="mono"><strong>{{ $document->number }}</strong></p>
            <p class="meta" style="margin: 4px 0 0;">
                {{ __('Date') }}: {{ format_app_date($document->doc_date) }}
                @if ($document->delivery_date)
                    · {{ __('Delivery') }}: {{ format_app_date($document->delivery_date) }}
                @endif
            </p>
            @if ($document->isSale() && $document->collection_status)
                <span class="collection-pill">{{ app(\App\Domain\Payments\SaleCollectionStatus::class)->label($document->collection_status) }}</span>
            @endif
        </div>
    </div>

    <div class="party-box">
        <strong>{{ $document->isSale() ? __('Bill to') : __('Supplier') }}</strong><br>
        {{ $document->partyLabel() }}
        @if ($document->isSale() && $document->retailer?->code)
            <span class="mono"> · {{ $document->retailer->code }}</span>
        @endif
        @if ($document->salesRep)
            <br><span class="meta">{{ __('Sales rep') }}: {{ $document->salesRep->name }}</span>
        @endif
    </div>

    @include('print.partials.lines')

    <div class="totals">
        <div><span>{{ __('Subtotal') }}</span><span class="mono">{{ format_money($document->subtotal) }}</span></div>
        @if ((float) $document->header_discount > 0)
            <div><span>{{ __('Header discount') }}</span><span class="mono">−{{ format_money($document->header_discount) }}</span></div>
        @endif
        @if ((float) $document->slab_adjustment > 0)
            <div><span>{{ __('Slab adjustment') }}</span><span class="mono">−{{ format_money($document->slab_adjustment) }}</span></div>
        @endif
        @if ((float) $document->combo_bonus_discount > 0)
            <div><span>{{ __('Combo bonus discount') }}</span><span class="mono">−{{ format_money($document->combo_bonus_discount) }}</span></div>
        @endif
        @if ((float) $document->rounding_adjustment != 0)
            <div><span>{{ __('Rounding') }}</span><span class="mono">{{ format_money($document->rounding_adjustment) }}</span></div>
        @endif
        <div class="net"><span>{{ __('Net total') }}</span><span class="mono">{{ format_money($document->net_total) }}</span></div>
    </div>

    @if ($document->notes)
        <p class="meta" style="margin-top: 16px;"><strong>{{ __('Notes') }}:</strong> {{ $document->notes }}</p>
    @endif

    <p class="meta" style="margin-top: 24px; text-align: center; font-size: 10px;">{{ __('Thank you for your business.') }} · {{ $firmName }}</p>
@endsection
