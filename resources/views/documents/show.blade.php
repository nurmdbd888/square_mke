@extends('layouts.app')

@php
    $indexRoute = $document->isPurchase() ? 'purchases.index' : 'sales.index';
    $editRoute = $document->isPurchase() ? 'purchases.edit' : 'sales.edit';
    $postRoute = $document->isPurchase() ? 'purchases.post' : 'sales.post';
    $voidRoute = $document->isPurchase() ? 'purchases.destroy' : 'sales.destroy';
@endphp

@section('title', $document->number)

@section('content')
    <a href="{{ route($indexRoute) }}" class="text-sm text-brand-700 underline">{{ $document->isPurchase() ? __('Back to purchases') : __('Back to sales') }}</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 font-mono">{{ $document->number }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ format_app_date($document->doc_date) }}
                · {{ $document->partyLabel() }}
                · <span @class([
                    'font-medium',
                    'text-amber-700' => $document->isDraft(),
                    'text-emerald-700' => $document->isPosted(),
                    'text-slate-500' => $document->isVoid(),
                ])>{{ __(ucfirst($document->status)) }}</span>
            </p>
        </div>
        @if (! $document->isVoid())
            <div class="flex flex-wrap gap-2">
                @if ($document->isSale())
                    <a href="{{ route('sales.print.invoice', [$document, 'size' => 'a5']) }}" class="btn-secondary" target="_blank" rel="noopener">{{ __('Print invoice (A5)') }}</a>
                    <a href="{{ route('sales.print.invoice', [$document, 'size' => 'a4']) }}" class="btn-secondary" target="_blank" rel="noopener">{{ __('Print invoice (A4)') }}</a>
                    <a href="{{ route('sales.print.challan', [$document, 'size' => 'a5']) }}" class="btn-secondary" target="_blank" rel="noopener">{{ __('Print challan') }}</a>
                    <a href="{{ route('sales.print.thermal', $document) }}" class="btn-secondary" target="_blank" rel="noopener">{{ __('Print receipt') }}</a>
                @else
                    <a href="{{ route('purchases.print.challan', $document) }}" class="btn-secondary" target="_blank" rel="noopener">{{ __('Print challan') }}</a>
                @endif
            </div>
        @endif
        @if ($canManage && ! $document->isVoid())
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route($editRoute, $document) }}" class="btn-secondary">{{ __('Edit') }}</a>
                @if ($document->isDraft())
                    <form method="POST" action="{{ route($postRoute, $document) }}">
                        @csrf
                        <button class="btn-primary">{{ __('Post') }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route($voidRoute, $document) }}" data-confirm="{{ __('Void this document? Posted stock will be recomputed.') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger">{{ $document->isDraft() ? __('Delete draft') : __('Void') }}</button>
                </form>
            </div>
        @endif
    </div>

    @if ($document->isSale() && $document->isPosted())
        <section class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="sale-totals">
            <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Revenue') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($document->revenue) }}</p></div>
            <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('COGS') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($document->cogs) }}</p></div>
            <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Free goods') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($document->free_goods_cost) }}</p></div>
            <div class="card"><p class="text-xs uppercase text-slate-500">{{ __('Commission net') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ format_money($document->commission_net) }}</p></div>
        </section>
    @endif

    <div class="mt-6 table-wrap">
        <table class="data-table" data-testid="document-lines">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Product') }}</th>
                    <th class="text-right">{{ __('Qty') }}</th>
                    <th class="text-right">{{ __('Bonus') }}</th>
                    <th class="text-right">{{ __('Price') }}</th>
                    <th class="text-right">{{ __('Value') }}</th>
                    @if ($document->isSale() && $document->isPosted())
                        <th class="text-right">{{ __('Issue cost') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>
                            <span class="font-medium">{{ $line->product?->name_en }}</span>
                            @if ($line->isComboBonus())
                                <span class="ml-2 rounded bg-slate-100 px-1.5 text-xs">{{ __('Combo bonus') }}</span>
                            @endif
                            @if ($line->product?->code)
                                <span class="block font-mono text-xs text-slate-500">{{ $line->product->code }}</span>
                            @endif
                        </td>
                        <td class="text-right font-mono">{{ local_digits($line->qty) }}</td>
                        <td class="text-right font-mono">{{ local_digits($line->bonus_qty) }}</td>
                        <td class="text-right font-mono">{{ format_number($line->unit_price, 4) }}</td>
                        <td class="text-right font-mono">{{ format_money($line->line_value) }}</td>
                        @if ($document->isSale() && $document->isPosted())
                            <td class="text-right font-mono">{{ format_money($line->issue_cost ?? 0) }}@if ($line->bonus_issue_cost !== null && $line->bonus_issue_cost !== '0.00') / {{ format_money($line->bonus_issue_cost) }}@endif</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
        <div><dt class="text-slate-500">{{ __('Subtotal') }}</dt><dd class="font-mono">{{ format_money($document->subtotal) }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Net total') }}</dt><dd class="font-mono font-semibold">{{ format_money($document->net_total) }}</dd></div>
        @if ($document->notes)
            <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('Notes') }}</dt><dd>{{ $document->notes }}</dd></div>
        @endif
    </dl>

    @if ($document->attachments->isNotEmpty())
        <section class="mt-6 card">
            <h2 class="font-semibold">{{ __('Attachments') }}</h2>
            <ul class="mt-2 list-disc pl-5 text-sm">
                @foreach ($document->attachments as $file)
                    <li>{{ $file->original_name }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
