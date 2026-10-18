@extends('layouts.app')

@section('title', $row->exists ? $meta['edit'] : $meta['add'])

@section('content')
    <a href="{{ route('masters.directory.index', $kind) }}" class="text-sm text-brand-700 underline">{{ __('Back') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $row->exists ? $meta['edit'] : $meta['add'] }}</h1>

    @if ($row->exists && $row->trashed())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span>{{ __('This record is deactivated.') }}</span>
            <form method="POST" action="{{ route('masters.directory.restore', [$kind, $row]) }}">
                @csrf
                <button class="btn-secondary">{{ __('Reactivate') }}</button>
            </form>
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
        <section class="card">
            <form method="POST" action="{{ $row->exists ? route('masters.directory.update', [$kind, $row]) : route('masters.directory.store', $kind) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @if ($row->exists)
                    @method('PUT')
                @endif
                @foreach ($fields as $field)
                    <label class="block {{ ($field['max'] ?? 0) > 120 ? 'sm:col-span-2' : '' }}">
                        <span class="label">{{ $field['label'] }}
                            @unless ($field['required'] ?? false)
                                <span class="font-normal text-slate-500">({{ __('optional') }})</span>
                            @endunless
                        </span>
                        @if (($field['type'] ?? '') === 'select')
                            <select name="{{ $field['name'] }}" class="input">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($field['options'] as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old($field['name'], $row->{$field['name']}) === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}" value="{{ old($field['name'], $row->{$field['name']}) }}" maxlength="{{ $field['max'] }}" @if ($field['required'] ?? false) required @endif class="input">
                        @endif
                    </label>
                @endforeach
                <div class="sm:col-span-2">
                    <button class="btn-primary">{{ $row->exists ? __('Save') : $meta['add'] }}</button>
                </div>
            </form>
        </section>

        @can('masters.delete')
            @if ($row->exists && ! $row->trashed())
                <aside>
                    <section class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Deactivate') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ __('You can reactivate this later. Old documents keep the name.') }}</p>
                        <x-delete-form
                            :action="route('masters.directory.destroy', [$kind, $row])"
                            :confirm="__('Deactivate this record?')"
                            :label="__('Deactivate')"
                            class="mt-3"
                        />
                    </section>
                </aside>
            @endif
        @endcan
    </div>
@endsection
