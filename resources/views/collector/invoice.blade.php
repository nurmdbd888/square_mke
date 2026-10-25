@extends('layouts.app')

@section('title', __('Collect invoice :number', ['number' => $invoice->number]))

@section('content')
    <x-back-link :href="route('collector.index')">{{ __('Back to Collections') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Collect invoice') }}</h1>

    <section class="mt-5 card" data-testid="collection-invoice-details">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase text-slate-500">{{ __('Sales invoice') }}</p>
                <h2 class="mt-1 font-mono text-xl font-semibold text-slate-900">{{ $invoice->number }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ format_app_date($invoice->doc_date) }} · {{ $invoice->retailer?->name }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase text-slate-500">{{ __('Invoice total') }}</p>
                <p class="font-mono text-lg font-semibold">{{ format_money($invoice->net_total) }}</p>
            </div>
        </div>

        <div class="mt-4 table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th class="text-right">{{ __('Quantity') }}</th>
                        <th class="text-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lines as $line)
                        <tr>
                            <td>{{ $line->product?->name_en }}</td>
                            <td class="text-right font-mono">{{ local_digits($line->qty) }}</td>
                            <td class="text-right font-mono">{{ format_money($line->line_value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <form method="POST" action="{{ route('collector.store', $invoice) }}" class="mt-4 card max-w-xl"
          x-data="{ amount: '', due: {{ \Illuminate\Support\Js::from((string) $remaining) }} }"
          data-testid="collection-payment-form">
        @csrf
        <label class="block">
            <span class="label">{{ __('Amount collected') }}</span>
            <input type="number" name="amount" x-model="amount" min="0.01" max="{{ $remaining }}" step="0.01"
                   required inputmode="decimal" class="input min-h-12 font-mono text-lg"
                   data-testid="collection-amount">
        </label>
        @error('amount')
            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror
        <div class="mt-4 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-3">
            <span class="text-sm font-medium text-slate-700">{{ __('Balance due') }}</span>
            <span class="font-mono text-lg font-semibold text-slate-900" data-testid="collection-balance-due">{{ format_money($remaining) }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between px-3">
            <span class="text-sm text-slate-600">{{ __('Due after this collection') }}</span>
            <span class="font-mono font-semibold text-slate-700"><span data-testid="collection-balance-after" x-text="Math.max(0, Number(due) - (Number(amount) || 0)).toFixed(2)">{{ format_number($remaining) }}</span></span>
        </div>
        <button type="submit" class="btn-primary mt-5 min-h-12 w-full" data-testid="collection-submit">{{ __('Save collection') }}</button>
    </form>
@endsection
