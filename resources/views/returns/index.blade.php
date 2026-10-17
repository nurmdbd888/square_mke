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

    <div class="mt-4 table-wrap">
        <table class="data-table" data-testid="returns-table">
            <thead>
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Retailer') }}</th>
                    <th class="text-right">{{ __('Total') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notes as $note)
                    <tr>
                        <td class="font-mono">{{ $note->number }}</td>
                        <td>{{ local_digits($note->doc_date->format('d-m-Y')) }}</td>
                        <td>{{ __(str_replace('_', ' ', ucfirst($note->type))) }}</td>
                        <td>{{ $note->retailer?->name ?? '—' }}</td>
                        <td class="text-right font-mono">{{ format_money($note->net_total) }}</td>
                        <td><span class="badge">{{ __(ucfirst($note->status)) }}</span></td>
                        <td class="text-right"><a href="{{ route('returns.show', $note) }}" class="text-brand-700 underline">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No stock notes yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $notes->links() }}
@endsection
