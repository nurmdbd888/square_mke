<table class="lines">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Product') }}</th>
            <th class="text-right">{{ __('Qty') }}</th>
            <th class="text-right">{{ __('Bonus') }}</th>
            <th class="text-right">{{ __('Price') }}</th>
            <th class="text-right">{{ __('Value') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td>{{ $line->line_no }}</td>
                <td>
                    @if (app()->getLocale() === 'bn' && $line->product?->name_bn)
                        <span>{{ $line->product->name_bn }}</span>
                        <span class="meta" style="display:block;font-size:10px;">{{ $line->product->name_en }}</span>
                    @else
                        <span>{{ $line->product?->name_en }}</span>
                        @if ($line->product?->name_bn)
                            <span class="meta" style="display:block;font-size:10px;">{{ $line->product->name_bn }}</span>
                        @endif
                    @endif
                    @if ($line->isComboBonus())
                        <span style="font-size:10px;"> ({{ __('Combo bonus') }})</span>
                    @endif
                </td>
                <td class="text-right mono">{{ local_digits($line->qty) }}</td>
                <td class="text-right mono">{{ local_digits($line->bonus_qty) }}</td>
                <td class="text-right mono">{{ format_money($line->unit_price) }}</td>
                <td class="text-right mono">{{ format_money($line->line_value) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
