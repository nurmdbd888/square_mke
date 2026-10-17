<?php

namespace App\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * Exact money and quantity helpers. Never use float.
 */
final class Exact
{
    public static function of(BigNumber|int|string|null $value, string $fallback = '0'): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::of($fallback);
        }

        if ($value instanceof BigDecimal) {
            return $value;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', Formatter::toAsciiDigits(trim($value)));
            if ($value === '') {
                return BigDecimal::of($fallback);
            }
        }

        return BigDecimal::of($value);
    }

    public static function money(BigNumber|int|string|null $value): string
    {
        return (string) self::of($value)->toScale(2, RoundingMode::HalfUp);
    }

    public static function qty(BigNumber|int|string|null $value): string
    {
        return (string) self::of($value)->toScale(3, RoundingMode::HalfUp);
    }

    public static function price(BigNumber|int|string|null $value): string
    {
        return (string) self::of($value)->toScale(4, RoundingMode::HalfUp);
    }

    public static function rate(BigNumber|int|string|null $value): string
    {
        return (string) self::of($value)->toScale(4, RoundingMode::HalfUp);
    }

    public static function plain(BigNumber|int|string|null $value, int $scale = 4): string
    {
        $plain = self::of($value)->toScale($scale, RoundingMode::HalfUp)->__toString();

        return str_contains($plain, '.') ? rtrim(rtrim($plain, '0'), '.') : $plain;
    }

    /**
     * Issue cost = round(value × qty ÷ stock_qty, 2). Taking the full balance
     * uses the remaining value so the ledger does not drift.
     */
    public static function issueCost(BigDecimal $stockValue, BigDecimal $stockQty, BigDecimal $issueQty): BigDecimal
    {
        if ($stockQty->isZero() || $issueQty->isZero()) {
            return BigDecimal::zero()->toScale(2, RoundingMode::HalfUp);
        }

        if ($issueQty->isEqualTo($stockQty) || $issueQty->isGreaterThan($stockQty)) {
            return $stockValue->toScale(2, RoundingMode::HalfUp);
        }

        return $stockValue
            ->multipliedBy($issueQty)
            ->dividedBy($stockQty, 2, RoundingMode::HalfUp);
    }
}
