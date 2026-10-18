@php
    $manage = auth()->user()->can('products.manage');
    $canDelete = auth()->user()->can('products.delete');
    $span = 2 + count($columns) + ($manage || $canDelete ? 1 : 0);
@endphp
@if ($manage)
    <form method="POST" action="{{ route('products.bulk') }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table" data-testid="products-table">
        <thead>
            <tr>
                @if ($manage)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
                <th scope="col">{{ __('Name') }}</th>
                @foreach (['code' => __('Code'), 'brand' => __('Brand'), 'pack' => __('Pack'), 'buy' => __('Buy price'), 'sell' => __('Sell price'), 'sd' => __('SD %'), 'vat' => __('VAT %')] as $key => $label)
                    @if (in_array($key, $columns, true))
                        <th scope="col">{{ $label }}</th>
                    @endif
                @endforeach
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($products as $row)
                <tr data-product="{{ $row->code }}" @class(['bg-slate-50 text-slate-400' => $row->trashed()])>
                    @if ($manage)
                        <td><input type="checkbox" name="ids[]" value="{{ $row->id }}" aria-label="{{ $row->name_en }}"></td>
                    @endif
                    <td>
                        <span class="font-medium text-slate-900">{{ $row->name_en }}</span>
                        @if ($row->trashed())
                            <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ __('Deactivated') }}</span>
                        @endif
                        @if ($row->name_bn)
                            <span class="block text-xs text-slate-500">{{ $row->name_bn }}</span>
                        @endif
                    </td>
                    @if (in_array('code', $columns, true))
                        <td class="font-mono" data-col="code">{{ $row->code ?: '—' }}</td>
                    @endif
                    @if (in_array('brand', $columns, true))
                        <td>{{ $row->brand ?: '—' }}</td>
                    @endif
                    @if (in_array('pack', $columns, true))
                        <td>{{ $row->pack_size ?: '—' }}</td>
                    @endif
                    @if (in_array('buy', $columns, true))
                        <td>
                            {{ $row->buy_price !== null ? format_number($row->buy_price, 2) : '—' }}
                            @if ($row->buy_price_exact !== null)
                                <span class="block text-xs text-slate-500">{{ format_number($row->buy_price_exact, 4) }}</span>
                            @endif
                        </td>
                    @endif
                    @if (in_array('sell', $columns, true))
                        <td>{{ $row->sell_price !== null ? format_number($row->sell_price, 2) : '—' }}</td>
                    @endif
                    @if (in_array('sd', $columns, true))
                        <td>{{ format_number($row->sd_pct, 2) }}</td>
                    @endif
                    @if (in_array('vat', $columns, true))
                        <td>{{ format_number($row->vat_pct, 2) }}</td>
                    @endif
                    <td class="text-right">
                        @if ($manage || $canDelete)
                            <x-row-actions :edit="$manage ? route('products.edit', $row) : null">
                                @if ($canDelete && ! $row->trashed())
                                    <x-delete-form
                                        :action="route('products.destroy', $row)"
                                        :confirm="__('Deactivate this product?')"
                                        :label="__('Deactivate')"
                                    />
                                @endif
                            </x-row-actions>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-6 text-center text-slate-500">{{ __('No products match.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($manage)
    <div class="mt-3 flex flex-wrap items-end gap-3" x-data="{ field: 'brand' }">
        <label class="block">
            <span class="label">{{ __('Field') }}</span>
            <select name="field" class="input" x-model="field">
                <option value="brand">{{ __('Brand') }}</option>
                <option value="sd_pct">{{ __('SD %') }}</option>
                <option value="vat_pct">{{ __('VAT %') }}</option>
                <option value="reorder_level">{{ __('Reorder level') }}</option>
                <option value="tracks_expiry">{{ __('Track expiry') }}</option>
            </select>
        </label>
        <label class="block" x-show="field !== 'tracks_expiry'">
            <span class="label">{{ __('Value') }}</span>
            <input type="text" name="value" class="input" :disabled="field === 'tracks_expiry'">
        </label>
        <label class="block" x-show="field === 'tracks_expiry'" x-cloak>
            <span class="label">{{ __('Value') }}</span>
            <select name="value" class="input" :disabled="field !== 'tracks_expiry'">
                <option value="1">{{ __('Yes') }}</option>
                <option value="0">{{ __('No') }}</option>
            </select>
        </label>
        <button class="btn-secondary">{{ __('Apply to selected') }}</button>
        <span class="pb-2 text-xs text-slate-500">{{ __('At most 100 rows.') }}</span>
    </div>
    </form>
@endif
{{ $products->links() }}
