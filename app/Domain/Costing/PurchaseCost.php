<?php

namespace App\Domain\Costing;

use App\Domain\Money\Exact;
use App\Settings\SettingsStore;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

/**
 * Stock value of one purchase line (printed value, not qty × printed price).
 *
 * cost = line_value − line_discount − allocated_header_discount
 *      + SD (when sd_in_cost) + VAT (only when vat_recoverable is off).
 * Bonus qty on a purchase enters stock at zero cost.
 */
final class PurchaseCost
{
    public function __construct(private readonly SettingsStore $settings) {}

    /**
     * @param  array{line_value?: string|int|null, line_discount?: string|int|null, header_discount_share?: string|int|null, sd_amount?: string|int|null, vat_amount?: string|int|null}  $line
     */
    public function stockValue(array $line, CarbonInterface|string|null $on = null): string
    {
        $value = Exact::of($line['line_value'] ?? 0)
            ->minus(Exact::of($line['line_discount'] ?? 0))
            ->minus(Exact::of($line['header_discount_share'] ?? 0));

        if ($this->settings->get('sd_in_cost', $on)) {
            $value = $value->plus(Exact::of($line['sd_amount'] ?? 0));
        }

        if (! $this->settings->get('vat_recoverable', $on)) {
            $value = $value->plus(Exact::of($line['vat_amount'] ?? 0));
        }

        return Exact::money($value->isNegative() ? BigDecimal::zero() : $value);
    }

    /**
     * Unit cost for a paid purchase qty (bonus qty is excluded from the divisor).
     */
    public function unitCost(string $stockValue, string $paidQty): string
    {
        $qty = Exact::of($paidQty);

        if ($qty->isZero()) {
            return '0';
        }

        return (string) Exact::of($stockValue)->dividedBy($qty, 4, RoundingMode::HalfUp);
    }
}
