@extends('layouts.app')

@section('title', $product->exists ? __('Edit product') : __('Add product'))

@section('content')
    <a href="{{ route('products.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to products') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $product->exists ? __('Edit product') : __('Add product') }}</h1>

    @if ($product->exists && $product->trashed())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span>{{ __('This product is deactivated.') }}</span>
            <form method="POST" action="{{ route('products.restore', $product) }}">
                @csrf
                <button class="btn-secondary">{{ __('Reactivate') }}</button>
            </form>
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
        <section class="card">
            <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @if ($product->exists)
                    @method('PUT')
                @endif
                <label class="block">
                    <span class="label">{{ __('Code') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="code" value="{{ old('code', $product->code) }}" maxlength="40" class="input font-mono">
                </label>
                <label class="block">
                    <span class="label">{{ __('Brand') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="brand" value="{{ old('brand', $product->brand) }}" maxlength="80" class="input">
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Name') }}</span>
                    <input type="text" name="name_en" value="{{ old('name_en', $product->name_en) }}" required maxlength="191" class="input">
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Bangla name') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="name_bn" value="{{ old('name_bn', $product->name_bn) }}" maxlength="191" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Pack size') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="pack_size" value="{{ old('pack_size', $product->pack_size) }}" maxlength="80" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Unit') }}</span>
                    <input type="text" name="unit" value="{{ old('unit', $product->unit) }}" required maxlength="20" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Units per carton') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="number" name="units_per_carton" value="{{ old('units_per_carton', $product->units_per_carton) }}" min="1" max="100000" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Reorder level') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level) }}" inputmode="decimal" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Buy price') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="buy_price" value="{{ old('buy_price', $product->buy_price) }}" inputmode="decimal" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Exact buy price') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="buy_price_exact" value="{{ old('buy_price_exact', $product->buy_price_exact) }}" inputmode="decimal" class="input">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('The printed price and the unrounded price are both kept.') }}</span>
                </label>
                <label class="block">
                    <span class="label">{{ __('Sell price') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="sell_price" value="{{ old('sell_price', $product->sell_price) }}" inputmode="decimal" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('SD %') }}</span>
                    <input type="text" name="sd_pct" value="{{ old('sd_pct', $product->sd_pct) }}" required inputmode="decimal" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('VAT %') }}</span>
                    <input type="text" name="vat_pct" value="{{ old('vat_pct', $product->vat_pct) }}" required inputmode="decimal" class="input">
                </label>
                <label class="flex items-center gap-2 sm:col-span-2">
                    <input type="hidden" name="tracks_expiry" value="0">
                    <input type="checkbox" name="tracks_expiry" value="1" @checked(filter_var(old('tracks_expiry', $product->tracks_expiry), FILTER_VALIDATE_BOOLEAN))>
                    <span class="text-sm font-medium text-slate-700">{{ __('Track expiry') }}</span>
                </label>
                @foreach ($fields as $field)
                    <label class="block">
                        <span class="label">{{ $field->label }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                        <input type="{{ $field->type === 'number' ? 'text' : 'text' }}" name="custom[{{ $field->key }}]" inputmode="{{ $field->type === 'number' ? 'decimal' : 'text' }}" value="{{ old('custom.'.$field->key, $product->custom[$field->key] ?? '') }}" maxlength="191" class="input">
                    </label>
                @endforeach
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Aliases') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <textarea name="aliases" rows="4" maxlength="5000" class="input">{{ old('aliases', $product->exists ? $product->aliases->pluck('name')->implode("\n") : '') }}</textarea>
                    <span class="mt-1 block text-xs text-slate-500">{{ __('One name per line. The Bangla name is kept as an alias too.') }}</span>
                </label>
                <div class="sm:col-span-2">
                    <button class="btn-primary">{{ $product->exists ? __('Save') : __('Add product') }}</button>
                </div>
            </form>
        </section>

        @can('products.delete')
            @if ($product->exists && ! $product->trashed())
                <aside>
                    <section class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Deactivate') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ __('The product stays in old documents. You can reactivate it later.') }}</p>
                        <x-delete-form
                            :action="route('products.destroy', $product)"
                            :confirm="__('Deactivate this product?')"
                            :label="__('Deactivate')"
                            class="mt-3"
                        />
                    </section>
                </aside>
            @endif
        @endcan
    </div>
@endsection
