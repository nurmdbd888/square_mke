@php
    $existingAllocations = $existingAllocations ?? collect();
    $showInvoicePicker = $showInvoicePicker ?? false;
@endphp
@if ($invoices->isEmpty())
    <p class="text-sm text-slate-500">{{ __('No open invoices.') }}</p>
@else
    @isset($partyBalance)
        <p class="mb-3 rounded-lg bg-slate-100 px-3 py-2 text-sm" data-testid="party-balance-due">
            <span class="font-medium text-slate-800">{{ __('Total balance due') }}:</span>
            <span class="font-mono font-semibold">{{ format_money($partyBalance['balance']) }}</span>
        </p>
    @endisset

    @if ($showInvoicePicker)
        <label class="mb-4 block">
            <span class="label">{{ __('Pay against invoice') }}</span>
            <select class="input min-h-11 font-mono"
                    data-testid="invoice-picker"
                    @change="pickInvoice($event.target.value); $event.target.value = ''">
                <option value="">{{ __('Choose invoice number…') }}</option>
                @foreach ($invoices as $invoice)
                    <option value="{{ $invoice->document_id }}">
                        {{ $invoice->number }}
                        · {{ local_digits(\Illuminate\Support\Carbon::parse($invoice->doc_date)->format('d-m-Y')) }}
                        · {{ __('Due') }} {{ format_money($invoice->remaining) }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">{{ __('Posted sales with an outstanding balance for this retailer. Choosing one fills that row; you can still pay partially or split across invoices below.') }}</p>
        </label>
    @endif

    <div class="table-wrap">
        <table class="data-table" data-testid="open-invoices">
            <thead>
                <tr>
                    <th>{{ __('Invoice number') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th class="text-right">{{ __('Remaining') }}</th>
                    <th class="text-right">{{ __('This payment') }}</th>
                    <th class="text-right">{{ __('Balance after') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $i => $invoice)
                    @php
                        $prefill = old('allocations.'.$i.'.amount', $existingAllocations[$invoice->document_id]->amount ?? '0');
                    @endphp
                    <tr data-invoice-id="{{ $invoice->document_id }}">
                        <td class="font-mono">
                            <input type="hidden" name="allocations[{{ $i }}][document_id]" value="{{ $invoice->document_id }}">
                            {{ $invoice->number }}
                        </td>
                        <td>{{ local_digits(\Illuminate\Support\Carbon::parse($invoice->doc_date)->format('d-m-Y')) }}</td>
                        <td class="text-right font-mono">{{ format_money($invoice->remaining) }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <input type="text"
                                       name="allocations[{{ $i }}][amount]"
                                       value="{{ $prefill }}"
                                       inputmode="decimal"
                                       class="input max-w-[8rem] text-right font-mono allocation-amount"
                                       data-remaining="{{ $invoice->remaining }}"
                                       @input="$dispatch('allocation-changed')">
                                <button type="button"
                                        class="rounded-md px-2 py-1 text-xs font-medium text-brand-700 hover:bg-brand-50"
                                        data-pay-full="{{ $invoice->remaining }}"
                                        @click="$el.previousElementSibling.value = $el.dataset.payFull; $dispatch('allocation-changed')">
                                    {{ __('Full') }}
                                </button>
                            </div>
                        </td>
                        <td class="text-right font-mono text-slate-600 invoice-balance-after" data-remaining="{{ $invoice->remaining }}">—</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
