@extends('layouts.app')

@section('title', __('Settings'))

@php
    use App\Settings\SettingDefinition;

    $plain = collect($groups)->map(fn ($defs) => array_values(array_filter($defs, fn ($d) => ! $d->effectiveDated)))->filter();
    $dated = collect($groups)->flatten()->filter(fn ($d) => $d->effectiveDated);
@endphp

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Settings') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Every change is kept in the audit log with the old and new value.') }}</p>

    <form method="POST" action="{{ route('settings.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')
        <fieldset @disabled(! $canManage) class="space-y-6">
            @foreach ($plain as $group => $definitions)
                <section class="card" data-group="{{ $group }}">
                    <h2 class="font-semibold text-slate-900">{{ __('settings.groups.'.$group) }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($definitions as $definition)
                            @php($value = old('settings.'.$definition->key, $store->get($definition->key)))
                            <div @class(['sm:col-span-2' => $definition->type === SettingDefinition::ROLES])>
                                @if ($definition->type === SettingDefinition::ROLES)
                                    <fieldset>
                                        <legend class="label">{{ __($definition->label) }}</legend>
                                        <div class="mt-2 flex flex-wrap gap-4">
                                            @foreach ($definition->options as $option => $label)
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" name="settings[{{ $definition->key }}][]" value="{{ $option }}" class="size-4 accent-brand-600" @checked(in_array($option, (array) $value, true))>
                                                    {{ __('roles.'.$option) }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @else
                                    <label class="block">
                                        <span class="label">{{ __($definition->label) }}</span>
                                        @if ($definition->type === SettingDefinition::CHOICE)
                                            <select name="settings[{{ $definition->key }}]" class="input">
                                                @foreach ($definition->options as $option => $label)
                                                    <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ __($label) }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($definition->type === SettingDefinition::BOOLEAN)
                                            <input type="hidden" name="settings[{{ $definition->key }}]" value="0">
                                            <input type="checkbox" name="settings[{{ $definition->key }}]" value="1" class="mt-2 size-4 accent-brand-600" @checked($value)>
                                        @elseif ($definition->type === SettingDefinition::INTEGER)
                                            <input type="number" name="settings[{{ $definition->key }}]" value="{{ $value }}" class="input">
                                        @else
                                            <input type="text" name="settings[{{ $definition->key }}]" value="{{ $value }}" class="input">
                                        @endif
                                    </label>
                                @endif
                                @if ($definition->help)
                                    <p class="mt-1 text-xs text-slate-500">{{ __($definition->help) }}</p>
                                @endif
                                @unless ($store->isStored($definition->key))
                                    <p class="mt-1 text-xs text-slate-400">{{ __('Default value') }}</p>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
            @if ($canManage)
                <button class="btn-primary">{{ __('Save settings') }}</button>
            @endif
        </fieldset>
    </form>

    @foreach ($dated as $definition)
        <section class="card mt-6" data-dated="{{ $definition->key }}">
            <h2 class="font-semibold text-slate-900">{{ __($definition->label) }}</h2>
            @if ($definition->help)
                <p class="mt-1 text-sm text-slate-600">{{ __($definition->help) }}</p>
            @endif
            <p class="mt-2 text-sm">{{ __('Value today') }}: <strong>{{ is_bool($store->get($definition->key)) ? ($store->get($definition->key) ? __('Yes') : __('No')) : local_digits((string) $store->get($definition->key)) }}</strong></p>

            @php($history = $store->history($definition->key))
            @if ($history->isNotEmpty())
                <div class="table-wrap mt-3">
                    <table class="data-table">
                        <thead><tr><th scope="col">{{ __('From date') }}</th><th scope="col">{{ __('Value') }}</th><th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($history as $row)
                                <tr>
                                    <td>{{ local_digits($row->effective_from->format('d-m-Y')) }}</td>
                                    <td>{{ local_digits((string) json_decode($row->value, true)) }}</td>
                                    <td class="text-right">
                                        @if ($canManage && $row->effective_from->isFuture())
                                            <form method="POST" action="{{ route('settings.destroy-dated', [$definition->key, $row->effective_from->toDateString()]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-sm text-red-700 underline">{{ __('Remove') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($canManage)
                <form method="POST" action="{{ route('settings.update') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="dated_key" value="{{ $definition->key }}">
                    <label class="block">
                        <span class="label">{{ __('New value') }}</span>
                        <input type="text" name="value" required class="input">
                    </label>
                    <label class="block">
                        <span class="label">{{ __('Applies from') }}</span>
                        <input type="date" name="effective_from" value="{{ now()->toDateString() }}" required class="input">
                    </label>
                    <button class="btn-primary">{{ __('Add') }}</button>
                </form>
            @endif
        </section>
    @endforeach
@endsection
