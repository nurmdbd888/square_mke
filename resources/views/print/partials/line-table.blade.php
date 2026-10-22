@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\DocumentLine> $lines */
    $forceZeroAmounts = $forceZeroAmounts ?? false;
    $showSubtotal = $showSubtotal ?? false;
@endphp

@if (! empty($sectionTitle))
    <p class="section-title" style="margin: 14px 0 6px; font-weight: 700; font-size: 12px;">{{ $sectionTitle }}</p>
@endif

<table class="lines">
    <thead>
        <tr>
            <th>{{ __('SL. NO.') }}</th>
            <th>{{ __('Product') }}</th>
            <th>{{ __('Pack') }}</th>
            <th class="text-right">{{ __('Rate') }}</th>
            <th class="text-right">{{ __('Qty') }}</th>
            <th class="text-right">{{ __('Tax') }}</th>
            <th class="text-right">{{ __('Disc.') }}</th>
            <th class="text-right">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $index => $line)
            <tr>
                <td>{{ $index + 1 }}</td>
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
                </td>
                <td>{{ $line->product?->pack_size ?: ($line->product?->unit ?: '—') }}</td>
                <td class="text-right mono">{{ format_money($line->unit_price) }}</td>
                <td class="text-right mono">{{ local_digits($line->qty) }}</td>
                <td class="text-right mono">{{ format_money($forceZeroAmounts ? 0 : $line->vat_amount) }}</td>
                <td class="text-right mono">{{ format_money($forceZeroAmounts ? 0 : $line->line_discount) }}</td>
                <td class="text-right mono">{{ format_money($forceZeroAmounts ? 0 : $line->line_value) }}</td>
            </tr>
        @endforeach
        @if ($lines->isEmpty())
            <tr>
                <td colspan="8" class="meta">{{ __('No lines.') }}</td>
            </tr>
        @endif
    </tbody>
    @if ($showSubtotal)
        <tfoot>
            <tr>
                <td colspan="7" class="text-right" style="font-weight: 600;">{{ __('Sub Total') }}:</td>
                <td class="text-right mono" style="font-weight: 600;">{{ format_money($subtotal ?? 0) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
