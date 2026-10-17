@extends('layouts.app')

@section('title', __('Returns and adjustments'))

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Returns and adjustments') }}</h1>
        @if ($canManage)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('returns.create', ['type' => 'sales_return']) }}" class="btn-primary">{{ __('Add sales return') }}</a>
                <a href="{{ route('returns.create', ['type' => 'stock_count']) }}" class="btn-secondary">{{ __('Stock count') }}</a>
            </div>
        @endif
    </div>

    <form method="GET" action="{{ route('returns.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block min-w-[12rem] flex-1">
            <span class="label">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input">
        </label>
        <label class="block">
            <span class="label">{{ __('Type') }}</span>
            <select name="type" class="input">
                <option value="sales_return" @selected(($filters['type'] ?? '') === 'sales_return')>{{ __('Sales return') }}</option>
                <option value="all" @selected(($filters['type'] ?? '') === 'all')>{{ __('All') }}</option>
                <option value="stock_count" @selected(($filters['type'] ?? '') === 'stock_count')>{{ __('Stock count') }}</option>
                <option value="adjustment" @selected(($filters['type'] ?? '') === 'adjustment')>{{ __('Adjustment') }}</option>
                <option value="loss" @selected(($filters['type'] ?? '') === 'loss')>{{ __('Loss / damage') }}</option>
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Status') }}</span>
            <select name="status" class="input">
                @foreach (['all', 'draft', 'posted', 'void'] as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s === 'all' ? __('All') : __(ucfirst($s)) }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div class="mt-4">
        @include('returns._table')
    </div>
@endsection
