@extends('layouts.app')

@section('title', __('Party ledger'))

@section('content')
    <x-back-link :href="route('reports.index')">{{ __('Back to reports') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Party ledger') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Chronological invoices and payments with running balance.') }}</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3" data-testid="party-ledger-filters">
        <label class="block min-w-[16rem] flex-1">
            <span class="label">{{ __('Party') }}</span>
            <select name="party_id" class="input min-h-11" required data-testid="party-ledger-party">
                <option value="">{{ __('Choose…') }}</option>
                <optgroup label="{{ __('Retailers') }}">
                    @foreach ($retailers as $option)
                        <option value="r-{{ $option->id }}" @selected($party === 'retailer' && (string) $partyId === (string) $option->id)>
                            {{ $option->code ? $option->code.' · ' : '' }}{{ $option->name }}
                        </option>
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('Suppliers') }}">
                    @foreach ($suppliers as $option)
                        <option value="s-{{ $option->id }}" @selected($party === 'supplier' && (string) $partyId === (string) $option->id)>
                            {{ $option->code ? $option->code.' · ' : '' }}{{ $option->name }}
                        </option>
                    @endforeach
                </optgroup>
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('From') }}</span>
            <x-date-input name="from" :value="$from" />
        </label>
        <label class="block">
            <span class="label">{{ __('To') }}</span>
            <x-date-input name="to" :value="$to" />
        </label>
        <button class="btn-secondary min-h-11">{{ __('Filter') }}</button>
    </form>

    @if ($ledger && $selected)
        <section class="mt-6 grid gap-3 sm:grid-cols-3" data-testid="party-ledger-summary">
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Opening') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($ledger['opening']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Closing') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($ledger['closing']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Party') }}</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $selected->name }}</p>
            </div>
        </section>

        <div class="mt-4 table-wrap">
            <table class="data-table" data-testid="party-ledger-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Number') }}</th>
                        <th class="text-right">{{ __('Debit') }}</th>
                        <th class="text-right">{{ __('Credit') }}</th>
                        <th class="text-right">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-slate-50">
                        <td colspan="3" class="font-medium">{{ __('Opening balance') }}</td>
                        <td class="text-right font-mono">—</td>
                        <td class="text-right font-mono">—</td>
                        <td class="text-right font-mono">{{ format_money($ledger['opening']) }}</td>
                    </tr>
                    @forelse ($ledger['entries'] as $row)
                        <tr>
                            <td>{{ format_app_date($row['date']) }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="font-mono">
                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" class="text-brand-700 underline">{{ $row['number'] }}</a>
                                @else
                                    {{ $row['number'] }}
                                @endif
                            </td>
                            <td class="text-right font-mono">{{ $row['debit'] === '0.00' ? '—' : format_money($row['debit']) }}</td>
                            <td class="text-right font-mono">{{ $row['credit'] === '0.00' ? '—' : format_money($row['credit']) }}</td>
                            <td class="text-right font-mono">{{ format_money($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No movements in this range.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
