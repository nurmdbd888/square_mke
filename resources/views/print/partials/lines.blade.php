@php
    $paidLines = $document->lines->reject(fn ($line) => $line->isComboBonus())->values();
    $bonusLines = $document->lines->filter(fn ($line) => $line->isComboBonus())->values();
    $paidSubtotal = $paidLines->reduce(
        fn ($sum, $line) => $sum->plus(\App\Domain\Money\Exact::of($line->line_value)),
        \App\Domain\Money\Exact::of('0'),
    );
@endphp

@include('print.partials.line-table', [
    'lines' => $paidLines,
    'sectionTitle' => null,
    'forceZeroAmounts' => false,
    'subtotal' => $paidSubtotal,
    'showSubtotal' => true,
])

@if ($bonusLines->isNotEmpty())
    @include('print.partials.line-table', [
        'lines' => $bonusLines,
        'sectionTitle' => __('Invoice Combo Bonus').' :',
        'forceZeroAmounts' => true,
        'subtotal' => \App\Domain\Money\Exact::of('0'),
        'showSubtotal' => true,
    ])
@endif
