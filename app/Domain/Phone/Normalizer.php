<?php

namespace App\Domain\Phone;

/**
 * Bangladesh mobile numbers stored as 01XXXXXXXXX.
 * Accepts 01…, 1…, 8801… and +8801…, with spaces and dashes.
 */
final class Normalizer
{
    public static function normalise(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $input) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) >= 13) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0'.$digits;
        }

        return $digits === '' ? null : $digits;
    }
}
