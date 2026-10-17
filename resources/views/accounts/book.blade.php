@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
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

    <div class="mt-4 grid gap-3 sm:grid-cols-4" data-testid="book-totals">
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Opening') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['opening']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('In') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['in']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Out') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['out']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Closing') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($book['closing']) }}</p></div>
    </div>

    <div class="mt-6 table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Party') }}</th>
                    <th>{{ __('Method') }}</th>
                    <th class="text-right">{{ __('In') }}</th>
                    <th class="text-right">{{ __('Out') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($book['rows'] as $row)
                    <tr>
                        <td>{{ local_digits(\Illuminate\Support\Carbon::parse($row['date'])->format('d-m-Y')) }}</td>
                        <td class="font-mono">{{ $row['number'] }}</td>
                        <td>{{ $row['party'] }}</td>
                        <td>{{ \App\Domain\Payments\Methods::label($row['method']) }}</td>
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
