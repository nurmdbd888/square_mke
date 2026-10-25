@extends('layouts.app')

@section('title', __('Purchase Orders'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <x-back-link :href="route('purchases.index')">{{ __('Back to purchases') }}</x-back-link>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Purchase Orders') }}</h1>
        </div>
        @if ($canManage)
            <a href="{{ route('purchases.orders.create') }}" class="btn-primary w-full sm:w-auto">{{ __('Create Purchase Order') }}</a>
        @endif
    </div>

    <form method="GET" action="{{ route('purchases.orders.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
        <label class="block min-w-[12rem] flex-1">
            <span class="label">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Order number or supplier') }}" class="input">
        </label>
        <button type="submit" class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <section class="card mt-4">
        <div class="table-wrap">
            <table class="data-table" data-testid="purchase-orders-table">
                <thead>
                    <tr>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Order date') }}</th>
                        <th>{{ __('Expected arrival') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Order total') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $total = $order->totalAmount();
                            $paid = $order->paidAmount();
                        @endphp
                        <tr data-testid="purchase-order-row-{{ $order->id }}">
                            <td class="font-mono">{{ $order->number }}</td>
                            <td>{{ $order->supplier?->name }}</td>
                            <td>{{ format_app_date($order->order_date) }}</td>
                            <td>{{ $order->expected_delivery_date ? format_app_date($order->expected_delivery_date) : '—' }}</td>
                            <td>
                                <span class="badge">{{ $order->statusLabel() }}</span>
                                <div class="mt-1 text-xs text-slate-500">{{ __('Paid') }}: {{ format_money($paid) }}</div>
                            </td>
                            <td class="text-right font-mono">{{ format_money($total) }}</td>
                            <td class="text-right">
                                <a href="{{ route('purchases.orders.show', $order) }}" class="btn-secondary px-3 py-1.5 text-xs min-h-10">{{ __('View order') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No purchase orders yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    </section>
@endsection
