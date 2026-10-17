@extends('layouts.app')

@section('title', __('Stock comparison'))

@section('content')
    <a href="{{ route('stock.comparison.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to comparisons') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $comparison->original_name }}</h1>

    <div class="mt-4 table-wrap">
        <table class="data-table" data-testid="comparison-table">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Product') }}</th>
                    <th class="text-right">{{ __('Our qty') }}</th>
                    <th class="text-right">{{ __('Their qty') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($comparison->lines as $line)
                    <tr @class([
                        'bg-amber-50' => $line->status === 'qty_diff',
                        'bg-rose-50' => in_array($line->status, ['unknown_product', 'only_ours'], true),
                    ])>
                        <td class="font-mono">{{ $line->product_code ?? '—' }}</td>
                        <td>{{ $line->product?->name_en ?? $line->product_name ?? '—' }}</td>
                        <td class="text-right font-mono">{{ $line->our_qty !== null ? local_digits($line->our_qty) : '—' }}</td>
                        <td class="text-right font-mono">{{ $line->their_qty !== null ? local_digits($line->their_qty) : '—' }}</td>
                        <td>{{ __(str_replace('_', ' ', ucfirst($line->status))) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
