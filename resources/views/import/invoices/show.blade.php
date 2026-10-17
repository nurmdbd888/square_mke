@extends('layouts.app')

@section('title', __('Import preview'))

@section('content')
    <a href="{{ route('imports.invoices.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to invoice import') }}</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ __('Import preview') }} #{{ $batch->id }}</h1>
    <p class="text-sm text-slate-600">{{ $batch->doc_type }} · {{ $batch->original_name }} · {{ $batch->line_count }} {{ __('lines') }} · {{ $batch->error_count }} {{ __('errors') }}</p>

    <x-flash />

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
                                    @if ($batch->status === \App\Models\DocumentImportBatch::STATUS_PREVIEW)
                                        <form method="POST" action="{{ route('imports.invoices.lines.update', [$batch, $line]) }}" class="mt-2 flex flex-wrap gap-2">
                                            @csrf
                                            @method('PUT')
                                            <select name="product_id" class="input text-xs">
                                                <option value="">{{ __('Pick product') }}</option>
                                                @foreach (\App\Models\Product::query()->orderBy('code')->limit(200)->get() as $p)
                                                    <option value="{{ $p->id }}" @selected($line->product_id === $p->id)>{{ $p->code }} {{ $p->name_en }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn-secondary text-xs">{{ __('Save row') }}</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-2 py-2">{{ $line->qty }} @if ($line->bonus_qty > 0)+ {{ $line->bonus_qty }} bonus @endif</td>
                                <td class="px-2 py-2">{{ $line->unit_price }}</td>
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
