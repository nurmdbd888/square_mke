<?php

namespace App\Domain\Costing;

use App\Domain\Money\Exact;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Running qty and value for one product during a ledger walk.
 */
final class StockState
{
    public BigDecimal $qty;

    public BigDecimal $value;

    public function __construct(BigDecimal|string|null $qty = '0', BigDecimal|string|null $value = '0')
    {
        $this->qty = Exact::of($qty);
        $this->value = Exact::of($value);
    }

    public function average(): string
    {
        if (! $this->qty->isPositive()) {
            return '0';
        }

        return (string) $this->value->dividedBy($this->qty, 6, RoundingMode::HalfUp);
    }

    public function receive(BigDecimal|string $qty, BigDecimal|string $value): void
    {
        $this->qty = $this->qty->plus(Exact::of($qty));
        $this->value = $this->value->plus(Exact::of($value));
    }

    /**
     * Issue stock. Decision #33: selling beyond stock uses the last average
     * (or the product buy price when there is none).
     *
     * @return string issue cost (2 dp)
     */
    public function issue(BigDecimal|string $qty, ?string $fallbackUnitCost = null): string
    {
        $want = Exact::of($qty);

        if ($want->isZero()) {
            return '0.00';
        }

        $lastAverage = $this->qty->isPositive()
            ? $this->value->dividedBy($this->qty, 6, RoundingMode::HalfUp)
            : Exact::of($fallbackUnitCost ?? '0');

        if ($this->qty->isPositive() && $want->isEqualTo($this->qty)) {
            $cost = Exact::money($this->value);
            $this->qty = Exact::of('0');
            $this->value = Exact::of('0');

            return $cost;
        }

        if ($this->qty->isPositive() && $want->isLessThan($this->qty)) {
            $cost = Exact::issueCost($this->value, $this->qty, $want);
            $this->qty = $this->qty->minus($want);
            $this->value = $this->value->minus(Exact::of($cost));

            return Exact::money($cost);
        }

        if ($this->qty->isPositive() && $want->isGreaterThan($this->qty)) {
            $availableCost = Exact::money($this->value);
            $short = $want->minus($this->qty);
            $unit = Exact::of($fallbackUnitCost ?? (string) $lastAverage);
            if ($unit->isZero()) {
                $unit = $lastAverage;
            }
            $shortCost = Exact::money($unit->multipliedBy($short));
            $this->qty = Exact::of('0')->minus($short);
            $this->value = Exact::of('0')->minus(Exact::of($shortCost));

            return Exact::money(Exact::of($availableCost)->plus(Exact::of($shortCost)));
        }

        $unit = Exact::of($fallbackUnitCost ?? '0');
        if ($unit->isZero() && ! $lastAverage->isZero()) {
            $unit = $lastAverage;
        }
        $cost = Exact::money($unit->multipliedBy($want));
        $this->qty = $this->qty->minus($want);
        $this->value = $this->value->minus(Exact::of($cost));

        return $cost;
    }
}
