@extends('layouts.app')

@section('title', __('Price lists'))

@section('content')
    <a href="{{ route('masters.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to masters') }}</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Price lists') }}</h1>
        <div class="flex flex-wrap items-center gap-2">
            @include('import.partials.list-actions', ['entity' => 'price_lists'])
            @can('masters.manage')
                <a href="{{ route('imports.entity.index', 'price_list_items') }}" class="btn-secondary">{{ __('Import prices') }}</a>
                <a href="{{ route('masters.price-lists.create') }}" class="btn-primary">{{ __('Add price list') }}</a>
            @endcan
        </div>
    </div>
    <p class="mt-1 text-sm text-slate-600">{{ __('A list holds the sell price of each product from a date. The product sell price is used when a list has no row yet.') }}</p>

    <form method="GET" action="{{ route('masters.price-lists.index') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_10rem_auto]"
          hx-get="{{ route('masters.price-lists.index') }}" hx-target="#price-lists-table" hx-push-url="true" hx-trigger="input changed delay:400ms from:input[name=q], change from:select">
        <label class="block">
            <span class="sr-only">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name') }}" class="input mt-0">
        </label>
        <label class="block">
            <span class="sr-only">{{ __('Status') }}</span>
            <select name="status" class="input mt-0">
                @foreach (['active' => __('Active'), 'inactive' => __('Deactivated'), 'all' => __('All')] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div id="price-lists-table" class="mt-4">
        @include('master.price-lists._table')
    </div>
@endsection
