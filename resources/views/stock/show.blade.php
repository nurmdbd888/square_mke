@extends('layouts.app')

@section('title', __('Stock ledger'))

@section('content')
    <x-back-link :href="route('stock.index')">{{ __('Back to stock') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $product->name_en }}</h1>
    @if ($product->code)
        <p class="font-mono text-sm text-slate-500">{{ $product->code }}</p>
    @endif
    @if ($pending)
        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('A costing update is pending. Figures may change after the next cron run.') }}</p>
    @endif

    <div class="mt-6 table-wrap">
        <table class="data-table" data-testid="stock-ledger">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Movement') }}</th>
                    <th>{{ __('Document') }}</th>
                    <th class="text-right">{{ __('In') }}</th>
                    <th class="text-right">{{ __('Out') }}</th>
                    <th class="text-right">{{ __('Value in') }}</th>
                    <th class="text-right">{{ __('Value out') }}</th>
                    <th class="text-right">{{ __('Running qty') }}</th>
                    <th class="text-right">{{ __('Average') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ local_digits($entry->doc_date->format('d-m-Y')) }}</td>
                        <td>{{ match ($entry->movement) {
                            'purchase' => __('Purchase'),
                            'sale' => __('Sale'),
                            'sale_bonus' => __('Sale bonus'),
                            'opening' => __('Opening'),
                            'sales_return' => __('Sales return'),
                            'adjustment_in' => __('Adjustment in'),
                            'purchase_return' => __('Purchase return'),
                            'loss' => __('Loss'),
                            default => $entry->movement,
                        } }}</td>
                        <td class="font-mono">{{ $entry->document?->number ?? '—' }}</td>
                        <td class="text-right font-mono">{{ local_digits($entry->qty_in) }}</td>
                        <td class="text-right font-mono">{{ local_digits($entry->qty_out) }}</td>
                        <td class="text-right font-mono">{{ format_money($entry->value_in) }}</td>
                        <td class="text-right font-mono">{{ format_money($entry->value_out) }}</td>
                        <td class="text-right font-mono">{{ local_digits($entry->running_qty) }}</td>
                        <td class="text-right font-mono">{{ format_number($entry->average_cost, 4) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-slate-500">{{ __('No stock movements yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}
@endsection
