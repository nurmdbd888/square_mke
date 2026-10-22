@extends('layouts.app')

@section('title', __('Collections'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Collections') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Pending sales invoices and confirmations for field collectors.') }}</p>
    @if (! empty($warning))
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{{ $warning }}</div>
    @endif

    <section class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="collector-daily-summary">
        <div class="card">
            <h2 class="text-sm font-medium text-slate-600">{{ __('Today\'s posted receipts') }}</h2>
            <p class="mt-2 font-mono text-2xl font-semibold text-brand-700">{{ format_money($summary['receipt_total'] ?? $todayTotal) }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __(':count receipts today', ['count' => local_digits($summary['receipt_count'] ?? 0)]) }}</p>
            <a href="{{ route('payments.index', ['kind' => 'receipt', 'status' => 'posted']) }}" class="mt-2 inline-block text-sm text-brand-700 underline">{{ __('View receipts') }}</a>
        </div>
        <div class="card">
            <h2 class="text-sm font-medium text-slate-600">{{ __('Confirmed today') }}</h2>
            <p class="mt-2 font-mono text-2xl font-semibold text-emerald-700">{{ local_digits($summary['confirmed_today'] ?? 0) }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('Marked completed') }}</p>
        </div>
        <div class="card">
            <h2 class="text-sm font-medium text-slate-600">{{ __('Ready to confirm') }}</h2>
            <p class="mt-2 font-mono text-2xl font-semibold text-amber-800">{{ local_digits($summary['ready_count'] ?? 0) }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('Paid in full — confirm') }}</p>
        </div>
        <div class="card" @class(['ring-2 ring-red-200' => ($summary['overdue_count'] ?? 0) > 0])>
            <h2 class="text-sm font-medium text-slate-600">{{ __('Overdue') }}</h2>
            <p class="mt-2 font-mono text-2xl font-semibold text-red-700">{{ local_digits($summary['overdue_count'] ?? 0) }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('Older than :days days', ['days' => local_digits($dueDays)]) }}</p>
            @if (($summary['partial_open'] ?? 0) > 0)
                <p class="mt-1 text-xs text-amber-800">{{ __(':count partially due', ['count' => local_digits($summary['partial_open'])]) }}</p>
            @endif
        </div>
    </section>

    <section class="card mt-6">
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <h2 class="font-semibold text-slate-900">{{ __('Pending invoices') }}</h2>
            <form method="GET" action="{{ route('collector.index') }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-end" data-testid="collector-filters">
                <label class="block min-w-[10rem] flex-1">
                    <span class="label">{{ __('Search') }}</span>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Invoice or retailer') }}" class="input min-h-11">
                </label>
                <label class="block min-w-[8rem]">
                    <span class="label">{{ __('Status') }}</span>
                    <select name="status" class="input min-h-11">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('All') }}</option>
                        <option value="due" @selected(($filters['status'] ?? '') === 'due')>{{ __('Due') }}</option>
                        <option value="partial" @selected(($filters['status'] ?? '') === 'partial')>{{ __('Partially due') }}</option>
                        <option value="ready" @selected(($filters['status'] ?? '') === 'ready')>{{ __('Ready to confirm') }}</option>
                    </select>
                </label>
                <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm">
                    <input type="checkbox" name="overdue" value="1" class="rounded border-slate-300" @checked(! empty($filters['overdue']))>
                    <span>{{ __('Overdue only') }}</span>
                </label>
                <button class="btn-secondary min-h-11">{{ __('Filter') }}</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table" data-testid="collector-pending-table">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Retailer') }}</th>
                        <th class="text-right">{{ __('Due') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $invoice)
                        @php
                            $remaining = $collection->remainingAmount($invoice);
                            $readyConfirm = $remaining->isZero();
                            $isOverdue = ! $readyConfirm && $collection->isOverdue($invoice);
                        @endphp
                        <tr @class([
                            'bg-amber-50/60' => $readyConfirm,
                            'bg-red-50/40' => $isOverdue,
                        ]) data-testid="collector-row-{{ $invoice->id }}">
                            <td class="font-mono">{{ $invoice->number }}</td>
                            <td>{{ format_app_date($invoice->doc_date) }}</td>
                            <td>{{ $invoice->retailer?->name }}</td>
                            <td class="text-right font-mono">{{ format_money($remaining) }}</td>
                            <td>
                                <span @class([
                                    'rounded px-2 py-0.5 text-xs font-medium',
                                    'bg-red-50 text-red-800' => $invoice->collection_status === 'due' || $invoice->collection_status === null,
                                    'bg-amber-50 text-amber-900' => $invoice->collection_status === 'partial',
                                ])>{{ $collection->label($invoice->collection_status) }}</span>
                                @if ($isOverdue)
                                    <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800" data-testid="collector-overdue-badge">{{ __('Overdue') }}</span>
                                @endif
                                @if ($readyConfirm)
                                    <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-800">{{ __('Ready to confirm') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($remaining->isPositive())
                                        <a
                                            href="{{ route('payments.create', ['kind' => 'receipt', 'retailer_id' => $invoice->retailer_id, 'document_id' => $invoice->id]) }}"
                                            class="btn-primary px-3 py-1.5 text-xs min-h-10"
                                            data-testid="collector-collect-{{ $invoice->id }}"
                                        >{{ __('Collect') }}</a>
                                    @endif
                                    @if ($readyConfirm)
                                        <form method="POST" action="{{ route('sales.collection.confirm', $invoice) }}">
                                            @csrf
                                            <button type="submit" class="btn-secondary px-3 py-1.5 text-xs min-h-10" data-testid="collector-confirm-{{ $invoice->id }}">{{ __('Confirm paid') }}</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('sales.show', $invoice) }}" class="btn-secondary px-3 py-1.5 text-xs min-h-10">{{ __('View') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No pending collections.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
