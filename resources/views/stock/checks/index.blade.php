@extends('layouts.app')

@section('title', __('Stock check history'))

@section('content')
    <x-back-link :href="route('stock.index')">{{ __('Back to stock') }}</x-back-link>
    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Stock check history') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Past physical checks with who confirmed, when, and IN STORE marks.') }}</p>
        </div>
        <a href="{{ route('stock.index', ['checking' => 1]) }}" class="btn-primary w-full sm:w-auto" data-testid="stock-checks-start-new">{{ __('New stock check') }}</a>
    </div>

    <div class="mt-6 table-wrap">
        <table class="data-table" data-testid="stock-checks-table">
            <thead>
                <tr>
                    <th>{{ __('When') }}</th>
                    <th>{{ __('Confirmed by') }}</th>
                    <th class="text-right">{{ __('Lines') }}</th>
                    <th class="text-right">{{ __('Available') }}</th>
                    <th class="text-right">{{ __('Not Available') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $row)
                    <tr data-testid="stock-check-row-{{ $row->id }}">
                        <td>
                            <span class="font-medium">{{ format_app_date($row->confirmed_at) }}</span>
                            <span class="block text-xs text-slate-500">{{ local_digits(optional($row->confirmed_at)->format('H:i')) }}</span>
                        </td>
                        <td>{{ $row->creator?->name ?? '—' }}</td>
                        <td class="text-right font-mono">{{ local_digits($row->lines_count) }}</td>
                        <td class="text-right font-mono text-emerald-800">{{ local_digits($row->available_count) }}</td>
                        <td class="text-right font-mono text-rose-800">{{ local_digits($row->not_available_count) }}</td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('stock.checks.show', $row) }}" class="btn-secondary px-3 py-1.5 text-xs min-h-10">{{ __('Open') }}</a>
                                <a href="{{ route('stock.checks.export', $row) }}" class="btn-primary px-3 py-1.5 text-xs min-h-10" data-testid="stock-check-export-{{ $row->id }}">{{ __('Export') }}</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No stock checks yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
