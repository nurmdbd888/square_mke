<?php

namespace App\Domain\Import;

final class InvoiceText
{
    /** @var array<string, string> */
    private const BN_DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    public static function normalizeDigits(string $value): string
    {
        return strtr($value, self::BN_DIGITS);
    }

    public static function normalizeKey(string $value): string
    {
        $value = self::normalizeDigits(mb_strtolower(trim($value)));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    public static function parseNumber(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = self::normalizeDigits(trim($value));
        if (strcasecmp($value, 'Bonus') === 0) {
            return '0';
        }
        $value = str_replace(['%', ',', ' '], '', $value);

        return $value === '' || ! is_numeric($value) ? null : $value;
    }

    public static function parsePercent(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $num = self::parseNumber($value);

        return $num;
    }

    public static function parseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }
}
