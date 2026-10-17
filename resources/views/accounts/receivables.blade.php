@extends('layouts.app')

@section('title', __('Receivables'))

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Receivables') }}</h1>
        <form method="GET" class="flex items-end gap-2">
            <label class="block">
                <span class="label">{{ __('As of') }}</span>
                <x-date-input name="as_of" :value="$asOf" />
            </label>
            <button class="btn-secondary">{{ __('Filter') }}</button>
        </form>
    </div>

    <div class="mt-4 table-wrap">
        <table class="data-table" data-testid="receivables-table">
            <thead>
                <tr>
                    <th>{{ __('Retailer') }}</th>
                    <th class="text-right">{{ __('Balance') }}</th>
                    <th class="text-right">{{ __('0–30') }}</th>
                    <th class="text-right">{{ __('31–60') }}</th>
                    <th class="text-right">{{ __('61–90') }}</th>
                    <th class="text-right">{{ __('90+') }}</th>
                    <th class="text-right">{{ __('Advance') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['party']->name }}</td>
                        <td class="text-right font-mono">{{ format_money($row['balance']['balance']) }}</td>
                        <td class="text-right font-mono">{{ format_money($row['ageing']['buckets']['0-30']) }}</td>
                        <td class="text-right font-mono">{{ format_money($row['ageing']['buckets']['31-60']) }}</td>
                        <td class="text-right font-mono">{{ format_money($row['ageing']['buckets']['61-90']) }}</td>
                        <td class="text-right font-mono">{{ format_money($row['ageing']['buckets']['90+']) }}</td>
                        <td class="text-right font-mono">{{ format_money($row['balance']['advances']) }}</td>
                        <td class="text-right"><a href="{{ route('accounts.statement', $row['party']) }}" class="text-brand-700 underline">{{ __('Statement') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">{{ __('No outstanding receivables.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
