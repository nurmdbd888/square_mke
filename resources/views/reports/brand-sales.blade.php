@extends('layouts.app')

@section('title', __('Brand-wise sales'))

@section('content')
    <x-back-link :href="route('reports.index')">{{ __('Back to reports') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Brand-wise sales') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Posted sale lines grouped by product brand.') }}</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="label">{{ __('From') }}</span>
            <x-date-input name="from" :value="$from" />
        </label>
        <label class="block">
            <span class="label">{{ __('To') }}</span>
            <x-date-input name="to" :value="$to" />
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div class="mt-6 table-wrap">
        <table class="data-table" data-testid="brand-sales-table">
            <thead>
                <tr>
                    <th>{{ __('Brand') }}</th>
                    <th class="text-right">{{ __('Invoices') }}</th>
                    <th class="text-right">{{ __('Qty') }}</th>
                    <th class="text-right">{{ __('Bonus qty') }}</th>
                    <th class="text-right">{{ __('Line value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['brand'] }}</td>
                        <td class="text-right font-mono">{{ local_digits($row['invoices']) }}</td>
                        <td class="text-right font-mono">{{ local_digits($row['qty']) }}</td>
                        <td class="text-right font-mono">{{ local_digits($row['bonus_qty']) }}</td>
                        <td class="text-right font-mono">{{ format_money($row['line_value']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('No posted sales in this range.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
