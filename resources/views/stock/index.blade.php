@extends('layouts.app')

@section('title', __('Stock'))

@section('content')
    <div
        x-data="stockCheckPage({{ \Illuminate\Support\Js::from([
            'checking' => (bool) ($checking ?? false),
            'exportCheckId' => $exportCheckId ?? null,
            'confirmUrl' => route('stock.checks.store'),
            'exportBase' => url('/stock/checks'),
            'startUrl' => route('stock.index', array_filter(['q' => $filters['q'] ?? null, 'checking' => 1])),
            'cancelUrl' => route('stock.index', array_filter(['q' => $filters['q'] ?? null])),
            'csrf' => csrf_token(),
            'productIds' => $products->pluck('id')->values()->all(),
        ]) }})"
        data-testid="stock-page"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Stock') }}</h1>
            @if ($canStockCheck ?? false)
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center" data-testid="stock-check-actions">
                    <a
                        href="{{ route('stock.checks.index') }}"
                        class="btn-secondary w-full sm:w-auto text-center"
                        data-testid="stock-check-history"
                    >{{ __('Past checks') }}</a>
                    <button
                        type="button"
                        class="btn-secondary w-full sm:w-auto"
                        data-testid="stock-check-start"
                        @click="start()"
                        :disabled="checking"
                    >{{ __('Stock Check') }}</button>
                    <template x-if="checking">
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                            <button
                                type="button"
                                class="btn-primary w-full sm:w-auto"
                                data-testid="stock-check-confirm"
                                @click="confirmStock()"
                                :disabled="!canConfirm || submitting"
                            >{{ __('CONFIRM STOCK') }}</button>
                            <button
                                type="button"
                                class="btn-secondary w-full sm:w-auto"
                                data-testid="stock-check-cancel"
                                @click="cancel()"
                                :disabled="submitting"
                            >{{ __('CANCEL') }}</button>
                        </div>
                    </template>
                    <template x-if="exportCheckId && !checking">
                        <a
                            class="btn-primary"
                            data-testid="stock-check-export"
                            :href="exportUrl"
                        >{{ __('Export report') }}</a>
                    </template>
                </div>
            @endif
        </div>

        <p x-show="checking" x-cloak class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900" data-testid="stock-check-hint">
            {{ __('Mark each product Available or Not Available, then confirm to export the report.') }}
            <span class="ml-1 font-medium" x-text="markedCount + '/' + productIds.length + ' {{ __('marked') }}'"></span>
        </p>

        @if ($pending)
            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" data-testid="costing-pending">{{ __('A costing update is pending. Figures may change after the next cron run.') }}</p>
        @endif

        @if (($nearExpiry ?? collect())->isNotEmpty())
            <section class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm" data-testid="near-expiry-alert">
                <h2 class="font-semibold text-amber-900">{{ __('Near expiry') }}</h2>
                <ul class="mt-1 list-inside list-disc text-amber-900">
                    @foreach ($nearExpiry as $batch)
                        <li>{{ $batch->product->name_en }} · {{ $batch->batch_no }} · {{ local_digits($batch->expiry_date->format('d-m-Y')) }}</li>
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
            @if ($checking ?? false)
                <input type="hidden" name="checking" value="1">
            @endif
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
                        <th x-show="checking" x-cloak data-testid="in-store-header">{{ __('IN STORE') }}</th>
                        <th x-show="!checking"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php $row = $latest->get($product->id); @endphp
                        <tr data-testid="stock-row-{{ $product->id }}">
                            <td>
                                <span class="font-medium">{{ $product->name_en }}</span>
                                @if ($product->code)<span class="block font-mono text-xs text-slate-500">{{ $product->code }}</span>@endif
                            </td>
                            <td class="text-right font-mono">{{ $row ? local_digits($row->running_qty) : local_digits('0') }}</td>
                            <td class="text-right font-mono">{{ $row ? format_money($row->running_value) : format_money(0) }}</td>
                            <td class="text-right font-mono">{{ $row ? format_number($row->average_cost, 4) : '—' }}</td>
                            <td x-show="checking" x-cloak class="min-w-[18rem]">
                                <div class="flex flex-wrap gap-2" data-testid="in-store-controls-{{ $product->id }}">
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-sm font-semibold"
                                        :class="marks[{{ $product->id }}] === 'available'
                                            ? 'border-emerald-200 bg-emerald-100 text-emerald-800'
                                            : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                                        @click="setMark({{ $product->id }}, 'available')"
                                        data-testid="mark-available-{{ $product->id }}"
                                    >{{ __('Available') }}</button>
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-sm font-semibold"
                                        :class="marks[{{ $product->id }}] === 'not_available'
                                            ? 'border-rose-200 bg-rose-50 text-rose-900'
                                            : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                                        @click="setMark({{ $product->id }}, 'not_available')"
                                        data-testid="mark-not-available-{{ $product->id }}"
                                    >{{ __('Not Available') }}</button>
                                </div>
                            </td>
                            <td x-show="!checking" class="text-right">
                                <a href="{{ route('stock.show', $product) }}" class="text-brand-700 underline">{{ __('Ledger') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('No products.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (! ($checking ?? false) && method_exists($products, 'links'))
            {{ $products->links() }}
        @endif
    </div>
@endsection
