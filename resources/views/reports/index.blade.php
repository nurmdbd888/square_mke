@extends('layouts.app')

@section('title', __('Reports'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Reports') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Profit, party ledger, brand sales, and invoice adjustment summaries.') }}</p>

    <ul class="mt-6 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white" data-testid="reports-index">
        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <div>
                <p class="font-medium text-slate-900">{{ __('Profit and loss') }}</p>
                <p class="text-sm text-slate-600">{{ __('Revenue, COGS, margin and net profit for a date range.') }}</p>
            </div>
            <a href="{{ route('reports.pl') }}" class="btn-secondary">{{ __('Open') }}</a>
        </li>
        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <div>
                <p class="font-medium text-slate-900">{{ __('Party ledger') }}</p>
                <p class="text-sm text-slate-600">{{ __('Chronological invoices and payments with running balance.') }}</p>
            </div>
            <a href="{{ route('reports.party-ledger') }}" class="btn-secondary" data-testid="reports-party-ledger">{{ __('Open') }}</a>
        </li>
        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <div>
                <p class="font-medium text-slate-900">{{ __('Brand-wise sales') }}</p>
                <p class="text-sm text-slate-600">{{ __('Posted sale lines grouped by product brand.') }}</p>
            </div>
            <a href="{{ route('reports.brand-sales') }}" class="btn-secondary" data-testid="reports-brand-sales">{{ __('Open') }}</a>
        </li>
        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <div>
                <p class="font-medium text-slate-900">{{ __('TDS / loading / combo') }}</p>
                <p class="text-sm text-slate-600">{{ __('Sums of invoice header adjustments for posted sales.') }}</p>
            </div>
            <a href="{{ route('reports.invoice-adjustments') }}" class="btn-secondary" data-testid="reports-invoice-adjustments">{{ __('Open') }}</a>
        </li>
        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <div>
                <p class="font-medium text-slate-900">{{ __('Month locks') }}</p>
                <p class="text-sm text-slate-600">{{ __('Lock closed months against further posts.') }}</p>
            </div>
            <a href="{{ route('reports.month-locks') }}" class="btn-secondary">{{ __('Open') }}</a>
        </li>
    </ul>
@endsection
