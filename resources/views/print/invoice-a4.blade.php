@extends('print.layout')

@section('title', $docTitle.' '.$document->number)

@section('content')
    <div class="header-grid">
        <div>
            <div class="firm">{{ $firmName }}</div>
            <div class="meta">{{ __('Square distributor') }}: {{ local_digits($distributorCode) }}</div>
            <h1 style="margin: 12px 0 0; font-size: 16px;">{{ $docTitle }}</h1>
        </div>
        <div>{!! $qrSvg !!}</div>
    </div>

    <div class="meta" style="margin-top: 16px;">
        <strong class="mono">{{ $document->number }}</strong>
        · {{ __('Date') }}: {{ local_digits($document->doc_date->format('d-m-Y')) }}
        @if ($document->delivery_date)
            · {{ __('Delivery') }}: {{ local_digits($document->delivery_date->format('d-m-Y')) }}
        @endif
        <br>
        {{ __('Retailer') }}: <strong>{{ $document->retailer?->name }}</strong>
        @if ($document->retailer?->code)
            ({{ $document->retailer->code }})
        @endif
        @if ($document->salesRep)
            · {{ __('Sales rep') }}: {{ $document->salesRep->name }}
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
@endsection
