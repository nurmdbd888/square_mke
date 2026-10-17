@extends('layouts.app')

@section('title', __('Payments'))

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Payments') }}</h1>
        @if ($canManage || ($canCollect ?? false))
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('payments.create', ['kind' => 'receipt']) }}" class="btn-primary">{{ __('Add receipt') }}</a>
                @if ($canManage)
                    <a href="{{ route('payments.create', ['kind' => 'payment']) }}" class="btn-secondary">{{ __('Add payment') }}</a>
                @endif
            </div>
        @endif
    </div>

    <form method="GET" action="{{ route('payments.index') }}" class="mt-4 flex flex-wrap items-end gap-3"
          hx-get="{{ route('payments.index') }}" hx-target="#payments-table" hx-trigger="change delay:200ms from:select, keyup changed delay:300ms from:input[name=q]" hx-push-url="true">
        <label class="block min-w-[12rem] flex-1">
            <span class="label">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number or party') }}" class="input">
        </label>
        <label class="block">
            <span class="label">{{ __('Kind') }}</span>
            <select name="kind" class="input">
                <option value="all" @selected(($filters['kind'] ?? '') === 'all')>{{ __('All') }}</option>
                <option value="receipt" @selected(($filters['kind'] ?? '') === 'receipt')>{{ __('Receipt') }}</option>
                <option value="payment" @selected(($filters['kind'] ?? '') === 'payment')>{{ __('Payment') }}</option>
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Status') }}</span>
            <select name="status" class="input">
                <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('All') }}</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Draft') }}</option>
                <option value="posted" @selected(($filters['status'] ?? '') === 'posted')>{{ __('Posted') }}</option>
                <option value="void" @selected(($filters['status'] ?? '') === 'void')>{{ __('Void') }}</option>
            </select>
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div id="payments-table" class="mt-4" data-testid="payments-table">
        @include('payments._table')
    </div>
@endsection
