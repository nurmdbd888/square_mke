@extends('layouts.app')

@section('title', __('Import preview'))

@section('content')
    <x-back-link :href="route('imports.invoices.index')">{{ __('Back to invoice import') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold">{{ __('Import preview') }} #{{ $batch->id }}</h1>
    <p class="text-sm text-slate-600">{{ $batch->doc_type }} · {{ $batch->original_name }} · {{ $batch->line_count }} {{ __('lines') }} · {{ $batch->error_count }} {{ __('errors') }}</p>

    <x-flash />

    @if ($batch->status === \App\Models\DocumentImportBatch::STATUS_PREVIEW)
        <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" data-testid="invoice-import-dry-run-banner">
            <p class="font-semibold">{{ __('Dry-run preview') }}</p>
            <p class="mt-1">{{ __('No invoices are posted until you confirm. Fix every red error (or rematch products) first.') }}</p>
        </div>
    @endif
    @if ($batch->status === \App\Models\DocumentImportBatch::STATUS_PROCESSING)
        <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            {{ __('This large import is still processing.') }}
            <a href="{{ route('imports.invoices.show', $batch) }}" class="underline">{{ __('Refresh preview') }}</a>
        </div>
    @endif

    @if (($batch->header_warnings ?? []) !== [])
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <ul class="list-disc pl-5">
                @foreach ($batch->header_warnings as $w)
                    <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach ($batch->invoices as $invoice)
        <section class="mt-6 card">
            <h2 class="font-semibold">{{ __('Invoice') }} {{ $invoice->invoice_no }} <span class="text-sm font-normal text-slate-500">({{ $invoice->status }})</span></h2>
            @php($importTotal = $invoice->header['net_total'] ?? $invoice->header['source_total'] ?? $invoice->header['subtotal'] ?? null)
            @if ($importTotal !== null)
                <div class="mt-3 inline-flex min-w-48 flex-col rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-emerald-950">
                    <span class="text-xs uppercase tracking-wide">{{ isset($invoice->header['net_total']) ? __('Net invoice amount') : __('Printed invoice total') }}</span>
                    <span class="text-xl font-semibold">{{ number_format((float) $importTotal, 2) }}</span>
                </div>
            @endif
            @if ($batch->status === \App\Models\DocumentImportBatch::STATUS_PREVIEW)
                <form method="POST" action="{{ route('imports.invoices.header.update', [$batch, $invoice]) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    @method('PUT')
                    <label class="block">
                        <span class="label">{{ __('Invoice number') }}</span>
                        <input name="invoice_no" value="{{ $invoice->invoice_no }}" required maxlength="64" class="input">
                    </label>
                    <label class="block">
                        <span class="label">{{ __('Invoice date') }}</span>
                        <input type="date" name="{{ $batch->isPurchase() ? 'doc_date' : 'invoice_date' }}" value="{{ $invoice->header[$batch->isPurchase() ? 'doc_date' : 'invoice_date'] ?? '' }}" required class="input">
                    </label>
                    @if ($batch->isPurchase())
                        <label class="block">
                            <span class="label">{{ __('Supplier') }}</span>
                            <input name="supplier_name" value="{{ $invoice->header['supplier_name'] ?? '' }}" maxlength="255" class="input">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Buyer') }}</span>
                            <input name="buyer_name" value="{{ $invoice->header['buyer_name'] ?? '' }}" maxlength="255" class="input">
                        </label>
                        @foreach (['header_discount' => __('Header discount'), 'slab_adjustment' => __('Loading adjustment'), 'combo_bonus_discount' => __('Combo discount'), 'tds_amount' => __('TDS'), 'rounding_adjustment' => __('Rounding adjustment')] as $key => $label)
                            <label class="block">
                                <span class="label">{{ $label }}</span>
                                <input type="number" step="0.01" name="{{ $key }}" value="{{ $invoice->header[$key] ?? 0 }}" class="input">
                            </label>
                        @endforeach
                    @else
                        <label class="block">
                            <span class="label">{{ __('Retailer code') }}</span>
                            <input name="retailer_code" value="{{ $invoice->header['retailer_code'] ?? '' }}" maxlength="64" class="input">
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Retailer name') }}</span>
                            <input name="retailer_name" value="{{ $invoice->header['retailer_name'] ?? '' }}" maxlength="255" class="input">
                        </label>
                        @foreach (['slab_adjustment' => __('Slab adjustment'), 'combo_bonus_policy' => __('Combo discount'), 'rounding_adjustment' => __('Rounding adjustment')] as $key => $label)
                            <label class="block">
                                <span class="label">{{ $label }}</span>
                                <input type="number" step="0.01" name="{{ $key }}" value="{{ $invoice->header[$key] ?? 0 }}" class="input">
                            </label>
                        @endforeach
                    @endif
                    <div class="flex items-end">
                        <button class="btn-secondary">{{ __('Save invoice details') }}</button>
                    </div>
                </form>
            @endif
            @if (($invoice->warnings ?? []) !== [])
                <ul class="mt-2 list-disc pl-5 text-sm text-amber-800">
                    @foreach ($invoice->warnings as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            @endif
            @if (($invoice->errors ?? []) !== [])
                <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
                    @foreach ($invoice->errors as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-2 py-1">#</th>
                            <th class="px-2 py-1">{{ __('Product') }}</th>
                            <th class="px-2 py-1">{{ __('Qty') }}</th>
                            <th class="px-2 py-1">{{ __('Unit price') }}</th>
                            <th class="px-2 py-1">{{ __('Discount') }}</th>
                            <th class="px-2 py-1">{{ __('Value') }}</th>
                            <th class="px-2 py-1">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr class="border-t align-top">
                                <td class="px-2 py-2">{{ $line->line_no }}</td>
                                <td class="px-2 py-2">
                                    <div>{{ $line->product_label }}</div>
                                    @if ($line->product)
                                        <div class="text-xs text-slate-500">{{ $line->product->code }} · {{ $line->product->name_en }}</div>
                                    @endif
                                    @if ($line->pack_size || $line->batch_no || $line->mrp)
                                        <div class="text-xs text-slate-500">
                                            @if ($line->pack_size) {{ __('Pack') }}: {{ $line->pack_size }} @endif
                                            @if ($line->batch_no) · {{ __('Batch') }}: {{ $line->batch_no }} @endif
                                            @if ($line->mrp) · {{ __('MRP') }}: {{ $line->mrp }} @endif
                                        </div>
                                    @endif
                                    @if ($line->provisional_cost)
                                        <div class="text-xs font-medium text-amber-800">{{ __('Provisional cost — verify before posting') }}</div>
                                    @endif
                                    @if ($batch->status === \App\Models\DocumentImportBatch::STATUS_PREVIEW)
                                        <form method="POST" action="{{ route('imports.invoices.lines.update', [$batch, $line]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                            @csrf
                                            @method('PUT')
                                            <select name="product_id" class="input text-xs">
                                                <option value="">{{ __('Pick product') }}</option>
                                                @foreach (\App\Models\Product::query()->orderBy('code')->limit(200)->get() as $p)
                                                    <option value="{{ $p->id }}" @selected($line->product_id === $p->id)>{{ $p->code }} {{ $p->name_en }}</option>
                                                @endforeach
                                            </select>
                                            <label class="text-xs">{{ __('Qty') }}<input type="number" step="0.001" name="qty" value="{{ $line->qty }}" class="input text-xs"></label>
                                            <label class="text-xs">{{ __('Bonus qty') }}<input type="number" step="0.001" name="bonus_qty" value="{{ $line->bonus_qty }}" class="input text-xs"></label>
                                            <label class="text-xs">{{ __('Unit price') }}<input type="number" step="0.0001" name="unit_price" value="{{ $line->unit_price }}" class="input text-xs"></label>
                                            <label class="text-xs">{{ __('Discount') }}<input type="number" step="0.01" name="line_discount" value="{{ $line->line_discount }}" class="input text-xs"></label>
                                            <label class="text-xs">{{ __('Value') }}<input type="number" step="0.01" name="line_value" value="{{ $line->line_value }}" class="input text-xs"></label>
                                            @if ($batch->isPurchase())
                                                <label class="text-xs">{{ __('Batch number') }}<input name="batch_no" value="{{ $line->batch_no }}" maxlength="64" class="input text-xs"></label>
                                            @endif
                                            <button class="btn-secondary text-xs">{{ __('Save row') }}</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-2 py-2">{{ $line->qty }} @if ($line->bonus_qty > 0)+ {{ $line->bonus_qty }} bonus @endif</td>
                                <td class="px-2 py-2">{{ $line->unit_price }}</td>
                                <td class="px-2 py-2">{{ $line->line_discount }}</td>
                                <td class="px-2 py-2">{{ $line->line_value }}</td>
                                <td class="px-2 py-2">
                                    @foreach ($line->errors ?? [] as $err)
                                        <div class="text-red-600">{{ $err }}</div>
                                    @endforeach
                                    @foreach ($line->warnings ?? [] as $warn)
                                        <div class="text-amber-700">{{ $warn }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @if ($batch->status === \App\Models\DocumentImportBatch::STATUS_PREVIEW)
        <form method="POST" action="{{ route('imports.invoices.confirm', $batch) }}" class="mt-6">@csrf<button class="btn-primary" @disabled($batch->error_count > 0)>{{ __('Confirm and post') }}</button></form>
    @endif
    @if ($batch->status === \App\Models\DocumentImportBatch::STATUS_POSTED)
        <form method="POST" action="{{ route('imports.invoices.undo', $batch) }}" class="mt-6">@csrf<button class="btn-danger">{{ __('Undo batch') }}</button></form>
    @endif
@endsection
