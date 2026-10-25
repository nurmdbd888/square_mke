@extends('layouts.app')

@php
    $isReceipt = $payment->kind === 'receipt' || old('kind', $payment->kind) === 'receipt';
    $lockedInvoice = $lockedInvoice ?? null;
    $title = $payment->exists
        ? ($isReceipt ? __('Edit receipt') : __('Edit payment'))
        : ($isReceipt ? __('Add receipt') : __('Add payment'));
@endphp

@section('title', $title)

@section('content')
    <x-back-link :href="route($isReceipt ? 'collector.index' : 'payments.index')">
        {{ $isReceipt ? __('Back to Collections') : __('Back to payments') }}
    </x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $title }}</h1>

    <form method="POST"
          action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store', $lockedInvoice ? ['document_id' => $lockedInvoice->document_id] : []) }}"
          enctype="multipart/form-data"
          class="mt-6 space-y-6"
          @party-selected="partyId = $event.detail.id"
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
              },
              pickInvoice(documentId) {
                  if (!documentId) return;
                  document.querySelectorAll('#allocation-box tr[data-invoice-id]').forEach(row => {
                      row.classList.remove('bg-brand-50', 'ring-2', 'ring-brand-200');
                  });
                  const row = document.querySelector('#allocation-box tr[data-invoice-id="' + documentId + '"]');
                  if (!row) return;
                  row.classList.add('bg-brand-50', 'ring-2', 'ring-brand-200');
                  row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                  const input = row.querySelector('.allocation-amount');
                  if (input) {
                      input.value = input.dataset.remaining || '0';
                      input.focus();
                  }
                  this.recalc();
                  this.$dispatch('allocation-changed');
              }
          }"
          x-init="
              recalc();
              const focusId = {{ \Illuminate\Support\Js::from(request('document_id')) }};
              if (focusId) {
                  $nextTick(() => pickInvoice(String(focusId)));
              }
          "
          @allocation-changed.window="recalc()"
          @htmx:after-swap.window="if ($event.detail.target?.id === 'allocation-box') { recalc(); const focusId = {{ \Illuminate\Support\Js::from(request('document_id')) }}; if (focusId) pickInvoice(String(focusId)); }">
        @csrf
        @if ($payment->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="kind" value="{{ old('kind', $payment->kind) }}">

        @if ($lockedInvoice && ! $isReceipt)
            <section class="card" data-testid="purchase-invoice-details">
                <p class="text-xs uppercase text-slate-500">{{ __('Purchase invoice') }}</p>
                <h2 class="mt-1 font-mono text-xl font-semibold text-slate-900">{{ $lockedInvoice->number }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ format_app_date($lockedInvoice->doc_date) }} · {{ $lockedInvoice->party_name }}</p>
                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div><dt class="text-xs uppercase text-slate-500">{{ __('Invoice total') }}</dt><dd class="mt-1 font-mono font-semibold">{{ format_money($lockedInvoice->net_total) }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">{{ __('Paid') }}</dt><dd class="mt-1 font-mono font-semibold">{{ format_money($lockedInvoice->allocated) }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">{{ __('Amount due') }}</dt><dd class="mt-1 font-mono font-semibold">{{ format_money($lockedInvoice->remaining) }}</dd></div>
                </dl>
            </section>
        @endif

        @unless ($isReceipt)
            <section class="card grid gap-4 sm:grid-cols-2">
        @endunless
            <label class="block">
                <span class="label">{{ __('Method') }}</span>
                <select name="method" class="input min-h-11" x-model="method">
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            @if (! $isReceipt)
                <label class="block">
                    <span class="label">{{ __('Payment number') }}</span>
                    <input type="text" name="number" value="{{ old('number', $payment->number) }}" required maxlength="80" class="input font-mono min-h-11" data-testid="payment-number">
                    <p class="mt-1 text-xs text-slate-500">{{ __('Your payment voucher or bank reference — not a supplier purchase invoice number.') }}</p>
                </label>
            @elseif ($payment->exists)
                <div>
                    <span class="label">{{ __('Receipt number') }}</span>
                    <p class="font-mono text-slate-800" data-testid="payment-number">{{ $payment->number }}</p>
                </div>
            @endif
            <label class="block">
                <span class="label">{{ __('Date') }}</span>
                <x-date-input name="payment_date" :value="$payment->payment_date?->format('Y-m-d') ?? now()->toDateString()" required class="min-h-11" />
            </label>
            <label class="block sm:col-span-2">
                <span class="label">{{ $isReceipt ? __('Amount received') : __('Payment amount') }}</span>
                <input type="text" name="amount" x-model="paymentAmount" @input="recalc()" required inputmode="decimal" class="input font-mono text-lg min-h-12" data-testid="payment-amount">
                @unless ($lockedInvoice)
                    <p class="mt-1 text-xs text-slate-500">{{ __('Can be less than invoice totals for a partial payment.') }}</p>
                @elseif (! $isReceipt)
                    <p class="mt-2 flex justify-between text-sm text-slate-600">
                        <span>{{ __('Due after payment') }}</span>
                        <span class="font-mono font-semibold" data-testid="purchase-balance-after" x-text="Math.max(0, Number({{ \Illuminate\Support\Js::from((string) $lockedInvoice->remaining) }}) - (parseFloat(String(paymentAmount).replace(/,/g, '')) || 0)).toFixed(2)">{{ format_number($lockedInvoice->remaining) }}</span>
                    </p>
                @endunless
            </label>
            @if ($isReceipt)
                <div class="sm:col-span-2" data-party-hx>
                    {{-- Included only by HTMX (hx-include); harmless if also posted on save. --}}
                    <input type="hidden" name="party" value="retailer">
                    @if ($lockedInvoice)
                        @php($lockedRetailer = $retailers->firstWhere('id', $payment->retailer_id))
                        <span class="label">{{ __('Retailer') }}</span>
                        <input type="hidden" name="retailer_id" value="{{ $payment->retailer_id }}">
                        <p class="font-medium text-slate-800">{{ $lockedRetailer?->code }} · {{ $lockedRetailer?->name }}</p>
                    @else
                        <x-party-code-picker
                            name="retailer_id"
                            :label="__('Retailer')"
                            :parties="$retailers"
                            :selected-id="old('retailer_id', $payment->retailer_id ?? request('retailer_id'))"
                            data-testid="payment-retailer-picker"
                            hx-get="{{ route('payments.open-invoices') }}"
                            hx-target="#allocation-box"
                            hx-swap="innerHTML"
                            hx-include="closest [data-party-hx]"
                            hx-trigger="change"
                        />
                    @endif
                </div>
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
                <div data-party-hx>
                    <input type="hidden" name="party" value="supplier">
                    @if ($lockedInvoice)
                        <span class="label">{{ __('Supplier') }}</span>
                        <input type="hidden" name="supplier_id" value="{{ $payment->supplier_id }}">
                        <p class="font-medium text-slate-800">{{ $lockedInvoice->party_name }}</p>
                    @else
                        <x-party-code-picker
                            name="supplier_id"
                            :label="__('Supplier')"
                            :parties="$suppliers"
                            :selected-id="$payment->supplier_id"
                            :required="false"
                            hx-get="{{ route('payments.open-invoices') }}"
                            hx-target="#allocation-box"
                            hx-swap="innerHTML"
                            hx-include="closest [data-party-hx]"
                            hx-trigger="change"
                        />
                    @endif
                </div>
                @unless ($lockedInvoice)
                    <label class="block">
                        <span class="label">{{ __('Expense category') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                        <select name="expense_category_id" class="input min-h-11">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('expense_category_id', $payment->expense_category_id) === (string) $category->id)>{{ __($category->label) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endunless
            @endif
            <label class="block" x-show="method === 'pdc' || method === 'cheque'" x-cloak>
                <span class="label">{{ __('Due date') }}</span>
                <x-date-input name="due_date" :value="$payment->due_date?->format('Y-m-d')" class="min-h-11" />
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
        @unless ($isReceipt)
            </section>
        @endunless

        @unless ($lockedInvoice && ! $isReceipt)
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
                    'showInvoicePicker' => $isReceipt && ! $lockedInvoice,
                ])
            </div>
        </section>
        @endunless

        <div class="flex flex-wrap gap-2 pb-8">
            <button type="submit" name="action" value="save" class="btn-secondary min-h-12 px-5">{{ __('Save draft') }}</button>
            <button type="submit" name="action" value="post" class="btn-primary min-h-12 px-5">{{ __('Save and post') }}</button>
        </div>
    </form>
@endsection
