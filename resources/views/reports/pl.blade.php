@extends('layouts.app')

@section('title', __('Profit and loss'))

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Profit and loss') }}</h1>
        <div class="flex flex-wrap items-center gap-2">
            @include('export.partials.actions', ['exportKey' => 'reports_pl'])
            <a href="{{ route('reports.month-locks') }}" class="btn-secondary">{{ __('Month locks') }}</a>
        </div>
    </div>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="label">{{ __('From') }}</span>
            <input type="date" name="from" value="{{ $from }}" class="input">
        </label>
        <label class="block">
            <span class="label">{{ __('To') }}</span>
            <input type="date" name="to" value="{{ $to }}" class="input">
        </label>
        @include('reports.partials.business-days-filter', ['businessDaysOnly' => $businessDaysOnly ?? false])
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>
    @if ($businessDaysOnly ?? false)
        <p class="mt-2 text-sm text-brand-700" data-testid="business-days-active">{{ __('Totals include business days only (weekly holidays excluded).') }}</p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="pl-summary">
        @foreach ([
            __('Revenue') => $figures['revenue'],
            __('COGS') => $figures['cogs'],
            __('Free goods cost') => $figures['free_goods'],
            __('Gross profit') => $figures['gross'],
            __('Commission (net)') => $figures['commission_net'],
            __('Profit before expenses') => $figures['profit_before_expenses'],
            __('Operating expenses') => $figures['expenses'],
            __('Stock loss') => $figures['stock_loss'],
            __('Net profit') => $figures['net_profit'],
        ] as $label => $value)
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ $label }}</p>
                <p class="mt-1 font-mono text-lg font-semibold text-slate-900">{{ format_money($value) }}</p>
            </div>
        @endforeach
    </div>

    <p class="mt-4 text-sm text-slate-600" data-testid="pl-reconcile">
        {{ __('Posted sales revenue in the ledger') }}: <span class="font-mono">{{ format_money($ledgerRevenue) }}</span>
    </p>
@endsection
