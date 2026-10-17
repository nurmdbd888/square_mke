<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use App\Models\Document;
use App\Models\OpeningBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Outstanding receivable (retailer) or payable (supplier) as of a date.
 */
final class PartyBalance
{
    /**
     * @return array{opening: string, invoices: string, allocated: string, advances: string, balance: string}
     */
    public function retailer(int $retailerId, CarbonInterface|string|null $asOf = null): array
    {
        return $this->party('retailer', $retailerId, Document::TYPE_SALE, $asOf);
    }

    /**
     * @return array{opening: string, invoices: string, allocated: string, advances: string, balance: string}
     */
    public function supplier(int $supplierId, CarbonInterface|string|null $asOf = null): array
    {
        return $this->party('supplier', $supplierId, Document::TYPE_PURCHASE, $asOf);
    }

    /**
     * Open invoice rows with remaining balance (for allocation UI and ageing).
     *
     * @return Collection<int, object{document_id: int, number: string, doc_date: string, net_total: string, allocated: string, remaining: string}>
     */
    public function openInvoices(string $party, int $partyId, CarbonInterface|string|null $asOf = null): Collection
    {
        $date = ($asOf === null ? now() : Carbon::parse($asOf))->toDateString();
        $type = $party === 'retailer' ? Document::TYPE_SALE : Document::TYPE_PURCHASE;
        $column = $party === 'retailer' ? 'retailer_id' : 'supplier_id';

        $docs = Document::query()
            ->where('type', $type)
            ->where($column, $partyId)
            ->where('status', Document::STATUS_POSTED)
            ->whereDate('doc_date', '<=', $date)
            ->orderBy('doc_date')
            ->orderBy('id')
            ->get(['id', 'number', 'doc_date', 'net_total']);

        $allocated = PaymentAllocation::query()
            ->whereIn('document_id', $docs->pluck('id'))
            ->whereHas('payment', function ($q) use ($date) {
                $q->where('status', Payment::STATUS_POSTED)
                    ->where(function ($w) use ($date) {
                        $w->where(function ($pdc) use ($date) {
                            $pdc->where('method', 'pdc')
                                ->where('clearing_status', Payment::CLEARING_CLEARED)
                                ->whereDate('cleared_at', '<=', $date);
                        })->orWhere(function ($other) use ($date) {
                            $other->where('method', '!=', 'pdc')
                                ->whereDate('payment_date', '<=', $date);
                        });
                    });
            })
            ->select('document_id', DB::raw('SUM(amount) as total'))
            ->groupBy('document_id')
            ->pluck('total', 'document_id');

        return $docs->map(function (Document $doc) use ($allocated) {
            $paid = Exact::of($allocated[$doc->id] ?? 0);
            $net = Exact::of($doc->net_total);
            $remaining = $net->minus($paid);
            if (! $remaining->isPositive()) {
                return null;
            }

            return (object) [
                'document_id' => $doc->id,
                'number' => $doc->number,
                'doc_date' => $doc->doc_date->toDateString(),
                'net_total' => Exact::money($net),
                'allocated' => Exact::money($paid),
                'remaining' => Exact::money($remaining),
            ];
        })->filter()->values();
    }

    /**
     * @return array{opening: string, invoices: string, allocated: string, advances: string, balance: string}
     */
    private function party(string $party, int $partyId, string $docType, CarbonInterface|string|null $asOf): array
    {
        $date = ($asOf === null ? now() : Carbon::parse($asOf))->toDateString();
        $column = $party === 'retailer' ? 'retailer_id' : 'supplier_id';
        $openingKind = $party === 'retailer' ? 'receivable' : 'payable';

        $opening = Exact::of(
            OpeningBalance::query()
                ->where('kind', $openingKind)
                ->where($column, $partyId)
                ->whereDate('as_of', '<=', $date)
                ->sum('amount')
        );

        $invoices = Exact::of(
            Document::query()
                ->where('type', $docType)
                ->where($column, $partyId)
                ->where('status', Document::STATUS_POSTED)
                ->whereDate('doc_date', '<=', $date)
                ->sum('net_total')
        );

        $allocated = Exact::of(
            PaymentAllocation::query()
                ->whereHas('document', fn ($q) => $q->where('type', $docType)->where($column, $partyId)->where('status', Document::STATUS_POSTED))
                ->whereHas('payment', fn ($q) => $this->postedPaymentQuery($q, $date, $column, $partyId))
                ->sum('amount')
        );

        $receiptsOrPayments = Exact::of(
            Payment::query()
                ->where('status', Payment::STATUS_POSTED)
                ->where($column, $partyId)
                ->where(function ($q) use ($date) {
                    $q->where(function ($pdc) use ($date) {
                        $pdc->where('method', 'pdc')
                            ->where('clearing_status', Payment::CLEARING_CLEARED)
                            ->whereDate('cleared_at', '<=', $date);
                    })->orWhere(function ($other) use ($date) {
                        $other->where('method', '!=', 'pdc')
                            ->whereDate('payment_date', '<=', $date);
                    });
                })
                ->sum('amount')
        );

        // Advances = money received/paid that is not allocated to invoices.
        $advances = $receiptsOrPayments->minus($allocated);
        if ($advances->isNegative()) {
            $advances = Exact::of('0');
        }

        $balance = $opening->plus($invoices)->minus($allocated)->minus($advances);

        return [
            'opening' => Exact::money($opening),
            'invoices' => Exact::money($invoices),
            'allocated' => Exact::money($allocated),
            'advances' => Exact::money($advances),
            'balance' => Exact::money($balance->isNegative() ? Exact::of('0') : $balance),
        ];
    }

    private function postedPaymentQuery($q, string $date, string $column, int $partyId)
    {
        return $q->where('status', Payment::STATUS_POSTED)
            ->where($column, $partyId)
            ->where(function ($w) use ($date) {
                $w->where(function ($pdc) use ($date) {
                    $pdc->where('method', 'pdc')
                        ->where('clearing_status', Payment::CLEARING_CLEARED)
                        ->whereDate('cleared_at', '<=', $date);
                })->orWhere(function ($other) use ($date) {
                    $other->where('method', '!=', 'pdc')
                        ->whereDate('payment_date', '<=', $date);
                });
            });
    }
}
