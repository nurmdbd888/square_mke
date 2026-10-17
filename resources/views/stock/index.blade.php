@extends('layouts.app')

@section('title', __('Stock'))

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Stock') }}</h1>
        @if ($canCompare ?? false)
            <a href="{{ route('stock.comparison.index') }}" class="btn-secondary">{{ __('Compare with export') }}</a>
        @endif
    </div>
    @if ($pending)
        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" data-testid="costing-pending">{{ __('A costing update is pending. Figures may change after the next cron run.') }}</p>
    @endif

    @if (($nearExpiry ?? collect())->isNotEmpty())
        <section class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm" data-testid="near-expiry-alert">
            <h2 class="font-semibold text-amber-900">{{ __('Near expiry') }}</h2>
            <ul class="mt-1 list-inside list-disc text-amber-900">
                @foreach ($nearExpiry as $batch)
                    <li>{{ $batch->product->name_en }} · {{ $batch->batch_no }} · {{ local_date($batch->expiry_date) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (($lowStock ?? collect())->isNotEmpty())
        <section class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm" data-testid="low-stock-alert">
            <h2 class="font-semibold text-rose-900">{{ __('Low stock') }}</h2>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($lowStock as $product)
                    <li>{{ $product->name_en }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="GET" action="{{ route('stock.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block min-w-[12rem] flex-1">
            <span class="label">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name, code or brand') }}" class="input">
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div class="mt-4 table-wrap">
        <table class="data-table" data-testid="stock-table">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th class="text-right">{{ __('On hand') }}</th>
                    <th class="text-right">{{ __('Stock value') }}</th>
                    <th class="text-right">{{ __('Average') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    @php $row = $latest->get($product->id); @endphp
                    <tr>
                        <td>
                            <span class="font-medium">{{ $product->name_en }}</span>
                            @if ($product->code)<span class="block font-mono text-xs text-slate-500">{{ $product->code }}</span>@endif
                        </td>
                        <td class="text-right font-mono">{{ $row ? local_digits($row->running_qty) : local_digits('0') }}</td>
                        <td class="text-right font-mono">{{ $row ? format_money($row->running_value) : format_money(0) }}</td>
                        <td class="text-right font-mono">{{ $row ? format_number($row->average_cost, 4) : '—' }}</td>
                        <td class="text-right"><a href="{{ route('stock.show', $product) }}" class="text-brand-700 underline">{{ __('Ledger') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('No products.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
@endsection
