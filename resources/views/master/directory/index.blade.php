@extends('layouts.app')

@section('title', $meta['title'])

@section('content')
    <a href="{{ route('masters.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to masters') }}</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $meta['title'] }}</h1>
        @can('masters.manage')
            <a href="{{ route('masters.directory.create', $kind) }}" class="btn-primary">{{ $meta['add'] }}</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('masters.directory.index', $kind) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_10rem_auto]"
          hx-get="{{ route('masters.directory.index', $kind) }}" hx-target="#directory-table" hx-push-url="true" hx-trigger="input changed delay:400ms from:input[name=q], change from:select">
        <label class="block">
            <span class="sr-only">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $meta['search_label'] }}" class="input mt-0">
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

    <div id="directory-table" class="mt-4">
        @include('master.directory._table')
    </div>
@endsection
