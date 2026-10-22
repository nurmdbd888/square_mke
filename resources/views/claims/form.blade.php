@extends('layouts.app')

@section('title', $claim->exists ? __('Edit claim') : __('Add claim'))

@section('content')
    <x-back-link :href="route('claims.index')">{{ __('Back to claims') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $claim->exists ? __('Edit claim') : __('Add claim') }}</h1>

    <form method="POST" action="{{ $claim->exists ? route('claims.update', $claim) : route('claims.store') }}" class="mt-6 card grid max-w-2xl gap-4">
        @csrf
        @if ($claim->exists)
            @method('PUT')
        @endif
        <label class="block">
            <span class="label">{{ __('Number') }}</span>
            <input type="text" name="number" value="{{ old('number', $claim->number) }}" required class="input font-mono">
        </label>
        <label class="block">
            <span class="label">{{ __('Date') }}</span>
            <x-date-input name="doc_date" :value="$claim->doc_date?->format('Y-m-d')" required />
        </label>
        <label class="block">
            <span class="label">{{ __('Supplier') }}</span>
            <select name="supplier_id" class="input">
                <option value="">{{ __('None') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $claim->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Amount') }}</span>
            <input type="text" name="amount" value="{{ old('amount', $claim->amount) }}" required class="input font-mono">
        </label>
        <label class="block">
            <span class="label">{{ __('Description') }}</span>
            <textarea name="description" rows="3" class="input">{{ old('description', $claim->description) }}</textarea>
        </label>
        @if ($claim->exists)
            <label class="block">
                <span class="label">{{ __('Status') }}</span>
                <select name="status" class="input">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $claim->status) === $status)>{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <button class="btn-primary w-fit">{{ __('Save') }}</button>
    </form>
@endsection
