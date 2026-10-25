@extends('layouts.app')

@section('title', __('Purchase Order :number', ['number' => $order->number]))

@section('content')
    <x-back-link :href="route('purchases.orders.index')">{{ __('Back to Purchase Orders') }}</x-back-link>
    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Purchase Order') }} <span class="font-mono">{{ $order->number }}</span></h1>
            <p class="mt-1 text-sm text-slate-600">{{ $order->supplier?->name }} · {{ format_app_date($order->order_date) }}</p>
        </div>
        @if ($canManage && ! $order->isFullyReceived())
            <a href="{{ route('purchases.orders.receive', $order) }}" class="btn-primary w-full sm:w-auto">{{ __('Receive delivery') }}</a>
        @endif
    </div>

    <section class="card mt-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-slate-900">{{ __('Order summary') }}</h2>
            <span class="badge" data-testid="purchase-order-status">{{ $order->statusLabel() }}</span>
        </div>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs uppercase text-slate-500">{{ __('Supplier') }}</dt><dd class="mt-1 font-medium">{{ $order->supplier?->name }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">{{ __('Expected arrival') }}</dt><dd class="mt-1">{{ $order->expected_delivery_date ? format_app_date($order->expected_delivery_date) : '—' }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">{{ __('Order total') }}</dt><dd class="mt-1 font-mono font-semibold">{{ format_money($order->totalAmount()) }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">{{ __('Paid') }}</dt><dd class="mt-1 font-mono font-semibold">{{ format_money($order->paidAmount()) }} <span class="font-sans font-normal text-slate-500">· {{ __('Due') }} {{ format_money($order->remainingPayment()) }}</span></dd></div>
        </dl>
        @if ($order->notes)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-600">{{ $order->notes }}</p>
        @endif
    </section>

    <section class="card mt-5">
        <h2 class="mb-3 font-semibold text-slate-900">{{ __('Ordered products') }}</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('Ordered') }}</th><th class="text-right">{{ __('Received') }}</th><th class="text-right">{{ __('Still due') }}</th><th class="text-right">{{ __('Unit price') }}</th><th class="text-right">{{ __('Line total') }}</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        @php
                            $remainingQty = \App\Domain\Money\Exact::of($line->qty)->minus(\App\Domain\Money\Exact::of($line->received_qty));
                            $lineTotal = \App\Domain\Money\Exact::of($line->qty)->multipliedBy(\App\Domain\Money\Exact::of($line->unit_price));
                        @endphp
                        <tr>
                            <td>{{ $line->product?->name_en ?? __('Product no longer available') }}</td>
                            <td class="text-right font-mono">{{ format_number($line->qty) }}</td>
                            <td class="text-right font-mono">{{ format_number($line->received_qty) }}</td>
                            <td class="text-right font-mono">{{ format_number($remainingQty) }}</td>
                            <td class="text-right font-mono">{{ format_money($line->unit_price) }}</td>
                            <td class="text-right font-mono">{{ format_money($lineTotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage && \App\Domain\Money\Exact::of($order->remainingPayment())->isPositive())
        <section class="card mt-5" x-data="{ method: {{ \Illuminate\Support\Js::from(old('method', 'cash')) }} }">
            <h2 class="font-semibold text-slate-900">{{ __('Record supplier payment') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('A payment is recorded as a supplier advance and applied to purchase invoices as deliveries are posted.') }}</p>
            <form method="POST" action="{{ route('purchases.orders.payments.store', $order) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <label class="block">
                    <span class="label">{{ __('Method') }}</span>
                    <select name="method" x-model="method" class="input min-h-11" required>
                        @foreach (\App\Models\Payment::METHODS as $method)
                            <option value="{{ $method }}">{{ __($method) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Payment date') }}</span>
                    <x-date-input name="payment_date" :value="old('payment_date', now()->toDateString())" required />
                </label>
                <label class="block">
                    <span class="label">{{ __('Amount') }} · {{ __('Maximum') }} {{ format_money($order->remainingPayment()) }}</span>
                    <input type="number" name="amount" min="0.01" max="{{ $order->remainingPayment() }}" step="0.01" value="{{ old('amount') }}" class="input min-h-11 font-mono" required>
                </label>
                <label class="block" x-show="method === 'pdc'" x-cloak>
                    <span class="label">{{ __('Cheque due date') }}</span>
                    <x-date-input name="due_date" :value="old('due_date')" ::required="method === 'pdc'" />
                </label>
                <label class="block">
                    <span class="label">{{ __('Reference') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="reference" maxlength="120" value="{{ old('reference') }}" class="input min-h-11">
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Notes') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="notes" maxlength="2000" value="{{ old('notes') }}" class="input min-h-11">
                </label>
                <div class="sm:col-span-2 sm:text-right"><button type="submit" class="btn-primary">{{ __('Post payment') }}</button></div>
            </form>
        </section>
    @endif

    <section class="card mt-5">
        <h2 class="mb-3 font-semibold text-slate-900">{{ __('Delivery history') }}</h2>
        @forelse ($order->receipts as $receipt)
            <div class="border-t border-slate-200 py-4 first:border-0 first:pt-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-mono font-semibold">{{ $receipt->number }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ format_app_date($receipt->received_on) }}
                            @if ($receipt->challan_no) · {{ __('Challan No.') }} {{ $receipt->challan_no }} @endif
                            @if ($receipt->vehicle_no) · {{ __('Vehicle No.') }} {{ $receipt->vehicle_no }} @endif
                        </p>
                    </div>
                    @if ($receipt->document)
                        <a href="{{ route('purchases.show', $receipt->document) }}" class="btn-secondary">{{ __('Purchase invoice') }} {{ $receipt->document->number }}</a>
                    @else
                        <span class="badge">{{ __('No goods accepted') }}</span>
                    @endif
                </div>
                <div class="table-wrap mt-3">
                    <table class="data-table">
                        <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Received') }}</th><th class="text-right">{{ __('Due') }}</th></tr></thead>
                        <tbody>
                            @foreach ($receipt->lines as $receiptLine)
                                <tr>
                                    <td>{{ $receiptLine->orderLine?->product?->name_en }}</td>
                                    <td>{{ $receiptLine->status === \App\Models\PurchaseOrderReceiptLine::STATUS_CONFIRMED ? __('CONFIRMED') : __('DUE') }}</td>
                                    <td class="text-right font-mono">{{ format_number($receiptLine->received_qty) }}</td>
                                    <td class="text-right font-mono">{{ format_number($receiptLine->due_qty) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($receipt->notes)<p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $receipt->notes }}</p>@endif
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('No deliveries have been recorded.') }}</p>
        @endforelse
    </section>

    <section class="card mt-5">
        <h2 class="mb-3 font-semibold text-slate-900">{{ __('Supplier payment history') }}</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>{{ __('Payment number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @php($postedPayments = $order->payments->where('status', \App\Models\Payment::STATUS_POSTED))
                    @forelse ($postedPayments as $payment)
                        <tr><td class="font-mono">{{ $payment->number }}</td><td>{{ format_app_date($payment->payment_date) }}</td><td>{{ __($payment->method) }}</td><td class="text-right font-mono">{{ format_money($payment->amount) }}</td><td>{{ $payment->isPdc() && $payment->clearing_status !== \App\Models\Payment::CLEARING_CLEARED ? __('Pending clearance') : __('Posted') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-5 text-center text-slate-500">{{ __('No supplier payments recorded against this order.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
