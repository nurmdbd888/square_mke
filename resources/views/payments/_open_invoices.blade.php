@if ($invoices->isEmpty())
    <p class="text-sm text-slate-500">{{ __('No open invoices.') }}</p>
@else
    <div class="table-wrap">
        <table class="data-table" data-testid="open-invoices">
            <thead>
                <tr>
                    <th>{{ __('Allocate') }}</th>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th class="text-right">{{ __('Remaining') }}</th>
                    <th class="text-right">{{ __('This payment') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $i => $invoice)
                    <tr>
                        <td>
                            <input type="hidden" name="allocations[{{ $i }}][document_id]" value="{{ $invoice->document_id }}">
                        </td>
                        <td class="font-mono">{{ $invoice->number }}</td>
                        <td>{{ local_digits(\Illuminate\Support\Carbon::parse($invoice->doc_date)->format('d-m-Y')) }}</td>
                        <td class="text-right font-mono">{{ format_money($invoice->remaining) }}</td>
                        <td class="text-right">
                            <input type="text" name="allocations[{{ $i }}][amount]" value="0" inputmode="decimal" class="input text-right" data-remaining="{{ $invoice->remaining }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
