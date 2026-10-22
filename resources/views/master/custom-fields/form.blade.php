@extends('layouts.app')

@section('title', $field->exists ? __('Edit custom field') : __('Add custom field'))

@section('content')
    <x-back-link :href="route('masters.custom-fields.index')">{{ __('Back to custom fields') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $field->exists ? __('Edit custom field') : __('Add custom field') }}</h1>

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
        <section class="card max-w-xl">
            <form method="POST" action="{{ $field->exists ? route('masters.custom-fields.update', $field) : route('masters.custom-fields.store') }}" class="grid gap-4">
                @csrf
                @if ($field->exists)
                    @method('PUT')
                @endif
                <label class="block">
                    <span class="label">{{ __('Used on') }}</span>
                    <select name="entity" class="input" required>
                        <option value="product" @selected(old('entity', $field->entity) === 'product')>{{ __('Product') }}</option>
                        <option value="retailer" @selected(old('entity', $field->entity) === 'retailer')>{{ __('Retailer') }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Key') }}</span>
                    <input type="text" name="key" value="{{ old('key', $field->key) }}" required maxlength="40" pattern="[a-z][a-z0-9_]*" class="input font-mono">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('Lower case letters, digits and underscore.') }}</span>
                </label>
                <label class="block">
                    <span class="label">{{ __('Label') }}</span>
                    <input type="text" name="label" value="{{ old('label', $field->label) }}" required maxlength="80" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Type') }}</span>
                    <select name="type" class="input" required>
                        <option value="string" @selected(old('type', $field->type) === 'string')>{{ __('Text') }}</option>
                        <option value="number" @selected(old('type', $field->type) === 'number')>{{ __('Number') }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Sort') }}</span>
                    <input type="number" name="sort" value="{{ old('sort', $field->sort ?? 0) }}" min="0" max="999" class="input">
                </label>
                <button class="btn-primary w-fit">{{ $field->exists ? __('Save') : __('Add custom field') }}</button>
            </form>
        </section>

        @can('masters.delete')
            @if ($field->exists)
                <aside>
                    <section class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Remove') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ __('The question leaves the form. Answers already saved stay on the record.') }}</p>
                        <x-delete-form
                            :action="route('masters.custom-fields.destroy', $field)"
                            :confirm="__('Remove this custom field?')"
                            :label="__('Remove')"
                            class="mt-3"
                        />
                    </section>
                </aside>
            @endif
        @endcan
    </div>
@endsection
