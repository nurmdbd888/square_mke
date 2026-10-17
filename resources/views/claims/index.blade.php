@extends('layouts.app')

@section('title', __('Claims'))

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Claims against Square') }}</h1>
        @if ($canManage)
            <a href="{{ route('claims.create') }}" class="btn-primary">{{ __('Add claim') }}</a>
        @endif
    </div>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block min-w-[12rem] flex-1">
            <span class="label">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input">
        </label>
        <label class="block">
            <span class="label">{{ __('Status') }}</span>
            <select name="status" class="input">
                <option value="all">{{ __('All') }}</option>
                @foreach (['open', 'submitted', 'approved', 'settled', 'rejected'] as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __(ucfirst($s)) }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div class="mt-4 table-wrap">
        <table class="data-table" data-testid="claims-table">
            <thead>
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Supplier') }}</th>
                    <th class="text-right">{{ __('Amount') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($claims as $claim)
                    <tr>
                        <td class="font-mono">{{ $claim->number }}</td>
                        <td>{{ local_date($claim->doc_date) }}</td>
                        <td>{{ $claim->supplier?->name ?? '—' }}</td>
                        <td class="text-right font-mono">{{ format_money($claim->amount) }}</td>
                        <td>{{ __(ucfirst($claim->status)) }}</td>
                        <td class="text-right"><a href="{{ route('claims.show', $claim) }}" class="text-brand-700 underline">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No claims yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $claims->links() }}
@endsection
