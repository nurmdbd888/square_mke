@extends('layouts.app')

@section('title', __('TDS / loading / combo'))

@section('content')
    <x-back-link :href="route('reports.index')">{{ __('Back to reports') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('TDS / loading / combo') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Sums of invoice header adjustments for posted sales.') }}</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="label">{{ __('From') }}</span>
            <x-date-input name="from" :value="$from" />
        </label>
        <label class="block">
            <span class="label">{{ __('To') }}</span>
            <x-date-input name="to" :value="$to" />
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="invoice-adjustments-summary">
        <div class="card">
            <p class="text-xs font-medium uppercase text-slate-500">{{ __('Posted invoices') }}</p>
            <p class="mt-1 font-mono text-lg font-semibold">{{ local_digits($summary['invoice_count']) }}</p>
        </div>
        <div class="card">
            <p class="text-xs font-medium uppercase text-slate-500">{{ __('TDS') }}</p>
            <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($summary['tds_amount']) }}</p>
        </div>
        <div class="card">
            <p class="text-xs font-medium uppercase text-slate-500">{{ __('Loading Adjustment') }}</p>
            <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($summary['loading_adjustment']) }}</p>
        </div>
        <div class="card">
            <p class="text-xs font-medium uppercase text-slate-500">{{ __('Combo Discount') }}</p>
            <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($summary['combo_bonus_discount']) }}</p>
        </div>
    </section>
    <p class="mt-4 text-sm text-slate-600">
        {{ __('Total adjustments') }}:
        <span class="font-mono font-semibold">{{ format_money($summary['total_adjustments']) }}</span>
    </p>
@endsection
