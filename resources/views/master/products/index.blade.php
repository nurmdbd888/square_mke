@extends('layouts.app')

@section('title', __('Products'))

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Products') }}</h1>
        <div class="flex flex-wrap items-center gap-2">
            @include('master.partials.list-toolbar', ['entity' => 'products', 'exportKey' => 'products'])
            @can('products.manage')
                <a href="{{ route('products.create') }}" class="btn-primary">{{ __('Add product') }}</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('products.index') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
          hx-get="{{ route('products.index') }}" hx-target="#products-table" hx-push-url="true" hx-trigger="input changed delay:400ms from:input[name=q], change from:select, change from:input[type=checkbox]">
        <input type="hidden" name="cols_set" value="1">
        <label class="block sm:col-span-2">
            <span class="sr-only">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name, code or brand') }}" class="input mt-0">
        </label>
        <label class="block">
            <span class="sr-only">{{ __('Brand') }}</span>
            <select name="brand" class="input mt-0">
                <option value="">{{ __('All brands') }}</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand }}" @selected(($filters['brand'] ?? null) === $brand)>{{ $brand }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="sr-only">{{ __('Status') }}</span>
            <select name="status" class="input mt-0">
                @foreach (['active' => __('Active'), 'inactive' => __('Deactivated'), 'all' => __('All')] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <fieldset class="sm:col-span-2 lg:col-span-4">
            <legend class="text-sm font-medium text-slate-700">{{ __('Columns') }}</legend>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                @foreach (['code' => __('Code'), 'brand' => __('Brand'), 'pack' => __('Pack'), 'buy' => __('Buy price'), 'sell' => __('Sell price'), 'sd' => __('SD %'), 'vat' => __('VAT %')] as $key => $label)
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="cols[]" value="{{ $key }}" @checked(in_array($key, $columns, true))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <button class="btn-secondary w-fit">{{ __('Filter') }}</button>
    </form>

    <div id="products-table" class="mt-4">
        @include('master.products._table')
    </div>
@endsection
