@extends('layouts.app')

@section('title', __('Payments'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Payments') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Supplier payments against purchase invoices.') }}</p>
        </div>
        @if ($canManage)
            <a href="{{ route('payments.create', ['kind' => 'payment']) }}" class="btn-secondary">{{ __('Record other expense') }}</a>
        @endif
    </div>

    <form method="GET" action="{{ route('payments.index') }}" class="mt-4 flex flex-wrap items-end gap-3" data-testid="payments-filters">
        <label class="block min-w-[12rem] flex-1">
            <span class="label">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Invoice or supplier') }}" class="input">
        </label>
        <label class="block">
            <span class="label">{{ __('Payment status') }}</span>
            <select name="status" class="input">
                <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('All') }}</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Draft') }}</option>
                <option value="posted" @selected(($filters['status'] ?? '') === 'posted')>{{ __('Posted') }}</option>
                <option value="void" @selected(($filters['status'] ?? '') === 'void')>{{ __('Void') }}</option>
            </select>
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <section class="mt-6" data-testid="purchase-invoices-due">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('Purchase invoices due') }}</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Purchase invoice') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th class="text-right">{{ __('Invoice total') }}</th>
                        <th class="text-right">{{ __('Paid') }}</th>
                        <th class="text-right">{{ __('Amount due') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseInvoices as $invoice)
                        <tr>
                            <td class="font-mono">{{ $invoice->number }}</td>
                            <td>{{ format_app_date($invoice->doc_date) }}</td>
                            <td>{{ $invoice->party_name }}</td>
                            <td class="text-right font-mono">{{ format_money($invoice->net_total) }}</td>
                            <td class="text-right font-mono">{{ format_money($invoice->allocated) }}</td>
                            <td class="text-right font-mono font-semibold">{{ format_money($invoice->remaining) }}</td>
                            <td class="text-right">
                                @if ($canManage)
                                    <a href="{{ route('payments.create', ['kind' => 'payment', 'supplier_id' => $invoice->party_id, 'document_id' => $invoice->document_id]) }}"
                                       class="btn-primary px-3 py-1.5 text-xs min-h-10"
                                       data-testid="supplier-pay-{{ $invoice->document_id }}">{{ __('Record payment') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No purchase invoices are due.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-8" data-testid="outgoing-payment-records">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('Recorded payments and expenses') }}</h2>
        <div id="payments-table">
            @include('payments._table')
        </div>
    </section>
@endsection
