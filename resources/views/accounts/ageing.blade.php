@extends('layouts.app')

@section('title', __('Ageing'))

@section('content')
    <x-back-link :href="$partyType === 'retailer' ? route('accounts.receivables') : route('accounts.payables')">{{ $partyType === 'retailer' ? __('Back to receivables') : __('Back to payables') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Ageing') }} · {{ $party->name }}</h1>
    <p class="text-sm text-slate-600">{{ __('As of') }} {{ local_digits(\Illuminate\Support\Carbon::parse($asOf)->format('d-m-Y')) }}</p>

    <div class="mt-6 grid gap-3 sm:grid-cols-4" data-testid="ageing-buckets">
        @foreach ($report['buckets'] as $bucket => $amount)
            <div class="card"><p class="text-xs uppercase text-slate-500">{{ $bucket }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($amount) }}</p></div>
        @endforeach
    </div>
    <p class="mt-3 text-sm">{{ __('Total') }}: <span class="font-mono font-semibold">{{ format_money($report['total']) }}</span></p>

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
                @forelse ($report['rows'] as $row)
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
