<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use App\Models\Retailer;

/**
 * Warns when a new sale would push the retailer over their credit limit.
 */
final class CreditLimit
{
    public function __construct(private readonly PartyBalance $balances) {}

    /**
     * @return array{over: bool, limit: string|null, outstanding: string, projected: string}|null
     */
    public function check(Retailer $retailer, string $additionalSale = '0'): ?array
    {
        if ($retailer->credit_limit === null || Exact::of($retailer->credit_limit)->isZero()) {
            return null;
        }

        $outstanding = Exact::of($this->balances->retailer($retailer->id)['balance']);
        $projected = $outstanding->plus(Exact::of($additionalSale));
        $limit = Exact::of($retailer->credit_limit);

        return [
            'over' => $projected->isGreaterThan($limit),
            'limit' => Exact::money($limit),
            'outstanding' => Exact::money($outstanding),
            'projected' => Exact::money($projected),
        ];
    }
}
