@extends('layouts.app')

@section('title', __('Collections'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Collections') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('All posted sales invoices and their payment status.') }}</p>
    @if (! empty($warning))
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{{ $warning }}</div>
    @endif

    <section class="card mt-6">
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <h2 class="font-semibold text-slate-900">{{ __('Sales invoices') }}</h2>
            <form method="GET" action="{{ route('collector.index') }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-end" data-testid="collector-filters">
                <label class="block min-w-[10rem] flex-1">
                    <span class="label">{{ __('Search') }}</span>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Invoice or retailer') }}" class="input min-h-11">
                </label>
                <label class="block min-w-[8rem]">
                    <span class="label">{{ __('Payment status') }}</span>
                    <select name="status" class="input min-h-11">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('All statuses') }}</option>
                        <option value="due" @selected(($filters['status'] ?? '') === 'due')>{{ __('Due') }}</option>
                        <option value="partial" @selected(($filters['status'] ?? '') === 'partial')>{{ __('Partially paid') }}</option>
                        <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>{{ __('Paid') }}</option>
                    </select>
                </label>
                <label class="block min-w-[8rem]">
                    <span class="label">{{ __('Sort by') }}</span>
                    <select name="sort" class="input min-h-11">
                        <option value="date" @selected(($filters['sort'] ?? 'date') === 'date')>{{ __('Invoice date') }}</option>
                        <option value="invoice" @selected(($filters['sort'] ?? '') === 'invoice')>{{ __('Invoice number') }}</option>
                        <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>{{ __('Amount due') }}</option>
                    </select>
                </label>
                <label class="block min-w-[8rem]">
                    <span class="label">{{ __('Order') }}</span>
                    <select name="direction" class="input min-h-11">
                        <option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>{{ __('Oldest / lowest first') }}</option>
                        <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>{{ __('Newest / highest first') }}</option>
                    </select>
                </label>
                <label class="block min-w-[9rem]">
                    <span class="label">{{ __('From date') }}</span>
                    <x-date-input name="from" :value="$filters['from'] ?? ''" />
                </label>
                <label class="block min-w-[9rem]">
                    <span class="label">{{ __('To date') }}</span>
                    <x-date-input name="to" :value="$filters['to'] ?? ''" />
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
                        <th>{{ __('Payment status') }}</th>
                        <th>{{ __('Collection confirmation') }}</th>
                        <th class="text-right">{{ __('Amount due') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $invoice)
                        @php($remaining = $collection->remainingAmount($invoice))
                        @php($paymentStatus = $remaining->isPositive() ? ($remaining->isLessThan($invoice->net_total) ? 'partial' : 'due') : 'paid')
                        @php($collectionConfirmed = $invoice->collection_status === \App\Models\Document::COLLECTION_COMPLETED)
                        <tr data-testid="collector-row-{{ $invoice->id }}">
                            <td class="font-mono">
                                @if ($remaining->isPositive())
                                    <a class="text-brand-700 underline" href="{{ route('collector.show', $invoice) }}">{{ $invoice->number }}</a>
                                @else
                                    {{ $invoice->number }}
                                @endif
                                @if ($collection->isOverdue($invoice))
                                    <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800" data-testid="collector-overdue-badge">{{ __('Overdue') }}</span>
                                @endif
                            </td>
                            <td>{{ format_app_date($invoice->doc_date) }}</td>
                            <td>{{ $invoice->retailer?->name }}</td>
                            <td>
                                <span data-testid="collector-payment-status-{{ $invoice->id }}">
                                    {{ match ($paymentStatus) {
                                        'partial' => __('Partially paid'),
                                        'paid' => __('Paid'),
                                        default => __('Due'),
                                    } }}
                                </span>
                            </td>
                            <td>
                                <span data-testid="collector-confirmation-status-{{ $invoice->id }}">
                                    {{ $collectionConfirmed ? __('Confirmed') : __('Pending') }}
                                </span>
                            </td>
                            <td class="text-right font-mono">{{ format_money($remaining) }}</td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($remaining->isPositive())
                                        <form method="POST" action="{{ route('collector.paid', $invoice) }}">
                                            @csrf
                                            <button class="btn-secondary px-3 py-1.5 text-xs min-h-10" data-confirm="{{ __('Record full cash payment for this invoice?') }}" data-testid="collector-paid-{{ $invoice->id }}">{{ __('PAID') }}</button>
                                        </form>
                                        <a href="{{ route('collector.show', $invoice) }}" class="btn-primary px-3 py-1.5 text-xs min-h-10" data-testid="collector-collect-{{ $invoice->id }}">{{ __('Open invoice') }}</a>
                                    @elseif (! $collectionConfirmed && $collection->columnsReady())
                                        <form method="POST" action="{{ route('sales.collection.confirm', $invoice) }}">
                                            @csrf
                                            <button class="btn-secondary px-3 py-1.5 text-xs min-h-10" data-testid="collector-confirm-{{ $invoice->id }}">{{ __('Confirm collection') }}</button>
                                        </form>
                                    @else
                                        <span class="text-sm text-slate-500">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No invoices match the selected filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $pending->links() }}
    </section>
@endsection
