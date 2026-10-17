<?php

namespace App\Domain\Costing;

use App\Domain\Money\Exact;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Allocates a header discount to lines pro rata by line_value; the last
 * positive line takes the rounding remainder so the shares sum exactly.
 */
final class HeaderDiscountAllocator
{
    /**
     * @param  list<array{line_value: string|int|float|null}>  $lines
     * @return list<string> shares aligned with $lines
     */
    public function allocate(array $lines, string|int|float|null $headerDiscount): array
    {
        $discount = Exact::of($headerDiscount);
        $count = count($lines);

        if ($count === 0 || $discount->isZero()) {
            return array_fill(0, $count, '0.00');
        }

        $bases = [];
        $total = Exact::of('0');

        foreach ($lines as $i => $line) {
            $base = Exact::of($line['line_value'] ?? 0);
            if ($base->isNegative()) {
                $base = Exact::of('0');
            }
            $bases[$i] = $base;
            $total = $total->plus($base);
        }

        $shares = array_fill(0, $count, Exact::of('0'));
        $allocated = Exact::of('0');
        $lastPositive = null;

        if ($total->isZero()) {
            $shares[$count - 1] = $discount;

            return array_map(fn (BigDecimal $s) => Exact::money($s), $shares);
        }

        foreach ($bases as $i => $base) {
            if (! $base->isPositive()) {
                continue;
            }
            $lastPositive = $i;
            $share = $discount->multipliedBy($base)->dividedBy($total, 2, RoundingMode::HalfUp);
            $shares[$i] = $share;
            $allocated = $allocated->plus($share);
        }

        if ($lastPositive !== null) {
            $shares[$lastPositive] = $shares[$lastPositive]->plus($discount->minus($allocated));
        }

        return array_map(fn (BigDecimal $s) => Exact::money($s), $shares);
    }
}
