@extends('layouts.app')

@php
    $isReceipt = $payment->kind === 'receipt' || old('kind', $payment->kind) === 'receipt';
    $title = $payment->exists
        ? ($isReceipt ? __('Edit receipt') : __('Edit payment'))
        : ($isReceipt ? __('Add receipt') : __('Add payment'));
@endphp

@section('title', $title)

@section('content')
    <a href="{{ route('payments.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to payments') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $title }}</h1>

    <form method="POST"
          action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store') }}"
          enctype="multipart/form-data"
          class="mt-6 space-y-6 @if ($isReceipt) max-w-xl @endif"
          x-data="{
              method: '{{ old('method', $payment->method ?? 'cash') }}',
              partyId: '{{ old($isReceipt ? 'retailer_id' : 'supplier_id', $isReceipt ? $payment->retailer_id : $payment->supplier_id) }}',
              paymentAmount: '{{ old('amount', $payment->amount ?? '0') }}',
              allocatedTotal: '0',
              recalc() {
                  let sum = 0;
                  document.querySelectorAll('.allocation-amount').forEach(el => {
                      const v = parseFloat(String(el.value).replace(/,/g, '')) || 0;
                      sum += v;
                      const rem = parseFloat(el.dataset.remaining) || 0;
                      const after = Math.max(0, rem - v);
                      const cell = el.closest('tr')?.querySelector('.invoice-balance-after');
                      if (cell) cell.textContent = after.toFixed(2);
                  });
                  this.allocatedTotal = sum.toFixed(2);
              }
          }"
          x-init="recalc()"
          @allocation-changed.window="recalc()">
        @csrf
        @if ($payment->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="kind" value="{{ old('kind', $payment->kind) }}">

        @if ($isReceipt)
            <section class="card space-y-3">
                <h2 class="font-semibold text-slate-900">{{ __('Payment slip photos') }}</h2>
                <p class="text-sm text-slate-600">{{ __('Take a photo or choose from gallery. Up to 5 images.') }}</p>
                <input type="file"
                       name="slip_photos[]"
                       accept="image/*"
                       capture="environment"
                       multiple
                       class="input min-h-12 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-800"
                       data-testid="slip-photos-input">
                @if ($payment->attachments->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($payment->attachments as $photo)
                            @if ($photo->isImage())
                                <a href="{{ route('payments.attachments.show', [$payment, $photo]) }}" class="block overflow-hidden rounded-lg border border-slate-200">
                                    <img src="{{ route('payments.attachments.show', [$payment, $photo]) }}" alt="{{ $photo->original_name }}" class="size-20 object-cover" loading="lazy">
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section class="card grid gap-4 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Number') }}</span>
                <input type="text" name="number" value="{{ old('number', $payment->number) }}" required maxlength="80" class="input font-mono min-h-11">
            </label>
            <label class="block">
                <span class="label">{{ __('Date') }}</span>
                <input type="date" name="payment_date" value="{{ old('payment_date', $payment->payment_date?->format('Y-m-d') ?? now()->toDateString()) }}" required class="input min-h-11">
            </label>
            <label class="block">
                <span class="label">{{ __('Method') }}</span>
                <select name="method" class="input min-h-11" x-model="method">
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Amount received') }}</span>
                <input type="text" name="amount" x-model="paymentAmount" @input="recalc()" required inputmode="decimal" class="input font-mono text-lg min-h-12" data-testid="payment-amount">
                <p class="mt-1 text-xs text-slate-500">{{ __('Can be less than invoice totals for a partial payment.') }}</p>
            </label>
            @if ($isReceipt)
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Retailer') }}</span>
                    <select name="retailer_id" required class="input min-h-11" x-model="partyId"
                            hx-get="{{ route('payments.open-invoices') }}"
                            hx-target="#allocation-box"
                            hx-include="[name='retailer_id']"
                            hx-vals='{"party":"retailer"}'
                            hx-trigger="change">
                        <option value="">{{ __('Choose') }}</option>
                        @foreach ($retailers as $retailer)
                            <option value="{{ $retailer->id }}" @selected((string) old('retailer_id', $payment->retailer_id) === (string) $retailer->id)>
                                {{ $retailer->code ? $retailer->code.' · ' : '' }}{{ $retailer->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Sales rep') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="sales_rep_id" class="input min-h-11">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($reps as $rep)
                            <option value="{{ $rep->id }}" @selected((string) old('sales_rep_id', $payment->sales_rep_id) === (string) $rep->id)>{{ $rep->name }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <label class="block">
                    <span class="label">{{ __('Supplier') }} <span class="font-normal text-slate-500">({{ __('optional for expenses') }})</span></span>
                    <select name="supplier_id" class="input min-h-11" x-model="partyId"
                            hx-get="{{ route('payments.open-invoices') }}"
                            hx-target="#allocation-box"
                            hx-include="[name='supplier_id']"
                            hx-vals='{"party":"supplier"}'
                            hx-trigger="change">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $payment->supplier_id) === (string) $supplier->id)>
                                {{ $supplier->code ? $supplier->code.' · ' : '' }}{{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Expense category') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="expense_category_id" class="input min-h-11">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('expense_category_id', $payment->expense_category_id) === (string) $category->id)>{{ __($category->label) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="block" x-show="method === 'pdc' || method === 'cheque'" x-cloak>
                <span class="label">{{ __('Due date') }}</span>
                <input type="date" name="due_date" value="{{ old('due_date', $payment->due_date?->format('Y-m-d')) }}" class="input min-h-11">
            </label>
            <label class="block" x-show="method === 'cheque' || method === 'pdc' || method === 'bank'" x-cloak>
                <span class="label">{{ __('Cheque / reference') }}</span>
                <input type="text" name="cheque_number" value="{{ old('cheque_number', $payment->cheque_number) }}" maxlength="80" class="input min-h-11">
            </label>
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Reference') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <input type="text" name="reference" value="{{ old('reference', $payment->reference) }}" maxlength="120" class="input min-h-11">
            </label>
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Notes') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <textarea name="notes" rows="2" class="input">{{ old('notes', $payment->notes) }}</textarea>
            </label>
        </section>

        <section class="card">
            <h2 class="font-semibold text-slate-900">{{ __('Allocate to invoices') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Enter amounts per invoice. Unallocated cash stays as advance; invoice rows show balance due after this receipt.') }}</p>
            <div class="mt-3 flex flex-wrap gap-4 text-sm" data-testid="allocation-summary">
                <span><span class="text-slate-500">{{ __('Allocated') }}:</span> <span class="font-mono font-semibold" x-text="allocatedTotal">0</span></span>
                <span x-show="parseFloat(allocatedTotal) > parseFloat(String(paymentAmount).replace(/,/g,'') || 0)" x-cloak class="text-amber-700">{{ __('Allocated total exceeds payment amount.') }}</span>
            </div>
            <div id="allocation-box" class="mt-4">
                @include('payments._open_invoices', [
                    'invoices' => $openInvoices,
                    'partyBalance' => $partyBalance,
                    'existingAllocations' => $existingAllocations ?? collect(),
                ])
            </div>
            <input type="hidden" name="party_id" :value="partyId" x-bind:value="partyId">
        </section>

        <div class="flex flex-wrap gap-2 pb-8">
            <button type="submit" name="action" value="save" class="btn-secondary min-h-12 px-5">{{ __('Save draft') }}</button>
            <button type="submit" name="action" value="post" class="btn-primary min-h-12 px-5">{{ __('Save and post') }}</button>
        </div>
    </form>
@endsection
