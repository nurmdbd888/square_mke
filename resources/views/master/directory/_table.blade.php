@php
    $manage = auth()->user()->can('masters.manage');
    $bulkFields = match ($kind) {
        'suppliers' => ['phone' => __('Phone'), 'bin' => __('BIN')],
        'routes' => ['code' => __('Code')],
        'sales-reps' => ['phone' => __('Phone'), 'user_id' => __('Linked user')],
        'vehicles' => ['type' => __('Vehicle type')],
        default => [],
    };
    $span = count($meta['columns']) + 1 + ($manage ? 1 : 0);
@endphp
@if ($manage)
    <form method="POST" action="{{ route('masters.directory.bulk', $kind) }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                @if ($manage)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
                @foreach ($meta['columns'] as $label)
                    <th scope="col">{{ $label }}</th>
                @endforeach
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr @class(['bg-slate-50 text-slate-400' => $row->trashed()])>
                    @if ($manage)
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $row->id }}" class="rounded border-slate-300" aria-label="{{ $row->name ?? $row->registration ?? $row->id }}">
                        </td>
                    @endif
                    @foreach ($meta['columns'] as $key => $label)
                        <td>
                            @if ($key === 'user')
                                {{ $row->user?->name ?: '—' }}
                            @elseif ($key === 'phone' && $row->phone)
                                {{ local_digits($row->phone) }}
                            @else
                                {{ $row->{$key} ?: '—' }}
                            @endif
                            @if ($loop->first && $row->trashed())
                                <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ __('Deactivated') }}</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right">
                        @can('masters.manage')
                            <x-row-actions :edit="route('masters.directory.edit', [$kind, $row])" />
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-6 text-center text-slate-500">{{ $meta['empty'] }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($manage && $bulkFields !== [])
    <div class="mt-3 flex flex-wrap items-end gap-3" x-data="{ field: @js(array_key_first($bulkFields)) }">
        <label class="block">
            <span class="label">{{ __('Field') }}</span>
            <select name="field" class="input" x-model="field">
                @foreach ($bulkFields as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($kind === 'sales-reps')
            <label class="block" x-show="field === 'user_id'" x-cloak>
                <span class="label">{{ __('Value') }}</span>
                <select name="value" class="input" :disabled="field !== 'user_id'">
                    <option value="">{{ __('None') }}</option>
                    @foreach (\App\Models\User::query()->orderBy('name')->get(['id', 'name', 'username']) as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                    @endforeach
                </select>
            </label>
            <label class="block" x-show="field === 'phone'">
                <span class="label">{{ __('Value') }}</span>
                <input type="text" name="value" class="input" :disabled="field !== 'phone'">
            </label>
        @else
            <label class="block">
                <span class="label">{{ __('Value') }}</span>
                <input type="text" name="value" class="input">
            </label>
        @endif
        <button type="submit" class="btn-secondary" data-confirm="{{ __('Apply to selected rows?') }}">{{ __('Apply to selected') }}</button>
        <x-bulk-limit-note />
    </div>
@endif
@if ($manage)
    </form>
@endif
{{ $rows->links() }}
