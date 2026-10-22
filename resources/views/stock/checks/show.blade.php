@extends('layouts.app')

@section('title', __('Stock check'))

@section('content')
    <x-back-link :href="route('stock.checks.index')">{{ __('Back to stock checks') }}</x-back-link>
    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Stock check') }} #{{ local_digits($check->id) }}</h1>
            <p class="mt-1 text-sm text-slate-600" data-testid="stock-check-meta">
                {{ __('Confirmed by :name on :when', [
                    'name' => $check->creator?->name ?? '—',
                    'when' => format_app_date($check->confirmed_at).' '.local_digits(optional($check->confirmed_at)->format('H:i')),
                ]) }}
            </p>
            <p class="mt-1 text-sm text-slate-600">
                {{ __('Available') }}: <span class="font-mono font-semibold text-emerald-800">{{ local_digits($availableCount) }}</span>
                · {{ __('Not Available') }}: <span class="font-mono font-semibold text-rose-800">{{ local_digits($notAvailableCount) }}</span>
            </p>
        </div>
        <a href="{{ route('stock.checks.export', $check) }}" class="btn-primary w-full sm:w-auto" data-testid="stock-check-export">{{ __('Export report') }}</a>
    </div>

    <div class="mt-6 table-wrap">
        <table class="data-table" data-testid="stock-check-lines">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Product') }}</th>
                    <th class="text-right">{{ __('On hand') }}</th>
                    <th class="text-right">{{ __('Stock value') }}</th>
                    <th>{{ __('IN STORE') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($check->lines->sortBy(fn ($line) => $line->product?->name_en ?? '') as $line)
                    <tr @class([
                        'bg-emerald-50/50' => $line->in_store === \App\Models\StockCheckLine::IN_STORE_AVAILABLE,
                        'bg-rose-50/50' => $line->in_store === \App\Models\StockCheckLine::IN_STORE_NOT_AVAILABLE,
                    ])>
                        <td class="font-mono">{{ $line->product?->code ?? '—' }}</td>
                        <td>{{ $line->product?->name_en ?? '—' }}</td>
                        <td class="text-right font-mono">{{ local_digits($line->on_hand_qty) }}</td>
                        <td class="text-right font-mono">{{ format_money($line->on_hand_value) }}</td>
                        <td>
                            @if ($line->in_store === \App\Models\StockCheckLine::IN_STORE_AVAILABLE)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('Available') }}</span>
                            @else
                                <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900">{{ __('Not Available') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
