@extends('layouts.app')

@php
    $indexRoute = $document->isPurchase() ? 'purchases.index' : 'sales.index';
    $editRoute = $document->isPurchase() ? 'purchases.edit' : 'sales.edit';
    $postRoute = $document->isPurchase() ? 'purchases.post' : 'sales.post';
    $voidRoute = $document->isPurchase() ? 'purchases.destroy' : 'sales.destroy';
@endphp

@section('title', $document->number)

@section('content')
    <x-back-link :href="route($indexRoute)">{{ $document->isPurchase() ? __('Back to purchases') : __('Back to sales') }}</x-back-link>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 font-mono">{{ $document->number }}</h1>
            <dl class="mt-2 flex flex-wrap gap-2 text-sm" data-testid="document-summary">
                <div class="rounded-md border border-slate-200 bg-white px-3 py-1.5" data-testid="document-date">
                    <dt class="text-xs text-slate-500">{{ __('Date') }}</dt>
                    <dd class="font-medium text-slate-800">{{ format_app_date($document->doc_date) }}</dd>
                </div>
                <div class="rounded-md border border-slate-200 bg-white px-3 py-1.5" data-testid="document-party">
                    <dt class="text-xs text-slate-500">{{ $document->isPurchase() ? __('Supplier') : __('Retailer') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $document->partyLabel() }}</dd>
                </div>
                <div class="rounded-md border border-slate-200 bg-white px-3 py-1.5">
                    <dt class="text-xs text-slate-500">{{ __('Status') }}</dt>
                    <dd @class([
                        'font-medium',
                        'text-amber-700' => $document->isDraft(),
                        'text-emerald-700' => $document->isPosted(),
                        'text-slate-500' => $document->isVoid(),
                    ])>{{ __(ucfirst($document->status)) }}</dd>
                </div>
            </dl>
        </div>
        @if (! $document->isVoid())
            <div class="flex flex-wrap gap-2">
                @if ($document->isSale())
                    @if ($canManage)
                        <a href="{{ route('sales.create') }}" class="btn-primary px-3 py-1.5 text-xs">{{ __('Add sale') }}</a>
                    @endif
                    <a href="{{ route('sales.print.invoice', [$document, 'size' => 'a5']) }}" class="btn-secondary px-3 py-1.5 text-xs" target="_blank" rel="noopener">{{ __('Print invoice (A5)') }}</a>
                    <a href="{{ route('sales.print.invoice', [$document, 'size' => 'a4']) }}" class="btn-secondary px-3 py-1.5 text-xs" target="_blank" rel="noopener">{{ __('Print invoice (A4)') }}</a>
                    <a href="{{ route('sales.print.challan', [$document, 'size' => 'a5']) }}" class="btn-secondary px-3 py-1.5 text-xs" target="_blank" rel="noopener">{{ __('Print challan') }}</a>
                    <a href="{{ route('sales.print.thermal', $document) }}" class="btn-secondary px-3 py-1.5 text-xs" target="_blank" rel="noopener">{{ __('Print receipt') }}</a>
                @else
                    <a href="{{ route('purchases.print.challan', $document) }}" class="btn-secondary px-3 py-1.5 text-xs" target="_blank" rel="noopener">{{ __('Print challan') }}</a>
                @endif
            </div>
        @endif
        @if ($canManage || ($canDelete ?? false))
            <div class="flex flex-wrap gap-2">
                @if ($canManage && ! $document->isVoid() && ! $document->purchase_order_id)
                    <a href="{{ route($editRoute, $document) }}" class="btn-secondary px-3 py-1.5 text-xs">{{ __('Edit') }}</a>
                    @if ($document->isDraft())
                        <form method="POST" action="{{ route($postRoute, $document) }}">
                            @csrf
                            <button class="btn-primary px-3 py-1.5 text-xs">{{ __('Post') }}</button>
                        </form>
                    @endif
                @endif
                @if (($canDelete ?? false) && ! $document->purchase_order_id)
                    <x-delete-form
                        :action="route($voidRoute, $document)"
                        :confirm="$document->isVoid()
                            ? __('Remove this voided document from the list?')
                            : ($document->isDraft() ? __('Delete this draft?') : __('Void this document? Posted stock will be recomputed.'))"
                        :label="$document->isVoid() ? __('Delete') : ($document->isDraft() ? __('Delete draft') : __('Void'))"
                        class="inline"
                    />
                @endif
            </div>
        @endif
        @if ($document->purchase_order_id)
            <p class="mt-2 w-full text-sm text-slate-600">
                {{ __('This purchase invoice was created automatically from a posted purchase-order delivery.') }}
                @if ($document->purchaseOrder)
                    <a href="{{ route('purchases.orders.show', $document->purchaseOrder) }}" class="text-brand-700 underline">{{ $document->purchaseOrder->number }}</a>
                @endif
            </p>
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

    @php
        $paidLines = $document->lines->reject(fn ($line) => $line->isComboBonus())->values();
        $bonusLines = $document->lines->filter(fn ($line) => $line->isComboBonus())->values();
    @endphp

    <div class="mt-4 table-wrap">
        <table class="data-table [&_td]:py-2 [&_th]:py-2" data-testid="document-lines">
            <thead>
                <tr>
                    <th>{{ __('SL. NO.') }}</th>
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
                @foreach ($paidLines as $line)
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>
                            <span class="font-medium">{{ $line->product?->name_en }}</span>
                            @if ($line->product?->pack_size)
                                <span class="block text-xs text-slate-600">{{ __('Pack') }}: {{ $line->product->pack_size }}</span>
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

    @if ($bonusLines->isNotEmpty())
        <h2 class="mt-5 text-lg font-semibold text-slate-900" data-testid="invoice-combo-bonus-heading">{{ __('Invoice Combo Bonus') }}</h2>
        <div class="mt-2 table-wrap">
            <table class="data-table [&_td]:py-2 [&_th]:py-2" data-testid="combo-bonus-lines">
                <thead>
                    <tr>
                        <th>{{ __('SL. NO.') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th class="text-right">{{ __('Qty') }}</th>
                        <th class="text-right">{{ __('Rate') }}</th>
                        <th class="text-right">{{ __('Value') }}</th>
                        @if ($document->isSale() && $document->isPosted())
                            <th class="text-right">{{ __('Free goods cost') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bonusLines as $i => $line)
                        <tr class="bg-amber-50/50">
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <span class="font-medium">{{ $line->product?->name_en }}</span>
                                @if ($line->product?->pack_size)
                                    <span class="block text-xs text-slate-600">{{ __('Pack') }}: {{ $line->product->pack_size }}</span>
                                @endif
                                <span class="ml-2 rounded bg-amber-100 px-1.5 text-xs text-amber-900">{{ __('Combo bonus') }}</span>
                                @if ($line->product?->code)
                                    <span class="block font-mono text-xs text-slate-500">{{ $line->product->code }}</span>
                                @endif
                            </td>
                            <td class="text-right font-mono">{{ local_digits($line->qty) }}</td>
                            <td class="text-right font-mono">{{ format_number($line->unit_price, 4) }}</td>
                            <td class="text-right font-mono">{{ format_money(0) }}</td>
                            @if ($document->isSale() && $document->isPosted())
                                <td class="text-right font-mono">{{ format_money($line->bonus_issue_cost ?? 0) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @php
        $showLoading = (float) $document->slab_adjustment != 0.0;
        $showCombo = (float) $document->combo_bonus_discount != 0.0;
        $showTds = (float) ($document->tds_amount ?? 0) != 0.0;
        $netInvoice = (float) $document->subtotal
            - (float) $document->header_discount
            - (float) $document->slab_adjustment
            - (float) $document->combo_bonus_discount;
    @endphp
    <div class="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(15rem,0.45fr)]" data-testid="invoice-adjustment-summary">
        <dl class="grid content-start gap-x-4 gap-y-2 rounded-lg border border-slate-200 bg-white p-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">{{ __('Subtotal') }}</dt><dd class="font-mono">{{ format_money($document->subtotal) }}</dd></div>
            @if ($showLoading)
                <div><dt class="text-slate-500">{{ __('Loading Adjustment') }}</dt><dd class="font-mono">−{{ format_money($document->slab_adjustment) }}</dd></div>
            @endif
            @if ($showCombo)
                <div><dt class="text-slate-500">{{ __('Combo Discount') }}</dt><dd class="font-mono">−{{ format_money($document->combo_bonus_discount) }}</dd></div>
            @endif
            @if ($showTds)
                <div><dt class="text-slate-500">{{ __('Total TDS Amount') }}</dt><dd class="font-mono">+{{ format_money($document->tds_amount) }}</dd></div>
            @endif
            @if ($document->notes)
                <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('Notes') }}</dt><dd>{{ $document->notes }}</dd></div>
            @endif
        </dl>
        <section class="flex flex-col justify-center rounded-lg border border-brand-200 bg-brand-50 px-4 py-3" data-testid="invoice-total-card">
            <p class="text-sm text-slate-600">{{ __('Net invoice amount') }}</p>
            <p class="font-mono text-lg font-semibold text-slate-900">{{ format_money($netInvoice) }}</p>
            <p class="mt-2 text-sm font-medium text-slate-700">{{ __('Total payable') }}</p>
            <p class="font-mono text-2xl font-bold text-brand-800">{{ format_money($document->net_total) }}</p>
        </section>
    </div>

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
