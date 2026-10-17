@extends('layouts.app')

@php
    $title = $note->exists ? __('Edit stock note') : match ($type) {
        'sales_return' => __('Add sales return'),
        'stock_count' => __('Stock count'),
        'adjustment' => __('Stock adjustment'),
        default => __('Add loss / damage'),
    };
    $oldLines = old('lines');
    if ($oldLines === null) {
        $oldLines = $note->lines->map(fn ($l) => [
            'product_id' => $l->product_id,
            'qty' => $l->qty,
            'expected_qty' => $l->expected_qty,
            'unit_price' => $l->unit_price,
            'line_value' => $l->line_value,
            'line_condition' => $l->line_condition,
            'linked_document_line_id' => $l->linked_document_line_id,
        ])->all();
        if ($oldLines === []) {
            $oldLines = [['product_id' => '', 'qty' => '', 'unit_price' => '0', 'line_value' => '', 'line_condition' => 'good', 'linked_document_line_id' => '', 'expected_qty' => '']];
        }
    }
@endphp

@section('title', $title)

@section('content')
    <a href="{{ route('returns.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to returns') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $title }}</h1>

    <form method="POST" action="{{ $note->exists ? route('returns.update', $note) : route('returns.store') }}" class="mt-6 space-y-6">
        @csrf
        @if ($note->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="type" value="{{ $type }}">

        <section class="card grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="label">{{ __('Number') }}</span>
                <input type="text" name="number" value="{{ old('number', $note->number) }}" required class="input font-mono">
            </label>
            <label class="block">
                <span class="label">{{ __('Date') }}</span>
                <input type="date" name="doc_date" value="{{ old('doc_date', $note->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}" required class="input">
            </label>
            @if ($type === 'sales_return')
                <label class="block">
                    <span class="label">{{ __('Retailer') }}</span>
                    <select name="retailer_id" class="input" required>
                        <option value="">{{ __('Choose') }}</option>
                        @foreach ($retailers as $retailer)
                            <option value="{{ $retailer->id }}" @selected((string) old('retailer_id', $note->retailer_id) === (string) $retailer->id)>{{ $retailer->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Notes') }}</span>
                <input type="text" name="notes" value="{{ old('notes', $note->notes) }}" class="input">
            </label>
        </section>

        <section class="card space-y-3">
            <h2 class="text-lg font-semibold">{{ __('Lines') }}</h2>
            @foreach ($oldLines as $i => $line)
                <div class="grid gap-2 border-b border-slate-100 pb-3 sm:grid-cols-6">
                    <label class="block sm:col-span-2">
                        <span class="label">{{ __('Product') }}</span>
                        <select name="lines[{{ $i }}][product_id]" required class="input">
                            <option value="">{{ __('Choose') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) ($line['product_id'] ?? '') === (string) $product->id)>{{ $product->name_en }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="label">{{ __('Qty') }}</span>
                        <input type="text" name="lines[{{ $i }}][qty]" value="{{ $line['qty'] ?? '' }}" required class="input font-mono">
                    </label>
                    @if ($type === 'stock_count')
                        <label class="block">
                            <span class="label">{{ __('Expected qty') }}</span>
                            <input type="text" name="lines[{{ $i }}][expected_qty]" value="{{ $line['expected_qty'] ?? '' }}" class="input font-mono">
                        </label>
                    @endif
                    @if ($type === 'sales_return')
                        <label class="block">
                            <span class="label">{{ __('Condition') }}</span>
                            <select name="lines[{{ $i }}][line_condition]" class="input">
                                <option value="good" @selected(($line['line_condition'] ?? '') === 'good')>{{ __('Good') }}</option>
                                <option value="damaged" @selected(($line['line_condition'] ?? '') === 'damaged')>{{ __('Damaged') }}</option>
                                <option value="expired" @selected(($line['line_condition'] ?? '') === 'expired')>{{ __('Expired') }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="label">{{ __('Linked sale line id') }}</span>
                            <input type="text" name="lines[{{ $i }}][linked_document_line_id]" value="{{ $line['linked_document_line_id'] ?? '' }}" class="input font-mono" placeholder="{{ __('optional') }}">
                        </label>
                    @endif
                    <label class="block">
                        <span class="label">{{ __('Unit price') }}</span>
                        <input type="text" name="lines[{{ $i }}][unit_price]" value="{{ $line['unit_price'] ?? '0' }}" class="input font-mono">
                    </label>
                    <label class="block">
                        <span class="label">{{ __('Line value') }}</span>
                        <input type="text" name="lines[{{ $i }}][line_value]" value="{{ $line['line_value'] ?? '' }}" class="input font-mono">
                    </label>
                </div>
            @endforeach
        </section>

        <div class="flex flex-wrap gap-2">
            <button type="submit" name="action" value="save" class="btn-secondary">{{ __('Save draft') }}</button>
            <button type="submit" name="action" value="post" class="btn-primary">{{ __('Save and post') }}</button>
        </div>
    </form>
@endsection
