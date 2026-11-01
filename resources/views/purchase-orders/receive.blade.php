@extends('layouts.app')

@section('title', __('Receive Purchase Order :number', ['number' => $order->number]))

@section('content')
    <x-back-link :href="route('purchases.orders.show', $order)">{{ __('Back to purchase order') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Receive delivery') }} · <span class="font-mono">{{ $order->number }}</span></h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('For each outstanding product, confirm the delivery or enter how many pieces are still due. Only the received quantity is added to stock.') }}</p>

    <form method="POST" action="{{ route('purchases.orders.receive.store', $order) }}" class="mt-6 space-y-5">
        @csrf
        <section class="card grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="label">{{ __('Received on') }}</span>
                <x-date-input name="received_on" :value="$receivedOn" required />
            </label>
            <label class="block">
                <span class="label">{{ __('Challan No.') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <input type="text" name="challan_no" maxlength="80" value="{{ old('challan_no') }}" class="input min-h-11">
            </label>
            <label class="block">
                <span class="label">{{ __('Vehicle No.') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <input type="text" name="vehicle_no" maxlength="80" value="{{ old('vehicle_no') }}" class="input min-h-11">
            </label>
            <label class="block">
                <span class="label">{{ __('Notes') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <input type="text" name="notes" maxlength="2000" value="{{ old('notes') }}" class="input min-h-11">
            </label>
        </section>

        <section class="card">
            <div class="table-wrap">
                <table class="data-table min-w-[42rem]" data-testid="purchase-order-receive-lines">
                    <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('Ordered') }}</th><th class="text-right">{{ __('Received before') }}</th><th>{{ __('Delivery status') }}</th><th class="text-right">{{ __('Pieces due') }}</th></tr></thead>
                    <tbody>
                        @foreach ($openLines as $index => $line)
                            @php($remaining = \App\Domain\Money\Exact::of($line->qty)->minus(\App\Domain\Money\Exact::of($line->received_qty)))
                            <tr x-data="{ status: '{{ old("items.$index.status", 'confirmed') }}' }">
                                <td>
                                    <span class="font-medium">{{ $line->product?->name_en }}</span>
                                    <input type="hidden" name="items[{{ $index }}][purchase_order_line_id]" value="{{ $line->id }}">
                                </td>
                                <td class="text-right font-mono">{{ format_number($line->qty) }}</td>
                                <td class="text-right font-mono">{{ format_number($line->received_qty) }}</td>
                                <td>
                                    <select name="items[{{ $index }}][status]" x-model="status" class="input min-h-10">
                                        <option value="confirmed">{{ __('CONFIRMED') }}</option>
                                        <option value="due">{{ __('DUE') }}</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number"
                                           name="items[{{ $index }}][due_qty]"
                                           value="{{ old("items.$index.due_qty", '0') }}"
                                           min="0"
                                           max="{{ $remaining }}"
                                           step="0.001"
                                           :required="status === 'due'"
                                           :disabled="status !== 'due'"
                                           class="input min-h-10 text-right font-mono disabled:bg-slate-100"
                                           aria-label="{{ __('Pieces due for :product', ['product' => $line->product?->name_en]) }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-500">{{ __('CONFIRMED receives all remaining pieces. DUE receives the remaining quantity minus the pieces entered as due.') }}</p>
        </section>

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('purchases.orders.show', $order) }}" class="btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-primary">{{ __('Submit / Post Delivery') }}</button>
        </div>
    </form>
@endsection
