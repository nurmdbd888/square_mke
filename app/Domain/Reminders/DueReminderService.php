<?php

namespace App\Domain\Reminders;

use App\Domain\Money\Exact;
use App\Domain\Payments\PartyBalance;
use App\Models\Retailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Retailers with open receivables older than the due threshold.
 */
final class DueReminderService
{
    public function __construct(private readonly PartyBalance $balances) {}

    /**
     * @return Collection<int, array{retailer: Retailer, balance: string, oldest_days: int, phone: string}>
     */
    public function retailersPastDue(int $dueDays, ?Carbon $asOf = null): Collection
    {
        $asOf = ($asOf ?? now())->startOfDay();
        $results = collect();

        Retailer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->each(function (Retailer $retailer) use ($dueDays, $asOf, $results) {
                $open = $this->balances->openInvoices('retailer', $retailer->id, $asOf);
                if ($open->isEmpty()) {
                    return;
                }

                $oldestDays = 0;
                foreach ($open as $row) {
                    $days = Carbon::parse($row->doc_date)->startOfDay()->diffInDays($asOf);
                    $oldestDays = max($oldestDays, (int) $days);
                }

                if ($oldestDays <= $dueDays) {
                    return;
                }

                $balance = Exact::money(Exact::of($this->balances->retailer($retailer->id, $asOf)['balance']));
                if (Exact::of($balance)->isZero()) {
                    return;
                }

                $results->push([
                    'retailer' => $retailer,
                    'balance' => $balance,
                    'oldest_days' => $oldestDays,
                    'phone' => (string) $retailer->phone,
                ]);
            });

        return $results;
    }

    public function buildMessage(Retailer $retailer, string $balance, int $oldestDays): string
    {
        return __('Payment reminder: :name owes :balance (oldest invoice :days days). Please contact :firm.', [
            'name' => $retailer->name,
            'balance' => $balance,
            'days' => local_digits((string) $oldestDays),
            'firm' => setting('firm_name'),
        ]);
    }
}
