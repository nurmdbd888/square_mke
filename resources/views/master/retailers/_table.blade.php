@php
    $manage = auth()->user()->can('retailers.manage');
    $span = 2 + count($columns) + ($manage ? 1 : 0);
@endphp
@if ($manage)
    <form method="POST" action="{{ route('retailers.bulk') }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table" data-testid="retailers-table">
        <thead>
            <tr>
                @if ($manage)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
                <th scope="col">{{ __('Name') }}</th>
                @foreach (['code' => __('Code'), 'phone' => __('Phone'), 'route' => __('Route'), 'rep' => __('Sales rep'), 'price_list' => __('Price list'), 'credit' => __('Credit limit')] as $key => $label)
                    @if (in_array($key, $columns, true))
                        <th scope="col">{{ $label }}</th>
                    @endif
                @endforeach
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($retailers as $row)
                <tr @class(['bg-slate-50 text-slate-400' => $row->trashed()])>
                    @if ($manage)
                        <td><input type="checkbox" name="ids[]" value="{{ $row->id }}" aria-label="{{ $row->name }}"></td>
                    @endif
                    <td>
                        <span class="font-medium text-slate-900">{{ $row->name }}</span>
                        @if ($row->trashed())
                            <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ __('Deactivated') }}</span>
                        @endif
                        @if ($row->address)
                            <span class="block text-xs text-slate-500">{{ $row->address }}</span>
                        @endif
                    </td>
                    @if (in_array('code', $columns, true))
                        <td class="font-mono">{{ $row->code ?: '—' }}</td>
                    @endif
                    @if (in_array('phone', $columns, true))
                        <td>{{ $row->phone ? local_digits($row->phone) : '—' }}</td>
                    @endif
                    @if (in_array('route', $columns, true))
                        <td>{{ $row->route?->name ?: '—' }}</td>
                    @endif
                    @if (in_array('rep', $columns, true))
                        <td>{{ $row->salesRep?->name ?: '—' }}</td>
                    @endif
                    @if (in_array('price_list', $columns, true))
                        <td>{{ $row->priceList?->name ?: '—' }}</td>
                    @endif
                    @if (in_array('credit', $columns, true))
                        <td>{{ $row->credit_limit !== null ? format_money($row->credit_limit) : '—' }}</td>
                    @endif
                    <td class="text-right">
                        @if ($manage)
                            <a href="{{ route('retailers.edit', $row) }}" class="font-medium text-brand-700 underline">{{ __('Edit') }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-6 text-center text-slate-500">{{ __('No retailers match.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($manage)
    <div class="mt-3 flex flex-wrap items-end gap-3" x-data="{ field: 'route_id' }">
        <label class="block">
            <span class="label">{{ __('Field') }}</span>
            <select name="field" class="input" x-model="field">
                <option value="route_id">{{ __('Route') }}</option>
                <option value="sales_rep_id">{{ __('Sales rep') }}</option>
                <option value="price_list_id">{{ __('Price list') }}</option>
                <option value="credit_limit">{{ __('Credit limit') }}</option>
            </select>
        </label>
        <label class="block" x-show="field === 'route_id'">
            <span class="label">{{ __('Value') }}</span>
            <select name="value" class="input" :disabled="field !== 'route_id'">
                <option value="">{{ __('None') }}</option>
                @foreach ($routes as $route)
                    <option value="{{ $route->id }}">{{ $route->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block" x-show="field === 'sales_rep_id'" x-cloak>
            <span class="label">{{ __('Value') }}</span>
            <select name="value" class="input" :disabled="field !== 'sales_rep_id'">
                <option value="">{{ __('None') }}</option>
                @foreach ($reps as $rep)
                    <option value="{{ $rep->id }}">{{ $rep->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block" x-show="field === 'price_list_id'" x-cloak>
            <span class="label">{{ __('Value') }}</span>
            <select name="value" class="input" :disabled="field !== 'price_list_id'">
                <option value="">{{ __('None') }}</option>
                @foreach ($priceLists as $list)
                    <option value="{{ $list->id }}">{{ $list->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block" x-show="field === 'credit_limit'" x-cloak>
            <span class="label">{{ __('Value') }}</span>
            <input type="text" name="value" inputmode="decimal" class="input" :disabled="field !== 'credit_limit'">
        </label>
        <button class="btn-secondary">{{ __('Apply to selected') }}</button>
        <span class="pb-2 text-xs text-slate-500">{{ __('At most 100 rows.') }}</span>
    </div>
    </form>
@endif
{{ $retailers->links() }}
