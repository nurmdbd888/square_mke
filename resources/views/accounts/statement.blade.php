@extends('layouts.app')

@section('title', __('Retailer statement'))

@section('content')
    <x-back-link :href="route('accounts.receivables')">{{ __('Back to receivables') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $party->name }}</h1>
    <p class="text-sm text-slate-600">{{ __('As of') }} {{ local_digits(\Illuminate\Support\Carbon::parse($asOf)->format('d-m-Y')) }}
        @if ($credit)
            · {{ __('Credit limit') }} {{ format_money($credit) }}
        @endif
    </p>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="statement-totals">
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Balance') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($balance['balance']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Invoices') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($balance['invoices']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Allocated') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($balance['allocated']) }}</p></div>
        <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Advance') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($balance['advances']) }}</p></div>
    </div>

    <h2 class="mt-8 font-semibold">{{ __('Ageing') }}</h2>
    <div class="mt-2 grid gap-3 sm:grid-cols-4" data-testid="ageing-buckets">
        @foreach ($ageing['buckets'] as $bucket => $amount)
            <div class="card"><p class="text-xs uppercase text-slate-500">{{ $bucket }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($amount) }}</p></div>
        @endforeach
    </div>

    <div class="mt-6 table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th class="text-right">{{ __('Days') }}</th>
                    <th>{{ __('Bucket') }}</th>
                    <th class="text-right">{{ __('Remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ageing['rows'] as $row)
                    <tr>
                        <td class="font-mono">{{ $row['number'] }}</td>
                        <td>{{ local_digits(\Illuminate\Support\Carbon::parse($row['doc_date'])->format('d-m-Y')) }}</td>
                        <td class="text-right font-mono">{{ local_digits($row['days']) }}</td>
                        <td>{{ $row['bucket'] }}</td>
                        <td class="text-right font-mono">{{ format_money($row['remaining']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('No open invoices.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
