{{-- Square-aligned totals: Net Invoice = subtotal − loading − combo; Payable = net + TDS. --}}
@php
    $loading = (float) $document->slab_adjustment;
    $combo = (float) $document->combo_bonus_discount;
    $tds = (float) ($document->tds_amount ?? 0);
    $header = (float) $document->header_discount;
    $rounding = (float) $document->rounding_adjustment;
    $netInvoice = (float) $document->subtotal - $header - $loading - $combo;
@endphp
<div class="totals">
    <div><span>{{ __('Subtotal') }}</span><span class="mono">{{ format_money($document->subtotal) }}</span></div>
    @if ($loading != 0.0)
        <div><span>{{ __('Loading Adjustment') }}</span><span class="mono">−{{ format_money($document->slab_adjustment) }}</span></div>
    @endif
    @if ($combo != 0.0)
        <div><span>{{ __('Combo Discount') }}</span><span class="mono">−{{ format_money($document->combo_bonus_discount) }}</span></div>
    @endif
    @if ($header > 0)
        <div><span>{{ __('Header discount') }}</span><span class="mono">−{{ format_money($document->header_discount) }}</span></div>
    @endif
    <div><span>{{ __('Net invoice amount') }}</span><span class="mono">{{ format_money($netInvoice) }}</span></div>
    @if ($tds != 0.0)
        <div><span>{{ __('Total TDS Amount') }}</span><span class="mono">+{{ format_money($document->tds_amount) }}</span></div>
    @endif
    @if ($rounding != 0.0)
        <div><span>{{ __('Rounding') }}</span><span class="mono">{{ format_money($document->rounding_adjustment) }}</span></div>
    @endif
    <div class="net"><span>{{ __('Total payable') }}</span><span class="mono">{{ format_money($document->net_total) }}</span></div>
</div>
