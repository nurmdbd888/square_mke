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
        if ($oldLines === []) {
            $oldLines = [['product_id' => '', 'kind' => 'normal', 'qty' => '', 'bonus_qty' => '0', 'unit_price' => '', 'line_discount' => '0', 'line_value' => '', 'sd_pct' => '0', 'vat_pct' => '15', 'sd_amount' => '0', 'vat_amount' => '0']];
        }
    }
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
        x-data="documentForm({{ Js::from(array_values($oldLines)) }})"
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
            <label class="block">
                <span class="label">{{ $isPurchase ? __('Supplier') : __('Retailer') }}</span>
                <select name="{{ $isPurchase ? 'supplier_id' : 'retailer_id' }}" required class="input">
                    <option value="">{{ __('Choose') }}</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected((string) old($isPurchase ? 'supplier_id' : 'retailer_id', $isPurchase ? $document->supplier_id : $document->retailer_id) === (string) $party->id)>
                            {{ $party->code ? $party->code.' · ' : '' }}{{ $party->name }}
                        </option>
                    @endforeach
                </select>
            </label>
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

        <section class="card">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-slate-900">{{ __('Lines') }}</h2>
                <button type="button" class="btn-secondary" @click="addLine()">{{ __('Add line') }}</button>
            </div>
            <div class="mt-4 space-y-4">
                <template x-for="(line, index) in lines" :key="index">
                    <div class="grid gap-3 rounded-lg border border-slate-200 p-3 lg:grid-cols-6">
                        <label class="block lg:col-span-2">
                            <span class="label">{{ __('Product') }}</span>
                            <select :name="`lines[${index}][product_id]`" x-model="line.product_id" required class="input" @change="fillProduct(index)">
                                <option value="">{{ __('Choose') }}</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}"
                                            data-buy="{{ $product->buy_price }}"
                                            data-sell="{{ $product->sell_price }}"
                                            data-sd="{{ $product->sd_pct }}"
                                            data-vat="{{ $product->vat_pct }}">
                                        {{ $product->code ? $product->code.' · ' : '' }}{{ $product->name_en }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Kind') }}</span>
                            <select :name="`lines[${index}][kind]`" x-model="line.kind" class="input">
                                <option value="normal">{{ __('Normal') }}</option>
                                <option value="combo_bonus">{{ __('Combo bonus') }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Qty') }}</span>
                            <input type="text" :name="`lines[${index}][qty]`" x-model="line.qty" required inputmode="decimal" class="input" @change="recalc(index)">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Bonus qty') }}</span>
                            <input type="text" :name="`lines[${index}][bonus_qty]`" x-model="line.bonus_qty" inputmode="decimal" class="input">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Unit price') }}</span>
                            <input type="text" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" inputmode="decimal" class="input" @change="recalc(index)">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Line discount') }}</span>
                            <input type="text" :name="`lines[${index}][line_discount]`" x-model="line.line_discount" inputmode="decimal" class="input" @change="recalc(index)">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Line value') }}</span>
                            <input type="text" :name="`lines[${index}][line_value]`" x-model="line.line_value" inputmode="decimal" class="input">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('SD %') }}</span>
                            <input type="text" :name="`lines[${index}][sd_pct]`" x-model="line.sd_pct" inputmode="decimal" class="input">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('VAT %') }}</span>
                            <input type="text" :name="`lines[${index}][vat_pct]`" x-model="line.vat_pct" inputmode="decimal" class="input">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('SD amount') }}</span>
                            <input type="text" :name="`lines[${index}][sd_amount]`" x-model="line.sd_amount" inputmode="decimal" class="input">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('VAT amount') }}</span>
                            <input type="text" :name="`lines[${index}][vat_amount]`" x-model="line.vat_amount" inputmode="decimal" class="input">
                        </label>
                        <div class="flex items-end lg:col-span-6">
                            <button type="button" class="text-sm text-red-700 underline" @click="removeLine(index)" x-show="lines.length > 1">{{ __('Remove line') }}</button>
                        </div>
                    </div>
                </template>
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
        function documentForm(initial) {
            const isPurchase = @json($isPurchase);
            return {
                lines: initial,
                addLine() {
                    this.lines.push({ product_id: '', kind: 'normal', qty: '', bonus_qty: '0', unit_price: '', line_discount: '0', line_value: '', sd_pct: '0', vat_pct: '15', sd_amount: '0', vat_amount: '0' });
                },
                removeLine(index) {
                    if (this.lines.length > 1) this.lines.splice(index, 1);
                },
                fillProduct(index) {
                    const select = document.querySelector(`select[name="lines[${index}][product_id]"]`);
                    const option = select?.selectedOptions?.[0];
                    if (!option) return;
                    const line = this.lines[index];
                    line.unit_price = isPurchase ? (option.dataset.buy || '') : (option.dataset.sell || option.dataset.buy || '');
                    line.sd_pct = option.dataset.sd || '0';
                    line.vat_pct = option.dataset.vat || '15';
                    this.recalc(index);
                },
                recalc(index) {
                    const line = this.lines[index];
                    const qty = parseFloat(line.qty || '0');
                    const price = parseFloat(line.unit_price || '0');
                    const discount = parseFloat(line.line_discount || '0');
                    if (!Number.isFinite(qty) || !Number.isFinite(price)) return;
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
