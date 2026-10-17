<?php

namespace App\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * Lakh/crore grouping (12,34,567.89) and Bangla digits, computed on decimal
 * strings. Never takes a float: NumberFormatter::format() would.
 */
final class Formatter
{
    private const ASCII = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const BANGLA = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    /**
     * @param  BigNumber|int|string  $value  a decimal string such as "1234567.89" (Bangla digits accepted)
     */
    public static function number(BigNumber|int|string $value, int $scale = 2, ?string $locale = null): string
    {
        $decimal = self::decimal($value)->toScale($scale, RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        $plain = (string) $decimal->abs();

        [$integer, $fraction] = array_pad(explode('.', $plain, 2), 2, null);

        $formatted = self::groupIndian($integer).($fraction !== null ? '.'.$fraction : '');

        if ($negative) {
            $formatted = '-'.$formatted;
        }

        return self::localiseDigits($formatted, $locale);
    }

    /**
     * Amount with the taka sign: ৳ 12,34,567.89 / ৳ ১২,৩৪,৫৬৭.৮৯.
     */
    public static function money(BigNumber|int|string $value, ?string $locale = null): string
    {
        return '৳ '.self::number($value, 2, $locale);
    }

    /**
     * Short form for dashboards: 1.23 crore, 12.35 lakh, 45,678.
     */
    public static function compact(BigNumber|int|string $value, ?string $locale = null): string
    {
        $decimal = self::decimal($value);
        $abs = $decimal->abs();
        $locale ??= app()->getLocale();

        foreach ([['10000000', 'crore', 'কোটি'], ['100000', 'lakh', 'লাখ']] as [$unit, $en, $bn]) {
            if ($abs->isGreaterThanOrEqualTo($unit)) {
                $scaled = $decimal->dividedBy($unit, 2, RoundingMode::HalfUp);

                return self::number($scaled, 2, $locale).' '.($locale === 'bn' ? $bn : $en);
            }
        }

        return self::number($decimal, 0, $locale);
    }

    public static function localiseDigits(string $text, ?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'bn' ? str_replace(self::ASCII, self::BANGLA, $text) : $text;
    }

    public static function toAsciiDigits(string $text): string
    {
        return str_replace(self::BANGLA, self::ASCII, $text);
    }

    private static function decimal(BigNumber|int|string $value): BigDecimal
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', self::toAsciiDigits(trim($value)));
        }

        return BigDecimal::of($value);
    }

    /**
     * 1234567 -> 12,34,567: the last three digits, then groups of two.
     */
    private static function groupIndian(string $digits): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $groups = str_split(strrev($rest), 2);

        return strrev(implode(',', $groups)).','.$last3;
    }
}
