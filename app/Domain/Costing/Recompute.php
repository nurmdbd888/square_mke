<?php

namespace App\Domain\Costing;

use App\Domain\Money\Exact;
use App\Jobs\RecomputeProductsJob;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Settings\SettingsStore;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the weighted-average stock ledger for affected products.
 *
 * Inline when the work looks small; otherwise queues RecomputeProductsJob
 * (processed by the minute cron via queue:work --stop-when-empty).
 */
final class Recompute
{
    public function __construct(
        private readonly PurchaseCost $purchaseCost,
        private readonly SettingsStore $settings,
    ) {}

    /**
     * @param  list<int>|int  $productIds
     */
    public function products(array|int $productIds, CarbonInterface|string|null $from = null, bool $forceInline = false): void
    {
        $ids = array_values(array_unique(array_map('intval', (array) $productIds)));
        $ids = array_values(array_filter($ids, fn (int $id) => $id > 0));

        if ($ids === []) {
            return;
        }

        $fromDate = $from === null ? null : Carbon::parse($from)->toDateString();
        $estimate = count($ids) * max(1, DocumentLine::query()
            ->whereIn('product_id', $ids)
            ->whereHas('document', fn ($q) => $q->where('status', Document::STATUS_POSTED))
            ->count());

        $limit = (int) config('sdm.recompute_inline_limit', 2000);

        if (! $forceInline && $estimate > $limit) {
            RecomputeProductsJob::dispatch($ids, $fromDate);
            cache()->put('sdm.costing_pending', true, now()->addHour());

            return;
        }

        foreach ($ids as $productId) {
            $this->one($productId, $fromDate);
        }

        if (! RecomputeProductsJob::pending()) {
            cache()->forget('sdm.costing_pending');
        }
    }

    public function one(int $productId, ?string $fromDate = null): void
    {
        $product = Product::query()->withTrashed()->find($productId);

        if ($product === null) {
            return;
        }

        DB::transaction(function () use ($product, $fromDate) {
            $query = StockLedgerEntry::query()->where('product_id', $product->id);

            if ($fromDate !== null) {
                $query->whereDate('doc_date', '>=', $fromDate);
            }

            $query->delete();

            $state = $this->seedState($product->id, $fromDate);
            $fallback = $product->buy_price_exact ?? $product->buy_price;
            $movements = $this->movements($product->id, $fromDate);

            foreach ($movements as $movement) {
                $qtyIn = Exact::of('0');
                $qtyOut = Exact::of('0');
                $valueIn = Exact::of('0');
                $valueOut = Exact::of('0');

                if ($movement['direction'] === 'in') {
                    $qtyIn = Exact::of($movement['qty']);
                    $valueIn = Exact::of($movement['value']);
                    $state->receive($qtyIn, $valueIn);
                } else {
                    $qtyOut = Exact::of($movement['qty']);
                    $valueOut = Exact::of($state->issue($qtyOut, $fallback !== null ? (string) $fallback : null));
                    if (isset($movement['line_id'], $movement['cost_field'])) {
                        DocumentLine::query()->whereKey($movement['line_id'])->update([
                            $movement['cost_field'] => Exact::money($valueOut),
                        ]);
                    }
                }

                StockLedgerEntry::query()->create([
                    'product_id' => $product->id,
                    'doc_date' => $movement['doc_date'],
                    'movement' => $movement['movement'],
                    'rank' => LedgerOrder::rank($movement['movement']),
                    'document_id' => $movement['document_id'],
                    'document_line_id' => $movement['line_id'],
                    'qty_in' => Exact::qty($qtyIn),
                    'qty_out' => Exact::qty($qtyOut),
                    'value_in' => Exact::money($valueIn),
                    'value_out' => Exact::money($valueOut),
                    'running_qty' => Exact::qty($state->qty),
                    'running_value' => Exact::money($state->value),
                    'average_cost' => $state->average(),
                    'posted_at' => $movement['posted_at'],
                ]);
            }

            $this->refreshSaleCosts($product->id);
        });
    }

    /**
     * Full rebuild of every product that has posted lines (property-test oracle).
     */
    public function all(): void
    {
        $ids = DocumentLine::query()
            ->whereHas('document', fn ($q) => $q->where('status', Document::STATUS_POSTED))
            ->distinct()
            ->pluck('product_id')
            ->all();

        StockLedgerEntry::query()->delete();

        foreach ($ids as $id) {
            $this->one((int) $id, null);
        }
    }

    private function seedState(int $productId, ?string $fromDate): StockState
    {
        if ($fromDate === null) {
            return new StockState;
        }

        $prior = StockLedgerEntry::query()
            ->where('product_id', $productId)
            ->whereDate('doc_date', '<', $fromDate)
            ->orderByDesc('doc_date')
            ->orderByDesc('rank')
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->first();

        if ($prior === null) {
            return new StockState;
        }

        return new StockState($prior->running_qty, $prior->running_value);
    }

    /**
     * @return list<array{doc_date: string, movement: string, direction: string, qty: string, value: string, document_id: int, line_id: int, posted_at: mixed, cost_field?: string}>
     */
    private function movements(int $productId, ?string $fromDate): array
    {
        $lines = DocumentLine::query()
            ->with('document')
            ->where('product_id', $productId)
            ->whereHas('document', function ($q) use ($fromDate) {
                $q->where('status', Document::STATUS_POSTED);
                if ($fromDate !== null) {
                    $q->whereDate('doc_date', '>=', $fromDate);
                }
            })
            ->get();

        $rows = [];

        foreach ($lines as $line) {
            /** @var Document $doc */
            $doc = $line->document;
            $postedAt = $doc->posted_at;

            if ($doc->isPurchase()) {
                $paidQty = Exact::of($line->qty);
                $bonusQty = Exact::of($line->bonus_qty);
                $stockValue = $this->purchaseCost->stockValue([
                    'line_value' => $line->line_value,
                    'line_discount' => $line->line_discount,
                    'header_discount_share' => $line->header_discount_share,
                    'sd_amount' => $line->sd_amount,
                    'vat_amount' => $line->vat_amount,
                ], $doc->doc_date);

                if ($paidQty->isPositive()) {
                    $rows[] = [
                        'doc_date' => $doc->doc_date->toDateString(),
                        'movement' => LedgerOrder::PURCHASE,
                        'direction' => 'in',
                        'qty' => Exact::qty($paidQty),
                        'value' => $stockValue,
                        'document_id' => $doc->id,
                        'line_id' => $line->id,
                        'posted_at' => $postedAt,
                    ];
                }

                if ($bonusQty->isPositive()) {
                    $bonusValue = $this->settings->get('purchase_bonus_cost', $doc->doc_date) === 'list_price'
                        ? Exact::money(Exact::of($line->unit_price)->multipliedBy($bonusQty))
                        : '0.00';
                    $rows[] = [
                        'doc_date' => $doc->doc_date->toDateString(),
                        'movement' => LedgerOrder::PURCHASE,
                        'direction' => 'in',
                        'qty' => Exact::qty($bonusQty),
                        'value' => $bonusValue,
                        'document_id' => $doc->id,
                        'line_id' => $line->id,
                        'posted_at' => $postedAt,
                    ];
                }

                continue;
            }

            // Sales
            if ($line->isComboBonus()) {
                $qty = Exact::of($line->qty)->plus(Exact::of($line->bonus_qty));
                if ($qty->isPositive()) {
                    $rows[] = [
                        'doc_date' => $doc->doc_date->toDateString(),
                        'movement' => LedgerOrder::SALE_BONUS,
                        'direction' => 'out',
                        'qty' => Exact::qty($qty),
                        'value' => '0',
                        'document_id' => $doc->id,
                        'line_id' => $line->id,
                        'posted_at' => $postedAt,
                        'cost_field' => 'bonus_issue_cost',
                    ];
                }

                continue;
            }

            $sold = Exact::of($line->qty);
            $bonus = Exact::of($line->bonus_qty);

            if ($sold->isPositive()) {
                $rows[] = [
                    'doc_date' => $doc->doc_date->toDateString(),
                    'movement' => LedgerOrder::SALE,
                    'direction' => 'out',
                    'qty' => Exact::qty($sold),
                    'value' => '0',
                    'document_id' => $doc->id,
                    'line_id' => $line->id,
                    'posted_at' => $postedAt,
                    'cost_field' => 'issue_cost',
                ];
            }

            if ($bonus->isPositive()) {
                $rows[] = [
                    'doc_date' => $doc->doc_date->toDateString(),
                    'movement' => LedgerOrder::SALE_BONUS,
                    'direction' => 'out',
                    'qty' => Exact::qty($bonus),
                    'value' => '0',
                    'document_id' => $doc->id,
                    'line_id' => $line->id,
                    'posted_at' => $postedAt,
                    'cost_field' => 'bonus_issue_cost',
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            $date = strcmp($a['doc_date'], $b['doc_date']);
            if ($date !== 0) {
                return $date;
            }
            $rank = LedgerOrder::rank($a['movement']) <=> LedgerOrder::rank($b['movement']);
            if ($rank !== 0) {
                return $rank;
            }
            $posted = strcmp((string) $a['posted_at'], (string) $b['posted_at']);
            if ($posted !== 0) {
                return $posted;
            }

            return $a['line_id'] <=> $b['line_id'];
        });

        return $rows;
    }

    /**
     * After a product rebuild, roll sale document COGS / free-goods totals up.
     */
    private function refreshSaleCosts(int $productId): void
    {
        $docIds = DocumentLine::query()
            ->where('product_id', $productId)
            ->whereHas('document', fn ($q) => $q->where('type', Document::TYPE_SALE)->where('status', Document::STATUS_POSTED))
            ->pluck('document_id')
            ->unique()
            ->all();

        foreach ($docIds as $docId) {
            $doc = Document::query()->with('lines')->find($docId);
            if ($doc === null) {
                continue;
            }

            $cogs = Exact::of('0');
            $free = Exact::of('0');

            foreach ($doc->lines as $line) {
                $cogs = $cogs->plus(Exact::of($line->issue_cost));
                $free = $free->plus(Exact::of($line->bonus_issue_cost));
            }

            $doc->forceFill([
                'cogs' => Exact::money($cogs),
                'free_goods_cost' => Exact::money($free),
            ])->saveQuietly();
        }
    }
}
