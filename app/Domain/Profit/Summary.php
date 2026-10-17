<?php

namespace App\Domain\Profit;

use App\Domain\Money\Exact;
use App\Models\Document;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * P&L figures from posted sales (and later expenses / losses).
 *
 * profit before expenses = revenue − COGS − free-goods cost + net commission
 */
final class Summary
{
    /**
     * @return array{
     *     revenue: string,
     *     cogs: string,
     *     free_goods: string,
     *     gross: string,
     *     commission_gross: string,
     *     commission_vat: string,
     *     commission_net: string,
     *     profit_before_expenses: string
     * }
     */
    public function between(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): array
    {
        $query = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED);

        if ($from !== null) {
            $query->whereDate('doc_date', '>=', Carbon::parse($from)->toDateString());
        }
        if ($to !== null) {
            $query->whereDate('doc_date', '<=', Carbon::parse($to)->toDateString());
        }

        $revenue = Exact::of('0');
        $cogs = Exact::of('0');
        $free = Exact::of('0');
        $commGross = Exact::of('0');
        $commVat = Exact::of('0');
        $commNet = Exact::of('0');

        foreach ($query->get() as $doc) {
            $revenue = $revenue->plus(Exact::of($doc->revenue));
            $cogs = $cogs->plus(Exact::of($doc->cogs));
            $free = $free->plus(Exact::of($doc->free_goods_cost));
            $commGross = $commGross->plus(Exact::of($doc->commission_gross));
            $commVat = $commVat->plus(Exact::of($doc->commission_vat));
            $commNet = $commNet->plus(Exact::of($doc->commission_net));
        }

        $gross = $revenue->minus($cogs)->minus($free);
        $profit = $gross->plus($commNet);

        return [
            'revenue' => Exact::money($revenue),
            'cogs' => Exact::money($cogs),
            'free_goods' => Exact::money($free),
            'gross' => Exact::money($gross),
            'commission_gross' => Exact::money($commGross),
            'commission_vat' => Exact::money($commVat),
            'commission_net' => Exact::money($commNet),
            'profit_before_expenses' => Exact::money($profit),
        ];
    }
}
