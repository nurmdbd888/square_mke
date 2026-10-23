@extends('layouts.app')

@php
    $isPurchase = $type === 'purchase';
    $title = $document->exists
        ? ($isPurchase ? __('Edit purchase') : __('Edit sale'))
        : ($isPurchase ? __('Add purchase') : __('Add sale'));
    $indexRoute = $isPurchase ? 'purchases.index' : 'sales.index';
    $action = $document->exists
        ? route($isPurchase ? 'purchases.update' : 'sales.update', $document)
        : route($isPurchase ? 'purchases.store' : 'sales.store');
    $oldLines = old('lines');
    if ($oldLines === null) {
        $oldLines = $document->lines->map(fn ($l) => [
            'product_id' => $l->product_id,
            'kind' => $l->kind,
            'qty' => $l->qty,
            'bonus_qty' => $l->bonus_qty,
            'unit_price' => number_format((float) $l->unit_price, 2, '.', ''),
            'line_discount' => $l->line_discount,
            'line_value' => $l->line_value,
            'sd_pct' => $l->sd_pct,
            'vat_pct' => $l->vat_pct,
            'sd_amount' => $l->sd_amount,
            'vat_amount' => $l->vat_amount,
        ])->all();
    }
    $productCatalog = $products->map(function ($product) {
        $searchParts = array_filter([
            $product->code,
            $product->name_en,
            $product->name_bn,
            ...$product->aliases->pluck('name')->all(),
        ]);

        return [
            'id' => $product->id,
            'code' => (string) ($product->code ?? ''),
            'brand' => (string) ($product->brand ?? ''),
            'name' => (string) $product->name_en,
            'name_bn' => (string) ($product->name_bn ?? ''),
            'searchText' => mb_strtolower(implode(' ', $searchParts)),
            'buy' => number_format((float) ($product->buy_price ?? 0), 2, '.', ''),
            'sell' => number_format((float) ($product->sell_price ?? 0), 2, '.', ''),
            'sd' => (string) ($product->sd_pct ?? '0'),
            'vat' => (string) ($product->vat_pct ?? '15'),
        ];
    })->values();
@endphp

@section('title', $title)

@section('content')
    <x-back-link :href="route($indexRoute)">{{ $isPurchase ? __('Back to purchases') : __('Back to sales') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $title }}</h1>
    @if ($document->exists && $document->isPosted())
        <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('This document is posted. Saving recomputes stock and writes an audit entry.') }}</p>
    @endif

    @if (! $isPurchase)
        <p id="offline-sales-banner" class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900" hidden></p>
    @endif

    <div class="mt-6">
        <x-ocr-scan-panel mode="invoice" />
    </div>

    <form
        method="POST"
        action="{{ $action }}"
        enctype="multipart/form-data"
        class="mt-6 space-y-6"
        x-data="documentForm({{ Js::from(array_values($oldLines)) }}, {{ Js::from($productCatalog) }}, {{ Js::from([
            'header_discount' => old('header_discount', $document->header_discount ?? '0'),
            'slab_adjustment' => old('slab_adjustment', $document->slab_adjustment ?? '0'),
            'combo_bonus_discount' => old('combo_bonus_discount', $document->combo_bonus_discount ?? '0'),
            'tds_amount' => old('tds_amount', $document->tds_amount ?? '0'),
            'rounding_adjustment' => old('rounding_adjustment', $document->rounding_adjustment ?? '0'),
        ]) }}, {{ Js::from([
            'productCreateUrl' => auth()->user()->can('products.manage') ? route('products.store') : null,
            'csrfToken' => csrf_token(),
        ]) }})"
        @unless($isPurchase)
            data-offline-sales
            data-sync-url="{{ route('sales.sync-draft') }}"
            data-offline-saved-label="{{ __('Saved offline. It will sync when you are back online.') }}"
            data-syncing-label="{{ __('Syncing offline drafts…') }}"
            data-synced-label="{{ __('Offline drafts synced.') }}"
            data-sync-error-label="{{ __('Some drafts could not sync yet.') }}"
        @endunless
    >
        @csrf
        @if ($document->exists)
            @method('PUT')
        @endif

        <section class="card grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="label">{{ __('Invoice number') }}</span>
                <input type="text" name="number" value="{{ old('number', $document->number) }}" required maxlength="80" class="input font-mono">
            </label>
            <x-party-code-picker
                :name="$isPurchase ? 'supplier_id' : 'retailer_id'"
                :label="$isPurchase ? __('Supplier') : __('Retailer')"
                :parties="$parties"
                :selected-id="$isPurchase ? $document->supplier_id : $document->retailer_id"
                :create-url="$isPurchase
                    ? (auth()->user()->can('masters.manage') ? route('masters.directory.store', 'suppliers') : null)
                    : (auth()->user()->can('retailers.manage') ? route('retailers.store') : null)"
                :create-label="$isPurchase ? __('Add supplier') : __('Add retailer')"
            />
            <label class="block">
                <span class="label">{{ __('Date') }}</span>
                <x-date-input name="doc_date" :value="$document->doc_date?->format('Y-m-d') ?? now()->toDateString()" required />
            </label>
            <label class="block">
                <span class="label">{{ __('Delivery date') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <x-date-input name="delivery_date" :value="$document->delivery_date?->format('Y-m-d')" />
            </label>
            @unless ($isPurchase)
                <label class="block">
                    <span class="label">{{ __('Sales rep') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="sales_rep_id" class="input">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($reps as $rep)
                            <option value="{{ $rep->id }}" @selected((string) old('sales_rep_id', $document->sales_rep_id) === (string) $rep->id)>{{ $rep->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Route') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="route_id" class="input">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}" @selected((string) old('route_id', $document->route_id) === (string) $route->id)>{{ $route->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Vehicle') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="vehicle_id" class="input">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $document->vehicle_id) === (string) $vehicle->id)>{{ $vehicle->registration }}</option>
                        @endforeach
                    </select>
                </label>
            @endunless
            <label class="block sm:col-span-2 lg:col-span-3">
                <span class="label">{{ __('Notes') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <textarea name="notes" rows="2" class="input">{{ old('notes', $document->notes) }}</textarea>
            </label>
        </section>

        <section class="card" data-testid="document-lines">
            <h2 class="font-semibold text-slate-900">{{ __('Products on this invoice') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Search on the left, click a product to add it. Change quantity and price on the right.') }}</p>

            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(15rem,20rem)_1fr]">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <label class="block">
                        <span class="label">{{ __('Brand') }}</span>
                        <select
                            class="input"
                            x-model="brandFilter"
                            data-testid="product-brand-filter"
                            @change="productHighlight = 0; productOpen = filteredProducts.length > 0"
                        >
                            <option value="">{{ __('All brands') }}</option>
                            <template x-for="brand in brandOptions" :key="brand">
                                <option :value="brand" x-text="brand"></option>
                            </template>
                        </select>
                    </label>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Pick a brand to work one brand at a time, or leave on All brands.') }}</p>

                    <div class="relative mt-3">
                        <label class="block">
                            <span class="label">{{ __('Search product') }}</span>
                            <input
                                type="search"
                                class="input"
                                placeholder="{{ __('Code, English or Bangla name') }}"
                                x-model="productQuery"
                                @input="productOpen = true; productHighlight = 0"
                                @focus="productOpen = filteredProducts.length > 0"
                                @keydown.down.prevent="highlightNextProduct()"
                                @keydown.up.prevent="highlightPrevProduct()"
                                @keydown.enter.prevent="addHighlightedProduct()"
                                autocomplete="off"
                            >
                        </label>
                        <ul
                            x-cloak
                            x-show="productOpen && filteredProducts.length"
                            @click.outside="productOpen = false"
                            class="absolute left-0 right-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg"
                        >
                            <template x-for="(product, pIndex) in filteredProducts.slice(0, 40)" :key="product.id">
                                <li>
                                    <button
                                        type="button"
                                        class="block w-full px-3 py-2 text-left"
                                        :class="productHighlight === pIndex ? 'bg-brand-50' : 'hover:bg-slate-50'"
                                        @mouseenter="productHighlight = pIndex"
                                        @click="addProduct(product)"
                                    >
                                        <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                            <span class="font-mono text-xs text-slate-500" x-text="product.code || '—'"></span>
                                            <span class="text-xs font-medium text-brand-700" x-show="!brandFilter && product.brand" x-text="product.brand"></span>
                                        </span>
                                        <span class="mt-0.5 block font-medium text-slate-900" x-text="product.name"></span>
                                        <span class="block text-xs text-slate-600" x-show="product.name_bn" x-text="product.name_bn"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        @can('products.manage')
                            <div
                                x-cloak
                                x-show="productQuery.trim() !== '' && filteredProducts.length === 0"
                                class="absolute left-0 right-0 top-full z-10 mt-1 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
                                data-testid="product-search-empty"
                            >
                                <p class="text-sm text-slate-600">{{ __('No matching product found.') }}</p>
                                <button
                                    type="button"
                                    class="mt-2 text-sm font-semibold text-brand-700 hover:underline"
                                    data-testid="product-quick-add"
                                    @click="openProductCreate()"
                                >
                                    {{ __('Add product') }}:
                                    <span x-text="productQuery"></span>
                                </button>
                            </div>
                        @endcan
                        @can('products.manage')
                            <div
                                x-cloak
                                x-show="productCreateOpen"
                                class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-slate-900/40 p-4 sm:items-center"
                                role="dialog"
                                aria-modal="true"
                                aria-label="{{ __('Add product') }}"
                                data-testid="product-quick-add-dialog"
                                @keydown.escape.window="if (!productCreating) productCreateOpen = false"
                                @keydown.enter="if ($event.target.tagName !== 'BUTTON') { $event.preventDefault(); saveNewProduct(); }"
                            >
                                <div class="my-auto w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-lg">
                                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Add product') }}</h2>
                                    <p class="mt-1 text-sm text-slate-600">{{ __('The product will be available in sales, purchases and stock.') }}</p>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <label class="block">
                                            <span class="label">{{ __('Name') }}</span>
                                            <input type="text" class="input" x-model="newProduct.name_en" maxlength="191" :required="productCreateOpen" data-testid="product-quick-add-name">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('Code') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                                            <input type="text" class="input font-mono" x-model="newProduct.code" maxlength="40">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('Bangla name') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                                            <input type="text" class="input" x-model="newProduct.name_bn" maxlength="191">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('Brand') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                                            <input type="text" class="input" x-model="newProduct.brand" maxlength="80">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('Unit') }}</span>
                                            <input type="text" class="input" x-model="newProduct.unit" maxlength="20" :required="productCreateOpen">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('Buy price') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                                            <input type="text" class="input font-mono" x-model="newProduct.buy_price" inputmode="decimal">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('Sell price') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                                            <input type="text" class="input font-mono" x-model="newProduct.sell_price" inputmode="decimal">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('SD %') }}</span>
                                            <input type="text" class="input font-mono" x-model="newProduct.sd_pct" inputmode="decimal" :required="productCreateOpen">
                                        </label>
                                        <label class="block">
                                            <span class="label">{{ __('VAT %') }}</span>
                                            <input type="text" class="input font-mono" x-model="newProduct.vat_pct" inputmode="decimal" :required="productCreateOpen">
                                        </label>
                                    </div>
                                    <p x-cloak x-show="productCreateError" class="mt-3 text-sm text-red-700" role="alert" x-text="productCreateError"></p>
                                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                        <button type="button" class="btn-secondary" @click="productCreateOpen = false" :disabled="productCreating">{{ __('Cancel') }}</button>
                                        <button type="button" class="btn-primary" data-testid="product-quick-add-save" @click="saveNewProduct()" :disabled="productCreating || !newProduct.name_en.trim() || !newProduct.unit.trim()">
                                            <span x-text="productCreating ? '{{ __('Saving…') }}' : '{{ __('Save and add') }}'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endcan
                    </div>
                    @unless ($isPurchase)
                        <div class="mt-3" data-testid="line-add-kind">
                            <span class="label">{{ __('Add next product as') }}</span>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <button type="button" class="btn-secondary py-1.5 text-xs"
                                        :class="lineAddKind === 'normal' ? 'ring-2 ring-brand-500' : ''"
                                        @click="lineAddKind = 'normal'">{{ __('Paid line') }}</button>
                                <button type="button" class="btn-secondary py-1.5 text-xs"
                                        :class="lineAddKind === 'combo_bonus' ? 'ring-2 ring-brand-500' : ''"
                                        @click="lineAddKind = 'combo_bonus'">{{ __('Invoice Combo Bonus') }}</button>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Combo bonus lines leave stock but add 0.00 to the invoice total.') }}</p>
                        </div>
                    @endunless
                    <p class="mt-2 text-xs text-slate-500">{{ __('Click a product to add it to the invoice.') }}</p>
                </div>

                <div class="min-w-0">
                    <div class="table-wrap">
                        <table class="data-table min-w-[58rem] table-fixed text-sm">
                            <thead>
                                <tr>
                                    <th class="w-14 whitespace-nowrap">{{ __('SL. NO.') }}</th>
                                    <th class="w-[28%] min-w-[16rem]">{{ __('Product') }}</th>
                                    <th class="w-28">{{ __('Qty') }}</th>
                                    <th class="w-32">{{ __('Price') }}</th>
                                    <th class="w-28">{{ __('Disc.') }}</th>
                                    <th class="w-28 text-right">{{ __('Total') }}</th>
                                    <th class="w-20"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(line, index) in lines" :key="index">
                                    <tr class="align-top" :class="line.kind === 'combo_bonus' ? 'bg-amber-50/80' : ''">
                                        <td class="font-mono text-slate-600" data-testid="line-sl-no" x-text="index + 1"></td>
                                        <td>
                                            <input type="hidden" :name="`lines[${index}][product_id]`" x-model="line.product_id">
                                            <input type="hidden" :name="`lines[${index}][kind]`" x-model="line.kind">
                                            <input type="hidden" :name="`lines[${index}][bonus_qty]`" x-model="line.bonus_qty">
                                            <input type="hidden" :name="`lines[${index}][sd_pct]`" x-model="line.sd_pct">
                                            <input type="hidden" :name="`lines[${index}][vat_pct]`" x-model="line.vat_pct">
                                            <input type="hidden" :name="`lines[${index}][sd_amount]`" x-model="line.sd_amount">
                                            <input type="hidden" :name="`lines[${index}][vat_amount]`" x-model="line.vat_amount">
                                            <span class="font-medium text-slate-900" x-text="productLabel(line.product_id)"></span>
                                            <span x-show="line.kind === 'combo_bonus'" class="ml-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900">{{ __('Combo bonus') }}</span>
                                            <button type="button" class="mt-1 block text-xs text-brand-700 underline" @click="line.showTax = !line.showTax" x-text="line.showTax ? '{{ __('Hide tax & bonus') }}' : '{{ __('Tax & bonus') }}'"></button>
                                        </td>
                                        <td>
                                            <input type="text" :name="`lines[${index}][qty]`" x-model="line.qty" required inputmode="decimal" class="input-num py-1.5 text-sm" @input="recalc(index)">
                                        </td>
                                        <td>
                                            <input type="text" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" inputmode="decimal" class="input-num py-1.5 text-sm font-mono" @input="recalc(index)">
                                        </td>
                                        <td>
                                            <input type="text" :name="`lines[${index}][line_discount]`" x-model="line.line_discount" inputmode="decimal" class="input-num py-1.5 text-sm font-mono" :disabled="line.kind === 'combo_bonus'" @input="recalc(index)">
                                        </td>
                                        <td class="text-right font-mono">
                                            <input type="hidden" :name="`lines[${index}][line_value]`" x-model="line.line_value">
                                            <span x-text="line.kind === 'combo_bonus' ? '0.00' : (line.line_value || '0.00')"></span>
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="text-xs text-red-700 underline" @click="removeLine(index)">{{ __('Remove') }}</button>
                                        </td>
                                    </tr>
                                    <tr x-show="line.showTax" x-cloak class="bg-slate-50">
                                        <td colspan="7" class="py-2">
                                            <div class="flex flex-wrap gap-3 text-xs">
                                                @unless ($isPurchase)
                                                    <label class="flex items-center gap-1">
                                                        <span>{{ __('Kind') }}</span>
                                                        <select x-model="line.kind" class="input py-1 text-xs" @change="recalc(index)">
                                                            <option value="normal">{{ __('Normal') }}</option>
                                                            <option value="combo_bonus">{{ __('Combo bonus') }}</option>
                                                        </select>
                                                    </label>
                                                @endunless
                                                <label class="flex items-center gap-1">
                                                    <span>{{ __('Bonus qty') }}</span>
                                                    <input type="text" x-model="line.bonus_qty" class="input w-16 py-1 text-xs font-mono">
                                                </label>
                                                <label class="flex items-center gap-1">
                                                    <span>{{ __('SD %') }}</span>
                                                    <input type="text" x-model="line.sd_pct" class="input w-16 py-1 text-xs font-mono">
                                                </label>
                                                <label class="flex items-center gap-1">
                                                    <span>{{ __('VAT %') }}</span>
                                                    <input type="text" x-model="line.vat_pct" class="input w-16 py-1 text-xs font-mono">
                                                </label>
                                                <label class="flex items-center gap-1">
                                                    <span>{{ __('SD amount') }}</span>
                                                    <input type="text" x-model="line.sd_amount" class="input w-20 py-1 text-xs font-mono">
                                                </label>
                                                <label class="flex items-center gap-1">
                                                    <span>{{ __('VAT amount') }}</span>
                                                    <input type="text" x-model="line.vat_amount" class="input w-20 py-1 text-xs font-mono">
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="lines.length === 0">
                                    <td colspan="7" class="py-8 text-center text-slate-500">{{ __('No products yet. Search on the left and click to add.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="sticky bottom-0 z-10 mt-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm"
                        data-testid="invoice-total-summary"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm text-slate-600">
                            <span>{{ __('Lines subtotal') }}</span>
                            <span class="font-mono" data-testid="invoice-lines-subtotal" x-text="linesSubtotal"></span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-baseline justify-between gap-2 text-sm text-slate-600">
                            <span>{{ __('Net invoice amount') }}</span>
                            <span class="font-mono" data-testid="net-invoice-amount" x-text="netInvoiceAmount"></span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-base font-semibold text-slate-900">{{ __('Total payable') }}</span>
                            <span class="font-mono text-lg font-semibold text-slate-900" data-testid="invoice-grand-total" x-text="invoiceTotal"></span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Net invoice = subtotal − loading − combo. Total payable = net invoice + TDS.') }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Square Invoice Adjustment: Loading = slab_adjustment, Combo Discount = combo_bonus_discount. --}}
        <section class="card" data-testid="invoice-adjustment">
            <h2 class="font-semibold text-slate-900">{{ __('Invoice Adjustment') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('As printed on Square Toiletries invoices. Loading and combo reduce the net invoice; TDS is added to total payable.') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <label class="block">
                    <span class="label">{{ __('Total TDS Amount') }}</span>
                    <input type="text" name="tds_amount" x-model="tds_amount" inputmode="decimal" class="input font-mono" data-testid="tds-amount">
                </label>
                <label class="block">
                    <span class="label">{{ __('Loading Adjustment') }}</span>
                    <input type="text" name="slab_adjustment" x-model="slab_adjustment" inputmode="decimal" class="input font-mono" data-testid="loading-adjustment">
                </label>
                <label class="block">
                    <span class="label">{{ __('Combo Discount') }}</span>
                    <input type="text" name="combo_bonus_discount" x-model="combo_bonus_discount" inputmode="decimal" class="input font-mono" data-testid="combo-discount">
                </label>
            </div>
            {{-- Preserve legacy fields without showing duplicate Square-unrelated controls. --}}
            <input type="hidden" name="header_discount" x-model="header_discount">
            <input type="hidden" name="rounding_adjustment" x-model="rounding_adjustment">
            <div class="mt-4 flex flex-wrap items-baseline justify-between gap-2 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3" data-testid="invoice-total-footer">
                <div>
                    <div class="text-sm text-slate-600">{{ __('Net invoice amount') }}: <span class="font-mono" x-text="netInvoiceAmount"></span></div>
                    <div class="text-base font-semibold text-slate-900">{{ __('Total payable') }}</div>
                </div>
                <span class="font-mono text-xl font-semibold text-slate-900" x-text="invoiceTotal"></span>
            </div>
            <label class="mt-4 block">
                <span class="label">{{ __('Attachments') }} <span class="font-normal text-slate-500">({{ __('optional, max 5 MB each') }})</span></span>
                <input type="file" name="attachments[]" multiple class="input">
            </label>
        </section>

        <div class="flex flex-wrap gap-2">
            <button type="submit" name="action" value="save" class="btn-secondary">{{ __('Save draft') }}</button>
            <button type="submit" name="action" value="post" class="btn-primary">{{ $document->exists && $document->isPosted() ? __('Save and recompute') : __('Save and post') }}</button>
        </div>
    </form>

    <script>
        function documentForm(initial, catalog, adjustments, options) {
            const isPurchase = @json($isPurchase);
            const adj = adjustments || {};
            const productOptions = options || {};
            const priceInput = (value) => {
                if (value === null || value === undefined || value === '') {
                    return '';
                }
                const price = Number(value);
                return Number.isFinite(price) ? price.toFixed(2) : String(value);
            };
            const emptyProduct = () => ({
                code: '',
                name_en: '',
                name_bn: '',
                brand: '',
                unit: 'Pcs',
                buy_price: '',
                sell_price: '',
                sd_pct: '0',
                vat_pct: '15',
            });
            const lines = (initial || []).map((line) => ({
                showTax: false,
                kind: 'normal',
                bonus_qty: '0',
                line_discount: '0',
                sd_pct: '0',
                vat_pct: '15',
                sd_amount: '0',
                vat_amount: '0',
                ...line,
                unit_price: priceInput(line.unit_price),
            }));

            return {
                lines,
                catalog: catalog || [],
                productCreateUrl: productOptions.productCreateUrl || '',
                csrfToken: productOptions.csrfToken || '',
                productCreateOpen: false,
                productCreating: false,
                productCreateError: '',
                newProduct: emptyProduct(),
                header_discount: String(adj.header_discount ?? '0'),
                slab_adjustment: String(adj.slab_adjustment ?? '0'),
                combo_bonus_discount: String(adj.combo_bonus_discount ?? '0'),
                tds_amount: String(adj.tds_amount ?? '0'),
                rounding_adjustment: String(adj.rounding_adjustment ?? '0'),
                lineAddKind: 'normal',
                brandFilter: '',
                productQuery: '',
                productOpen: false,
                productHighlight: 0,
                money(value) {
                    const n = parseFloat(value || '0');
                    return (Number.isFinite(n) ? n : 0).toFixed(2);
                },
                get linesSubtotal() {
                    const sum = this.lines.reduce((total, line) => {
                        if (line.kind === 'combo_bonus') {
                            return total;
                        }
                        const value = parseFloat(line.line_value || '0');

                        return total + (Number.isFinite(value) ? value : 0);
                    }, 0);

                    return this.money(sum);
                },
                // Square: Net Invoice = subtotal − loading − combo (− header); Payable = net + TDS (+ rounding).
                get netInvoiceAmount() {
                    const subtotal = parseFloat(this.linesSubtotal);
                    const header = parseFloat(this.header_discount || '0');
                    const loading = parseFloat(this.slab_adjustment || '0');
                    const combo = parseFloat(this.combo_bonus_discount || '0');
                    const net = (Number.isFinite(subtotal) ? subtotal : 0)
                        - (Number.isFinite(header) ? header : 0)
                        - (Number.isFinite(loading) ? loading : 0)
                        - (Number.isFinite(combo) ? combo : 0);

                    return this.money(Math.round(net * 100) / 100);
                },
                get invoiceTotal() {
                    const netInvoice = parseFloat(this.netInvoiceAmount);
                    const tds = parseFloat(this.tds_amount || '0');
                    const rounding = parseFloat(this.rounding_adjustment || '0');
                    const payable = (Number.isFinite(netInvoice) ? netInvoice : 0)
                        + (Number.isFinite(tds) ? tds : 0)
                        + (Number.isFinite(rounding) ? rounding : 0);

                    return this.money(Math.round(payable * 100) / 100);
                },
                get brandOptions() {
                    const brands = new Set();
                    this.catalog.forEach((product) => {
                        if (product.brand) {
                            brands.add(product.brand);
                        }
                    });

                    return [...brands].sort((a, b) => a.localeCompare(b));
                },
                get brandScopedProducts() {
                    if (!this.brandFilter) {
                        return this.catalog;
                    }

                    return this.catalog.filter((product) => (product.brand || '') === this.brandFilter);
                },
                get filteredProducts() {
                    const pool = this.brandScopedProducts;
                    const q = this.productQuery.trim().toLocaleLowerCase();
                    if (q === '') {
                        return pool;
                    }

                    return pool.filter((product) => {
                        const haystack = product.searchText || '';
                        const code = (product.code || '').toLocaleLowerCase();

                        return haystack.includes(q) || code.includes(q);
                    });
                },
                productLabel(productId) {
                    const product = this.catalog.find((row) => String(row.id) === String(productId));
                    if (!product) {
                        return '—';
                    }

                    return (product.code ? product.code + ' · ' : '') + product.name;
                },
                emptyProduct() {
                    return emptyProduct();
                },
                openProductCreate() {
                    if (!this.productCreateUrl) {
                        return;
                    }
                    this.newProduct = this.emptyProduct();
                    this.newProduct.name_en = this.productQuery.trim();
                    this.productCreateError = '';
                    this.productCreateOpen = true;
                },
                async saveNewProduct() {
                    if (!this.productCreateUrl || this.productCreating) {
                        return;
                    }
                    this.productCreating = true;
                    this.productCreateError = '';
                    try {
                        const response = await fetch(this.productCreateUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(this.newProduct),
                            credentials: 'same-origin',
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                            throw new Error(firstError || data.message || '{{ __('Could not save product. Please try again.') }}');
                        }
                        this.catalog = [data].concat(this.catalog.filter((product) => String(product.id) !== String(data.id)));
                        this.productCreateOpen = false;
                        this.addProduct(data);
                        this.newProduct = this.emptyProduct();
                    } catch (error) {
                        this.productCreateError = error.message || '{{ __('Could not save product. Please try again.') }}';
                    } finally {
                        this.productCreating = false;
                    }
                },
                addProduct(product) {
                    const kind = isPurchase ? 'normal' : (this.lineAddKind || 'normal');
                    const index = this.lines.findIndex((line) =>
                        String(line.product_id) === String(product.id) && (line.kind || 'normal') === kind
                    );
                    if (index >= 0) {
                        const qty = parseFloat(this.lines[index].qty || '0');
                        this.lines[index].qty = String((Number.isFinite(qty) ? qty : 0) + 1);
                        this.recalc(index);
                    } else {
                        this.lines.push({
                            product_id: String(product.id),
                            kind: kind,
                            qty: '1',
                            bonus_qty: '0',
                            unit_price: priceInput(isPurchase ? (product.buy || '') : (product.sell || product.buy || '')),
                            line_discount: '0',
                            line_value: kind === 'combo_bonus' ? '0.00' : '',
                            sd_pct: product.sd || '0',
                            vat_pct: product.vat || '15',
                            sd_amount: '0',
                            vat_amount: '0',
                            showTax: kind === 'combo_bonus',
                        });
                        this.recalc(this.lines.length - 1);
                    }
                    this.productQuery = '';
                    this.productOpen = false;
                },
                addHighlightedProduct() {
                    const list = this.filteredProducts.slice(0, 40);
                    if (list.length === 0) {
                        return;
                    }
                    this.addProduct(list[this.productHighlight] ?? list[0]);
                },
                highlightNextProduct() {
                    const max = Math.min(this.filteredProducts.length, 40) - 1;
                    this.productHighlight = Math.min(this.productHighlight + 1, Math.max(max, 0));
                },
                highlightPrevProduct() {
                    this.productHighlight = Math.max(this.productHighlight - 1, 0);
                },
                removeLine(index) {
                    this.lines.splice(index, 1);
                },
                recalc(index) {
                    const line = this.lines[index];
                    if (!line) {
                        return;
                    }
                    if (line.kind === 'combo_bonus') {
                        line.line_discount = '0';
                        line.line_value = '0.00';
                        return;
                    }
                    const qty = parseFloat(line.qty || '0');
                    const price = parseFloat(line.unit_price || '0');
                    const discount = parseFloat(line.line_discount || '0');
                    if (!Number.isFinite(qty) || !Number.isFinite(price)) {
                        return;
                    }
                    const value = Math.round((qty * price - discount) * 100) / 100;
                    line.line_value = value.toFixed(2);
                },
            };
        }
    </script>
    @unless($isPurchase)
        @push('scripts')
            <script src="{{ asset('js/offline-sales.js') }}?v={{ filemtime(public_path('js/offline-sales.js')) }}" defer></script>
        @endpush
    @endunless
@endsection
