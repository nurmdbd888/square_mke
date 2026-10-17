@extends('layouts.app')

@section('title', __('Collections'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Collections') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Pending sales invoices and confirmations for field collectors.') }}</p>

    <section class="card mt-6">
        <h2 class="font-semibold text-slate-900">{{ __('Today\'s posted receipts') }}</h2>
        <p class="mt-2 font-mono text-2xl font-semibold text-brand-700">{{ format_money($todayTotal) }}</p>
    </section>

    <section class="card mt-6">
        <h2 class="mb-3 font-semibold text-slate-900">{{ __('Pending invoices') }}</h2>
        <div class="table-wrap">
            <table class="data-table">
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
                        @endphp
                        <tr @class(['bg-amber-50/60' => $readyConfirm])>
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
                                @if ($readyConfirm)
                                    <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-800">{{ __('Ready to confirm') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($remaining->isPositive())
                                        <a href="{{ route('payments.create', ['kind' => 'receipt', 'retailer_id' => $invoice->retailer_id]) }}" class="btn-primary px-3 py-1.5 text-xs">{{ __('Collect') }}</a>
                                    @endif
                                    @if ($readyConfirm)
                                        <form method="POST" action="{{ route('sales.collection.confirm', $invoice) }}">
                                            @csrf
                                            <button type="submit" class="btn-secondary px-3 py-1.5 text-xs">{{ __('Confirm paid') }}</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('sales.show', $invoice) }}" class="btn-secondary px-3 py-1.5 text-xs">{{ __('View') }}</a>
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
