@extends('layouts.app')

@section('title', __('Month locks'))

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Month locks') }}</h1>
        <a href="{{ route('reports.pl') }}" class="btn-secondary">{{ __('Profit and loss') }}</a>
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route('reports.month-locks.store') }}" class="mt-4 card flex flex-wrap items-end gap-3">
            @csrf
            <label class="block">
                <span class="label">{{ __('Year') }}</span>
                <input type="number" name="year" value="{{ now()->year }}" class="input w-28" required min="2000" max="2100">
            </label>
            <label class="block">
                <span class="label">{{ __('Month') }}</span>
                <input type="number" name="month" value="{{ now()->month }}" class="input w-20" required min="1" max="12">
            </label>
            <button class="btn-primary">{{ __('Lock month') }}</button>
        </form>
    @endif

    <div class="mt-6 table-wrap">
        <table class="data-table" data-testid="month-locks-table">
            <thead>
                <tr>
                    <th>{{ __('Month') }}</th>
                    <th>{{ __('Locked at') }}</th>
                    <th>{{ __('Locked by') }}</th>
                    @if ($isOwner)
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($locks as $lock)
                    <tr>
                        <td>{{ local_digits(sprintf('%02d-%d', $lock->month, $lock->year)) }}</td>
                        <td>{{ local_digits($lock->locked_at->format('d-m-Y H:i')) }}</td>
                        <td>{{ $lock->lockedByUser?->name ?? '—' }}</td>
                        @if ($isOwner)
                            <td class="text-right">
                                <form method="POST" action="{{ route('reports.month-locks.destroy', ['year' => $lock->year, 'month' => $lock->month]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-sm text-red-700 hover:underline">{{ __('Unlock') }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-slate-500">{{ __('No locked months.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
