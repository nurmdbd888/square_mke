@extends('layouts.app')

@php
    $title = $type === 'purchase' ? __('Purchases') : __('Sales');
    $createRoute = $type === 'purchase' ? 'purchases.create' : 'sales.create';
    $indexRoute = $type === 'purchase' ? 'purchases.index' : 'sales.index';
@endphp

@section('title', $title)

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
            @if ($canManage)
                <a href="{{ route('imports.invoices.index') }}" class="btn-secondary w-full sm:w-auto">{{ __('Import from Excel') }}</a>
                <a href="{{ route($createRoute) }}" class="btn-primary w-full sm:w-auto">{{ $type === 'purchase' ? __('Add purchase') : __('Add sale') }}</a>
            @endif
            @if ($canDelete)
                {{-- Associated with #documents-bulk-form (checkboxes + CSRF live in the table partial). --}}
                <button
                    type="submit"
                    form="documents-bulk-form"
                    name="action"
                    value="void"
                    class="btn-danger w-full sm:w-auto"
                    data-testid="documents-bulk-delete"
                    data-bulk-delete-form="documents-bulk-form"
                    data-bulk-delete-action="void"
                    data-bulk-delete-action-url="{{ route($type === 'purchase' ? 'purchases.bulk' : 'sales.bulk') }}"
                    data-confirm="{{ __('Void posted documents, or remove drafts and already-void documents from the list?') }}"
                    data-empty="{{ __('Select at least one row to delete.') }}"
                >{{ __('Delete') }}</button>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route($indexRoute) }}" class="mt-4 flex flex-wrap items-end gap-3"
          hx-get="{{ route($indexRoute) }}" hx-target="#documents-table" hx-trigger="change delay:200ms from:select, keyup changed delay:300ms from:input[name=q]" hx-push-url="true">
        <label class="block min-w-[12rem] flex-1">
            <span class="label">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number or party') }}" class="input">
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
        @if ($type === 'sale')
            <label class="block">
                <span class="label">{{ __('From date') }}</span>
                <x-date-input name="from" :value="$filters['from'] ?? ''" />
            </label>
            <label class="block">
                <span class="label">{{ __('To date') }}</span>
                <x-date-input name="to" :value="$filters['to'] ?? ''" />
            </label>
        @endif
        <button type="submit" class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div id="documents-table" class="mt-4" data-testid="documents-table">
        @include('documents._table')
    </div>
@endsection
