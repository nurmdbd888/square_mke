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
        @if ($document->isPurchase())
            {{ __('Supplier') }}: <strong>{{ $document->supplier?->name }}</strong>
        @else
            {{ __('Retailer') }}: <strong>{{ $document->retailer?->name }}</strong>
            @if ($document->route)
                · {{ __('Route') }}: {{ $document->route->name }}
            @endif
            @if ($document->vehicle)
                · {{ __('Vehicle') }}: {{ $document->vehicle->registration }}
            @endif
        @endif
    </div>

    @include('print.partials.lines')

    <div class="totals">
        <div class="net"><span>{{ __('Net total') }}</span><span class="mono">{{ format_money($document->net_total) }}</span></div>
    </div>
@endsection
