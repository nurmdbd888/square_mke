@extends('layouts.app')

@section('title', __('Opening balances'))

@section('content')
    <x-back-link :href="route('masters.index')">{{ __('Back to masters') }}</x-back-link>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Opening balances') }}</h1>
        @can('masters.manage')
            <a href="{{ route('masters.opening-balances.create') }}" class="btn-primary">{{ __('Add opening balance') }}</a>
        @endcan
    </div>
    <p class="mt-1 text-sm text-slate-600">{{ __('Opening balances are stored for later. They are not posted to stock or the books yet.') }}</p>

    <form method="GET" action="{{ route('masters.opening-balances.index') }}" class="mt-4 grid gap-3 sm:grid-cols-[12rem_auto]"
          hx-get="{{ route('masters.opening-balances.index') }}" hx-target="#opening-table" hx-push-url="true" hx-trigger="change">
        <label class="block">
            <span class="sr-only">{{ __('Kind') }}</span>
            <select name="kind" class="input mt-0">
                <option value="">{{ __('All kinds') }}</option>
                @foreach (['stock' => __('Stock'), 'receivable' => __('Receivable'), 'payable' => __('Payable'), 'cash' => __('Cash'), 'bank' => __('Bank')] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['kind'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div id="opening-table" class="mt-4">
        @include('master.opening-balances._table')
    </div>
@endsection
