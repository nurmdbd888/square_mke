<?php

namespace App\Domain\Costing;

/**
 * Sort key for stock movements on one product within a day.
 *
 * Opening → purchase → sales return → adjustment-in → sale (incl. bonus)
 * → purchase return / claim-out → loss / damage / adjustment-out; then
 * posting time, then id. Purchases therefore cost before same-day sales.
 */
final class LedgerOrder
{
    public const OPENING = 'opening';

    public const PURCHASE = 'purchase';

    public const SALES_RETURN = 'sales_return';

    public const ADJUSTMENT_IN = 'adjustment_in';

    public const SALE = 'sale';

    public const SALE_BONUS = 'sale_bonus';

    public const PURCHASE_RETURN = 'purchase_return';

    public const LOSS = 'loss';

    /** @var array<string, int> */
    public const RANK = [
        self::OPENING => 0,
        self::PURCHASE => 1,
        self::SALES_RETURN => 2,
        self::ADJUSTMENT_IN => 3,
        self::SALE => 4,
        self::SALE_BONUS => 4,
        self::PURCHASE_RETURN => 5,
        self::LOSS => 6,
    ];

    public static function rank(string $type): int
    {
        return self::RANK[$type] ?? 99;
    }
}
