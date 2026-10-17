@extends('layouts.app')

@section('title', __('Day book'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Day book') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Cash book and bank book movements together.') }}</p>
    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="label">{{ __('From') }}</span>
            <input type="date" name="from" value="{{ $from }}" class="input">
        </label>
        <label class="block">
            <span class="label">{{ __('To') }}</span>
            <input type="date" name="to" value="{{ $to }}" class="input">
        </label>
        @include('reports.partials.business-days-filter', ['businessDaysOnly' => $businessDaysOnly ?? false])
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>
    @if ($businessDaysOnly ?? false)
        <p class="mt-2 text-sm text-brand-700" data-testid="business-days-active">{{ __('Movements on weekly holidays are hidden unless marked as a working day in Settings.') }}</p>
    @endif

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="day-book-totals">
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Cash in') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['cash']['in']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Cash out') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['cash']['out']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Bank in') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['bank']['in']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Bank out') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['bank']['out']) }}</p></div>
    </div>
    <p class="mt-3 text-sm" data-testid="day-book-sum">
        {{ __('Day book total in') }}: <span class="font-mono font-semibold">{{ format_money($book['in']) }}</span>
        · {{ __('Day book total out') }}: <span class="font-mono font-semibold">{{ format_money($book['out']) }}</span>
    </p>

    <div class="mt-6 table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Book') }}</th>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Party') }}</th>
                    <th class="text-right">{{ __('In') }}</th>
                    <th class="text-right">{{ __('Out') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($book['rows'] as $row)
                    <tr>
                        <td>{{ local_digits(\Illuminate\Support\Carbon::parse($row['date'])->format('d-m-Y')) }}</td>
                        <td>{{ $row['book'] === 'cash' ? __('Cash') : __('Bank') }}</td>
                        <td class="font-mono">{{ $row['number'] }}</td>
                        <td>{{ $row['party'] }}</td>
                        <td class="text-right font-mono">{{ format_money($row['in']) }}</td>
                        <td class="text-right font-mono">{{ format_money($row['out']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No movements in this range.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
