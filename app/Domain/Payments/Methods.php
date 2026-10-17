<?php

namespace App\Domain\Payments;

use App\Models\Payment;

/**
 * Payment method labels and book grouping.
 */
final class Methods
{
    /**
     * @return array<string, string> value => English label (translated in views via __())
     */
    public static function labels(): array
    {
        return [
            'cash' => 'Cash',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank' => 'Bank',
            'cheque' => 'Cheque',
            'pdc' => 'Post-dated cheque',
            'petty' => 'Petty cash',
        ];
    }

    public static function label(string $method): string
    {
        return __(self::labels()[$method] ?? $method);
    }

    public static function isCash(string $method): bool
    {
        return in_array($method, Payment::CASH_METHODS, true);
    }

    public static function isBank(string $method): bool
    {
        return in_array($method, Payment::BANK_METHODS, true);
    }
}
