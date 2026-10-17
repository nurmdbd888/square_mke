@extends('layouts.app')

@section('title', $retailer->exists ? __('Edit retailer') : __('Add retailer'))

@section('content')
    <a href="{{ route('retailers.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to retailers') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $retailer->exists ? __('Edit retailer') : __('Add retailer') }}</h1>

    @if ($retailer->exists && $retailer->trashed())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span>{{ __('This retailer is deactivated.') }}</span>
            <form method="POST" action="{{ route('retailers.restore', $retailer) }}">
                @csrf
                <button class="btn-secondary">{{ __('Reactivate') }}</button>
            </form>
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
        <section class="card">
            <form method="POST" action="{{ $retailer->exists ? route('retailers.update', $retailer) : route('retailers.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @if ($retailer->exists)
                    @method('PUT')
                @endif
                <label class="block">
                    <span class="label">{{ __('Code') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="code" value="{{ old('code', $retailer->code) }}" maxlength="40" class="input font-mono">
                </label>
                <label class="block">
                    <span class="label">{{ __('Phone') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="tel" name="phone" value="{{ old('phone', $retailer->phone) }}" maxlength="20" class="input">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('Stored as 01XXXXXXXXX.') }}</span>
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Name') }}</span>
                    <input type="text" name="name" value="{{ old('name', $retailer->name) }}" required maxlength="191" class="input">
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Address') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="address" value="{{ old('address', $retailer->address) }}" maxlength="255" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Route') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="route_id" class="input">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}" @selected((string) old('route_id', $retailer->route_id) === (string) $route->id)>{{ $route->name }}{{ $route->trashed() ? ' ('.__('Deactivated').')' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Sales rep') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="sales_rep_id" class="input">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($reps as $rep)
                            <option value="{{ $rep->id }}" @selected((string) old('sales_rep_id', $retailer->sales_rep_id) === (string) $rep->id)>{{ $rep->name }}{{ $rep->trashed() ? ' ('.__('Deactivated').')' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Price list') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <select name="price_list_id" class="input">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($priceLists as $list)
                            <option value="{{ $list->id }}" @selected((string) old('price_list_id', $retailer->price_list_id) === (string) $list->id)>{{ $list->name }}{{ $list->trashed() ? ' ('.__('Deactivated').')' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Credit limit') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="text" name="credit_limit" value="{{ old('credit_limit', $retailer->credit_limit) }}" inputmode="decimal" class="input">
                </label>
                @foreach ($fields as $field)
                    <label class="block">
                        <span class="label">{{ $field->label }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                        <input type="text" name="custom[{{ $field->key }}]" inputmode="{{ $field->type === 'number' ? 'decimal' : 'text' }}" value="{{ old('custom.'.$field->key, $retailer->custom[$field->key] ?? '') }}" maxlength="191" class="input">
                    </label>
                @endforeach
                <div class="sm:col-span-2">
                    <button class="btn-primary">{{ $retailer->exists ? __('Save') : __('Add retailer') }}</button>
                </div>
            </form>
        </section>

        @if ($retailer->exists && ! $retailer->trashed())
            <aside>
                <section class="card">
                    <h2 class="font-semibold text-slate-900">{{ __('Deactivate') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('The retailer stays on old documents. You can reactivate them later.') }}</p>
                    <form method="POST" action="{{ route('retailers.destroy', $retailer) }}" class="mt-3" data-confirm="{{ __('Deactivate this retailer?') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn-danger">{{ __('Deactivate') }}</button>
                    </form>
                </section>
            </aside>
        @endif
    </div>
@endsection
