<?php

namespace App\Domain\Reports;

use App\Domain\Money\Exact;
use App\Domain\Payments\PartyBalance;
use App\Models\Document;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Chronological party ledger with running balance (retailer receivable / supplier payable).
 */
final class PartyLedger
{
    public function __construct(private readonly PartyBalance $balances) {}

    /**
     * @return array{
     *     opening: string,
     *     closing: string,
     *     entries: list<array{date: string, kind: string, number: string, description: string, debit: string, credit: string, balance: string, url: string|null}>
     * }
     */
    public function between(string $party, int $partyId, string $from, string $to): array
    {
        abort_unless(in_array($party, ['retailer', 'supplier'], true), 404);

        $fromDay = Carbon::parse($from)->toDateString();
        $toDay = Carbon::parse($to)->toDateString();
        $openingAsOf = Carbon::parse($fromDay)->subDay()->toDateString();

        $openingRow = $party === 'retailer'
            ? $this->balances->retailer($partyId, $openingAsOf)
            : $this->balances->supplier($partyId, $openingAsOf);
        $opening = Exact::of($openingRow['balance']);

        $column = $party === 'retailer' ? 'retailer_id' : 'supplier_id';
        $docType = $party === 'retailer' ? Document::TYPE_SALE : Document::TYPE_PURCHASE;

        $docs = Document::query()
            ->where('type', $docType)
            ->where($column, $partyId)
            ->where('status', Document::STATUS_POSTED)
            ->whereBetween('doc_date', [$fromDay, $toDay])
            ->orderBy('doc_date')
            ->orderBy('id')
            ->get(['id', 'number', 'doc_date', 'net_total']);

        $payments = Payment::query()
            ->where('status', Payment::STATUS_POSTED)
            ->where($column, $partyId)
            ->where(function ($q) use ($fromDay, $toDay) {
                $q->where(function ($pdc) use ($fromDay, $toDay) {
                    $pdc->where('method', 'pdc')
                        ->where('clearing_status', Payment::CLEARING_CLEARED)
                        ->whereBetween('cleared_at', [$fromDay.' 00:00:00', $toDay.' 23:59:59']);
                })->orWhere(function ($other) use ($fromDay, $toDay) {
                    $other->where('method', '!=', 'pdc')
                        ->whereBetween('payment_date', [$fromDay, $toDay]);
                });
            })
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'number', 'kind', 'payment_date', 'cleared_at', 'method', 'amount']);

        /** @var Collection<int, array<string, mixed>> $events */
        $events = collect();
        foreach ($docs as $doc) {
            $events->push([
                'sort_date' => $doc->doc_date->toDateString(),
                'sort_id' => $doc->id,
                'date' => $doc->doc_date->toDateString(),
                'kind' => 'invoice',
                'number' => $doc->number,
                'description' => $party === 'retailer' ? __('Sale invoice') : __('Purchase invoice'),
                'debit' => Exact::money(Exact::of($doc->net_total)),
                'credit' => '0.00',
                'delta' => Exact::of($doc->net_total),
                'url' => $party === 'retailer' ? route('sales.show', $doc) : route('purchases.show', $doc),
            ]);
        }
        foreach ($payments as $payment) {
            $date = $payment->method === 'pdc' && $payment->cleared_at
                ? $payment->cleared_at->toDateString()
                : $payment->payment_date->toDateString();
            $events->push([
                'sort_date' => $date,
                'sort_id' => $payment->id,
                'date' => $date,
                'kind' => 'payment',
                'number' => $payment->number,
                'description' => $payment->isReceipt() ? __('Receipt') : __('Payment'),
                'debit' => '0.00',
                'credit' => Exact::money(Exact::of($payment->amount)),
                'delta' => Exact::of($payment->amount)->negated(),
                'url' => route('payments.show', $payment),
            ]);
        }

        $running = $opening;
        $entries = $events
            ->sortBy([
                ['sort_date', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values()
            ->map(function (array $row) use (&$running) {
                /** @var BigDecimal $running */
                $running = $running->plus($row['delta']);
                $balance = $running->isNegative() ? Exact::of('0') : $running;

                return [
                    'date' => $row['date'],
                    'kind' => $row['kind'],
                    'number' => $row['number'],
                    'description' => $row['description'],
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                    'balance' => Exact::money($balance),
                    'url' => $row['url'],
                ];
            })
            ->all();

        $closing = $running->isNegative() ? Exact::of('0') : $running;

        return [
            'opening' => Exact::money($opening),
            'closing' => Exact::money($closing),
            'entries' => $entries,
        ];
    }
}
