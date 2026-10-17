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
            'unit_price' => $l->unit_price,
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
            'buy' => (string) ($product->buy_price ?? ''),
            'sell' => (string) ($product->sell_price ?? ''),
            'sd' => (string) ($product->sd_pct ?? '0'),
            'vat' => (string) ($product->vat_pct ?? '15'),
        ];
    })->values();
@endphp

@section('title', $title)

@section('content')
    <a href="{{ route($indexRoute) }}" class="text-sm text-brand-700 underline">{{ $isPurchase ? __('Back to purchases') : __('Back to sales') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $title }}</h1>
    @if ($document->exists && $document->isPosted())
        <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('This document is posted. Saving recomputes stock and writes an audit entry.') }}</p>
    @endif

    @if (! $isPurchase)
        <p id="offline-sales-banner" class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900" hidden></p>
    @endif

    <form
        method="POST"
        action="{{ $action }}"
        enctype="multipart/form-data"
        class="mt-6 space-y-6"
        x-data="documentForm({{ Js::from(array_values($oldLines)) }}, {{ Js::from($productCatalog) }})"
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
            />
            <label class="block">
                <span class="label">{{ __('Date') }}</span>
                <input type="date" name="doc_date" value="{{ old('doc_date', $document->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}" required class="input">
            </label>
            <label class="block">
                <span class="label">{{ __('Delivery date') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <input type="date" name="delivery_date" value="{{ old('delivery_date', $document->delivery_date?->format('Y-m-d')) }}" class="input">
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
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Click a product to add it to the invoice.') }}</p>
                </div>

                <div class="min-w-0">
                    <div class="table-wrap">
                        <table class="data-table text-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Product') }}</th>
                                    <th class="min-w-[5.5rem]">{{ __('Qty') }}</th>
                                    <th class="min-w-[6.5rem]">{{ __('Price') }}</th>
                                    <th class="min-w-[5.5rem]">{{ __('Disc.') }}</th>
                                    <th class="w-24 text-right">{{ __('Total') }}</th>
                                    <th class="w-16"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(line, index) in lines" :key="index">
                                    <tr class="align-top">
                                        <td>
                                            <input type="hidden" :name="`lines[${index}][product_id]`" x-model="line.product_id">
                                            <input type="hidden" :name="`lines[${index}][kind]`" x-model="line.kind">
                                            <input type="hidden" :name="`lines[${index}][bonus_qty]`" x-model="line.bonus_qty">
                                            <input type="hidden" :name="`lines[${index}][sd_pct]`" x-model="line.sd_pct">
                                            <input type="hidden" :name="`lines[${index}][vat_pct]`" x-model="line.vat_pct">
                                            <input type="hidden" :name="`lines[${index}][sd_amount]`" x-model="line.sd_amount">
                                            <input type="hidden" :name="`lines[${index}][vat_amount]`" x-model="line.vat_amount">
                                            <span class="font-medium text-slate-900" x-text="productLabel(line.product_id)"></span>
                                            <button type="button" class="mt-1 block text-xs text-brand-700 underline" @click="line.showTax = !line.showTax" x-text="line.showTax ? '{{ __('Hide tax & bonus') }}' : '{{ __('Tax & bonus') }}'"></button>
                                        </td>
                                        <td>
                                            <input type="text" :name="`lines[${index}][qty]`" x-model="line.qty" required inputmode="decimal" class="input-num py-1.5 text-sm" @input="recalc(index)">
                                        </td>
                                        <td>
                                            <input type="text" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" inputmode="decimal" class="input-num py-1.5 text-sm font-mono" @input="recalc(index)">
                                        </td>
                                        <td>
                                            <input type="text" :name="`lines[${index}][line_discount]`" x-model="line.line_discount" inputmode="decimal" class="input-num py-1.5 text-sm font-mono" @input="recalc(index)">
                                        </td>
                                        <td class="text-right font-mono">
                                            <input type="hidden" :name="`lines[${index}][line_value]`" x-model="line.line_value">
                                            <span x-text="line.line_value || '0.00'"></span>
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="text-xs text-red-700 underline" @click="removeLine(index)">{{ __('Remove') }}</button>
                                        </td>
                                    </tr>
                                    <tr x-show="line.showTax" x-cloak class="bg-slate-50">
                                        <td colspan="6" class="py-2">
                                            <div class="flex flex-wrap gap-3 text-xs">
                                                <label class="flex items-center gap-1">
                                                    <span>{{ __('Kind') }}</span>
                                                    <select x-model="line.kind" class="input py-1 text-xs">
                                                        <option value="normal">{{ __('Normal') }}</option>
                                                        <option value="combo_bonus">{{ __('Combo bonus') }}</option>
                                                    </select>
                                                </label>
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
                                    <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No products yet. Search on the left and click to add.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="card grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="label">{{ __('Header discount') }}</span>
                <input type="text" name="header_discount" value="{{ old('header_discount', $document->header_discount ?? '0') }}" inputmode="decimal" class="input">
            </label>
            <label class="block">
                <span class="label">{{ __('Slab adjustment') }}</span>
                <input type="text" name="slab_adjustment" value="{{ old('slab_adjustment', $document->slab_adjustment ?? '0') }}" inputmode="decimal" class="input">
            </label>
            <label class="block">
                <span class="label">{{ __('Combo bonus discount') }}</span>
                <input type="text" name="combo_bonus_discount" value="{{ old('combo_bonus_discount', $document->combo_bonus_discount ?? '0') }}" inputmode="decimal" class="input">
            </label>
            <label class="block">
                <span class="label">{{ __('Rounding adjustment') }}</span>
                <input type="text" name="rounding_adjustment" value="{{ old('rounding_adjustment', $document->rounding_adjustment ?? '0') }}" inputmode="decimal" class="input">
            </label>
            <label class="block sm:col-span-2 lg:col-span-4">
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
        function documentForm(initial, catalog) {
            const isPurchase = @json($isPurchase);
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
            }));

            return {
                lines,
                catalog: catalog || [],
                brandFilter: '',
                productQuery: '',
                productOpen: false,
                productHighlight: 0,
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
                addProduct(product) {
                    const index = this.lines.findIndex((line) => String(line.product_id) === String(product.id));
                    if (index >= 0) {
                        const qty = parseFloat(this.lines[index].qty || '0');
                        this.lines[index].qty = String((Number.isFinite(qty) ? qty : 0) + 1);
                        this.recalc(index);
                    } else {
                        this.lines.push({
                            product_id: String(product.id),
                            kind: 'normal',
                            qty: '1',
                            bonus_qty: '0',
                            unit_price: isPurchase ? (product.buy || '') : (product.sell || product.buy || ''),
                            line_discount: '0',
                            line_value: '',
                            sd_pct: product.sd || '0',
                            vat_pct: product.vat || '15',
                            sd_amount: '0',
                            vat_amount: '0',
                            showTax: false,
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
