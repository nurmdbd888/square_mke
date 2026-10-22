<?php

namespace App\Domain\Reports;

use App\Domain\Money\Exact;
use App\Models\Document;

final class InvoiceAdjustmentsSummary
{
    /**
     * @return array{
     *     invoice_count: int,
     *     tds_amount: string,
     *     loading_adjustment: string,
     *     combo_bonus_discount: string,
     *     total_adjustments: string
     * }
     */
    public function between(string $from, string $to): array
    {
        $docs = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED)
            ->whereBetween('doc_date', [$from, $to])
            ->get(['tds_amount', 'slab_adjustment', 'combo_bonus_discount']);

        $tds = Exact::of('0');
        $loading = Exact::of('0');
        $combo = Exact::of('0');
        foreach ($docs as $doc) {
            $tds = $tds->plus(Exact::of($doc->tds_amount ?? 0));
            $loading = $loading->plus(Exact::of($doc->slab_adjustment ?? 0));
            $combo = $combo->plus(Exact::of($doc->combo_bonus_discount ?? 0));
        }

        $total = $tds->plus($loading)->plus($combo);

        return [
            'invoice_count' => $docs->count(),
            'tds_amount' => Exact::money($tds),
            'loading_adjustment' => Exact::money($loading),
            'combo_bonus_discount' => Exact::money($combo),
            'total_adjustments' => Exact::money($total),
        ];
    }
}
