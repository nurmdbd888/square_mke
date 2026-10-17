@extends('layouts.app')

@section('title', $row->exists ? __('Edit opening balance') : __('Add opening balance'))

@section('content')
    <a href="{{ route('masters.opening-balances.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to opening balances') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $row->exists ? __('Edit opening balance') : __('Add opening balance') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Opening balances are stored for later. They are not posted to stock or the books yet.') }}</p>

    @php
        $kind = old('kind', $row->kind ?: 'stock');
        $productValue = old('product', $row->product?->code ?: $row->product?->name_en);
        $retailerValue = old('retailer', $row->retailer?->code ?: $row->retailer?->name);
        $supplierValue = old('supplier', $row->supplier?->code ?: $row->supplier?->name);
    @endphp

    <section class="card mt-6 max-w-xl" x-data="{ kind: @js($kind) }">
        <form method="POST" action="{{ $row->exists ? route('masters.opening-balances.update', $row) : route('masters.opening-balances.store') }}" class="grid gap-4">
            @csrf
            @if ($row->exists)
                @method('PUT')
            @endif
            <label class="block">
                <span class="label">{{ __('Kind') }}</span>
                <select name="kind" class="input" x-model="kind" required>
                    @foreach (['stock' => __('Stock'), 'receivable' => __('Receivable'), 'payable' => __('Payable'), 'cash' => __('Cash'), 'bank' => __('Bank')] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="label">{{ __('As of') }}</span>
                <x-date-input name="as_of" :value="optional($row->as_of)->toDateString()" required />
            </label>
            <label class="block" x-show="kind === 'stock'" x-cloak>
                <span class="label">{{ __('Product code or name') }}</span>
                <input type="text" name="product" value="{{ $productValue }}" class="input">
            </label>
            <label class="block" x-show="kind === 'stock'" x-cloak>
                <span class="label">{{ __('Quantity') }}</span>
                <input type="text" name="qty" value="{{ old('qty', $row->qty) }}" inputmode="decimal" class="input">
            </label>
            <label class="block" x-show="kind === 'receivable'" x-cloak>
                <span class="label">{{ __('Retailer code or name') }}</span>
                <input type="text" name="retailer" value="{{ $retailerValue }}" class="input">
            </label>
            <label class="block" x-show="kind === 'payable'" x-cloak>
                <span class="label">{{ __('Supplier code or name') }}</span>
                <input type="text" name="supplier" value="{{ $supplierValue }}" class="input">
            </label>
            <label class="block">
                <span class="label">{{ __('Amount') }}</span>
                <input type="text" name="amount" value="{{ old('amount', $row->amount) }}" required inputmode="decimal" class="input">
            </label>
            <label class="block">
                <span class="label">{{ __('Note') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <input type="text" name="note" value="{{ old('note', $row->note) }}" maxlength="191" class="input">
            </label>
            <div class="flex flex-wrap gap-3">
                <button class="btn-primary">{{ $row->exists ? __('Save') : __('Add opening balance') }}</button>
            </div>
        </form>
    </section>

    @if ($row->exists)
        @can('masters.manage')
            <form method="POST" action="{{ route('masters.opening-balances.destroy', $row) }}" class="mt-6" data-confirm="{{ __('Remove this opening balance?') }}">
                @csrf
                @method('DELETE')
                <button class="btn-danger">{{ __('Remove') }}</button>
            </form>
        @endcan
    @endif
@endsection
