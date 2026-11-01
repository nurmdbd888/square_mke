<?php

namespace App\Domain\Money;

use Brick\Math\RoundingMode;

final class InvoiceRounding
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     */
    public function adjustment(array $lines, array $header): string
    {
        $subtotal = Exact::of(0);
        foreach ($lines as $line) {
            if (($line['kind'] ?? 'normal') === 'combo_bonus') {
                continue;
            }

            $lineValue = $line['line_value']
                ?? Exact::money(
                    Exact::of($line['qty'] ?? 0)
                        ->multipliedBy(Exact::of($line['unit_price'] ?? 0))
                        ->minus(Exact::of($line['line_discount'] ?? 0))
                );
            $subtotal = $subtotal->plus(Exact::of(Exact::money($lineValue)));
        }

        $amount = $subtotal
            ->minus(Exact::of($header['header_discount'] ?? 0))
            ->minus(Exact::of($header['slab_adjustment'] ?? 0))
            ->minus(Exact::of($header['combo_bonus_discount'] ?? 0))
            ->plus(Exact::of($header['tds_amount'] ?? 0))
            ->toScale(2, RoundingMode::HalfUp);
        $whole = $amount->toScale(0, RoundingMode::Down);
        $fraction = $amount->minus($whole)->abs();

        if ($fraction->isGreaterThan('0.50')) {
            $whole = $whole->plus($amount->isLessThan('0') ? -1 : 1);
        }

        return Exact::money($whole->minus($amount));
    }
}
