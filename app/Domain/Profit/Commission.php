<?php

namespace App\Domain\Profit;

use App\Domain\Money\Exact;
use App\Settings\SettingsStore;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

/**
 * Commission on a posted sales document (effective-dated rates).
 *
 * Gross = base × commission_pct / 100
 * VAT part = base × commission_vat_pct / 100
 * Net = gross − VAT part
 */
final class Commission
{
    public function __construct(private readonly SettingsStore $settings) {}

    /**
     * @return array{gross: string, vat: string, net: string, base: string, counted: bool}|null
     */
    public function forSale(string $salesRevenue, string $purchaseExVat, CarbonInterface|string|null $on = null): ?array
    {
        if (! $this->settings->get('count_commission', $on)) {
            return null;
        }

        $baseKey = (string) $this->settings->get('commission_base', $on);
        $base = Exact::of($baseKey === 'purchase' ? $purchaseExVat : $salesRevenue);
        $pct = Exact::of($this->settings->get('commission_pct', $on));
        $vatPct = Exact::of($this->settings->get('commission_vat_pct', $on));

        $gross = $base->multipliedBy($pct)->dividedBy(100, 2, RoundingMode::HalfUp);
        $vat = $base->multipliedBy($vatPct)->dividedBy(100, 2, RoundingMode::HalfUp);
        $net = $gross->minus($vat);

        return [
            'gross' => Exact::money($gross),
            'vat' => Exact::money($vat),
            'net' => Exact::money($net),
            'base' => Exact::money($base),
            'counted' => true,
        ];
    }
}
