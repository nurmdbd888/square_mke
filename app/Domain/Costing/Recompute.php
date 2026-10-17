<?php

namespace App\Domain\Costing;

use App\Domain\Money\Exact;
use App\Domain\Stock\BatchRegistry;
use App\Domain\Stock\FefoWalker;
use App\Jobs\RecomputeProductsJob;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\StockNote;
use App\Models\StockNoteLine;
use App\Settings\SettingsStore;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the stock ledger for affected products (weighted average or batch FEFO).
 */
final class Recompute
{
    public function __construct(
        private readonly PurchaseCost $purchaseCost,
        private readonly SettingsStore $settings,
        private readonly BatchRegistry $batches,
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

            $useFefo = $this->settings->get('costing_mode', now()) === 'batch_fefo' && $product->tracks_expiry;
            $state = $this->seedState($product->id, $fromDate);
            $fefo = $useFefo ? new FefoWalker : null;
            if ($fefo !== null) {
                $fefo->totalState()->qty = $state->qty;
                $fefo->totalState()->value = $state->value;
            }

            $fallback = $product->buy_price_exact ?? $product->buy_price;
            $fallbackStr = $fallback !== null ? (string) $fallback : null;
            $movements = $this->movements($product, $fromDate);

            foreach ($movements as $movement) {
                $this->applyMovement($product, $movement, $state, $fefo, $fallbackStr);
            }

            $this->refreshSaleCosts($product->id);
            $this->refreshStockNoteCosts($product->id);
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

    /**
     * @param  array<string, mixed>  $movement
     */
    private function applyMovement(
        Product $product,
        array $movement,
        StockState $state,
        ?FefoWalker $fefo,
        ?string $fallback,
    ): void {
        $qtyIn = Exact::of('0');
        $qtyOut = Exact::of('0');
        $valueIn = Exact::of('0');
        $valueOut = Exact::of('0');
        $batchId = $movement['product_batch_id'] ?? null;

        if ($movement['direction'] === 'in') {
            $qtyIn = Exact::of($movement['qty']);
            if ($movement['value'] === 'avg') {
                $avg = Exact::of($state->average());
                $valueIn = Exact::of(Exact::money($avg->multipliedBy($qtyIn)));
            } else {
                $valueIn = Exact::of($movement['value']);
            }

            if ($fefo !== null) {
                $fefo->receive($batchId, $qtyIn, $valueIn);
                $state->qty = $fefo->totalState()->qty;
                $state->value = $fefo->totalState()->value;
            } else {
                $state->receive($qtyIn, $valueIn);
            }

            if (isset($movement['stock_note_line_id'], $movement['restore_field'])) {
                StockNoteLine::query()->whereKey($movement['stock_note_line_id'])->update([
                    $movement['restore_field'] => Exact::money($valueIn),
                ]);
            }
        } else {
            $qtyOut = Exact::of($movement['qty']);

            if ($fefo !== null && $movement['movement'] === LedgerOrder::SALE) {
                $splits = $fefo->issueFefo($product->id, $qtyOut, $fallback);
                $state->qty = $fefo->totalState()->qty;
                $state->value = $fefo->totalState()->value;
                $totalCost = Exact::of('0');
                foreach ($splits as $split) {
                    $totalCost = $totalCost->plus(Exact::of($split['cost']));
                    $this->writeLedgerRow($product, $movement, $split['batch_id'], Exact::of('0'), Exact::of($split['qty']), Exact::of('0'), Exact::of($split['cost']), $state);
                }
                if (isset($movement['line_id'], $movement['cost_field'])) {
                    DocumentLine::query()->whereKey($movement['line_id'])->update([
                        $movement['cost_field'] => Exact::money($totalCost),
                    ]);
                }

                return;
            }

            if (isset($movement['fixed_cost'])) {
                $valueOut = Exact::of($movement['fixed_cost']);
                if ($fefo !== null) {
                    $fefo->totalState()->qty = $fefo->totalState()->qty->minus($qtyOut);
                    $fefo->totalState()->value = $fefo->totalState()->value->minus($valueOut);
                    $state->qty = $fefo->totalState()->qty;
                    $state->value = $fefo->totalState()->value;
                } else {
                    $state->qty = $state->qty->minus($qtyOut);
                    $state->value = $state->value->minus($valueOut);
                }
            } elseif ($fefo !== null) {
                $valueOut = Exact::of($fefo->issueAverage($qtyOut, $fallback));
                $state->qty = $fefo->totalState()->qty;
                $state->value = $fefo->totalState()->value;
            } else {
                $valueOut = Exact::of($state->issue($qtyOut, $fallback));
            }

            if (isset($movement['line_id'], $movement['cost_field'])) {
                DocumentLine::query()->whereKey($movement['line_id'])->update([
                    $movement['cost_field'] => Exact::money($valueOut),
                ]);
            }

            if (isset($movement['stock_note_line_id'], $movement['loss_field'])) {
                StockNoteLine::query()->whereKey($movement['stock_note_line_id'])->update([
                    $movement['loss_field'] => Exact::money($valueOut),
                ]);
            }
        }

        $this->writeLedgerRow($product, $movement, $batchId, $qtyIn, $qtyOut, $valueIn, $valueOut, $state);
    }

    /**
     * @param  array<string, mixed>  $movement
     */
    private function writeLedgerRow(
        Product $product,
        array $movement,
        ?int $batchId,
        BigDecimal $qtyIn,
        BigDecimal $qtyOut,
        BigDecimal $valueIn,
        BigDecimal $valueOut,
        StockState $state,
    ): void {
        StockLedgerEntry::query()->create([
            'product_id' => $product->id,
            'product_batch_id' => $batchId,
            'doc_date' => $movement['doc_date'],
            'movement' => $movement['movement'],
            'rank' => LedgerOrder::rank($movement['movement']),
            'document_id' => $movement['document_id'] ?? null,
            'document_line_id' => $movement['line_id'] ?? null,
            'stock_note_id' => $movement['stock_note_id'] ?? null,
            'stock_note_line_id' => $movement['stock_note_line_id'] ?? null,
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
     * @return list<array<string, mixed>>
     */
    private function movements(Product $product, ?string $fromDate): array
    {
        $rows = array_merge(
            $this->documentMovements($product->id, $fromDate),
            $this->stockNoteMovements($product->id, $fromDate),
        );

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

            return ($a['sort_id'] ?? 0) <=> ($b['sort_id'] ?? 0);
        });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentMovements(int $productId, ?string $fromDate): array
    {
        $product = Product::query()->find($productId);
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
            $batch = $product !== null
                ? $this->batches->forLine($product, $line->batch_no, $line->expiry_date)
                : null;
            if ($batch !== null && $line->product_batch_id !== $batch->id) {
                $line->forceFill(['product_batch_id' => $batch->id])->saveQuietly();
            }
            $batchId = $batch?->id;

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
                    $rows[] = $this->row($doc->doc_date->toDateString(), LedgerOrder::PURCHASE, 'in', Exact::qty($paidQty), $stockValue, $doc->id, $line->id, $postedAt, $batchId);
                }

                if ($bonusQty->isPositive()) {
                    $bonusValue = $this->settings->get('purchase_bonus_cost', $doc->doc_date) === 'list_price'
                        ? Exact::money(Exact::of($line->unit_price)->multipliedBy($bonusQty))
                        : '0.00';
                    $rows[] = $this->row($doc->doc_date->toDateString(), LedgerOrder::PURCHASE, 'in', Exact::qty($bonusQty), $bonusValue, $doc->id, $line->id, $postedAt, $batchId);
                }

                continue;
            }

            if ($line->isComboBonus()) {
                $qty = Exact::of($line->qty)->plus(Exact::of($line->bonus_qty));
                if ($qty->isPositive()) {
                    $rows[] = $this->row($doc->doc_date->toDateString(), LedgerOrder::SALE_BONUS, 'out', Exact::qty($qty), '0', $doc->id, $line->id, $postedAt, null, 'bonus_issue_cost');
                }

                continue;
            }

            $sold = Exact::of($line->qty);
            $bonus = Exact::of($line->bonus_qty);

            if ($sold->isPositive()) {
                $rows[] = $this->row($doc->doc_date->toDateString(), LedgerOrder::SALE, 'out', Exact::qty($sold), '0', $doc->id, $line->id, $postedAt, null, 'issue_cost');
            }

            if ($bonus->isPositive()) {
                $rows[] = $this->row($doc->doc_date->toDateString(), LedgerOrder::SALE_BONUS, 'out', Exact::qty($bonus), '0', $doc->id, $line->id, $postedAt, null, 'bonus_issue_cost');
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockNoteMovements(int $productId, ?string $fromDate): array
    {
        $lines = StockNoteLine::query()
            ->with('stockNote')
            ->where('product_id', $productId)
            ->whereHas('stockNote', function ($q) use ($fromDate) {
                $q->where('status', StockNote::STATUS_POSTED);
                if ($fromDate !== null) {
                    $q->whereDate('doc_date', '>=', $fromDate);
                }
            })
            ->get();

        $rows = [];

        foreach ($lines as $line) {
            $note = $line->stockNote;
            $postedAt = $note->posted_at;
            $qty = Exact::of($line->qty);

            if ($qty->isZero()) {
                continue;
            }

            if ($note->type === StockNote::TYPE_SALES_RETURN) {
                if ($line->isDamagedOrExpired()) {
                    if ($line->linked_document_line_id === null) {
                        $rows[] = $this->stockRow($note, $line, LedgerOrder::LOSS, 'out', Exact::qty($qty), '0', 'loss_cost');
                    }

                    continue;
                }

                $value = $this->returnValueIn($line);
                $rows[] = $this->stockRow($note, $line, LedgerOrder::SALES_RETURN, 'in', Exact::qty($qty), $value, 'restore_cost');

                continue;
            }

            if ($note->type === StockNote::TYPE_PURCHASE_RETURN) {
                $rows[] = $this->stockRow($note, $line, LedgerOrder::PURCHASE_RETURN, 'out', Exact::qty($qty), '0');

                continue;
            }

            if ($note->type === StockNote::TYPE_LOSS) {
                $rows[] = $this->stockRow($note, $line, LedgerOrder::LOSS, 'out', Exact::qty($qty), '0', 'loss_cost');

                continue;
            }

            if ($note->type === StockNote::TYPE_STOCK_COUNT || $note->type === StockNote::TYPE_ADJUSTMENT) {
                $expected = Exact::of($line->expected_qty ?? $line->qty);
                $counted = Exact::of($line->qty);
                $diff = $counted->minus($expected);
                if ($diff->isZero()) {
                    continue;
                }
                if ($diff->isPositive()) {
                    $rows[] = $this->stockRow($note, $line, LedgerOrder::ADJUSTMENT_IN, 'in', Exact::qty($diff), 'avg');
                } else {
                    $rows[] = $this->stockRow($note, $line, LedgerOrder::LOSS, 'out', Exact::qty($diff->abs()), '0', 'loss_cost');
                }
            }
        }

        return $rows;
    }

    private function returnValueIn(StockNoteLine $line): string
    {
        $linked = $this->linkedReturnCost($line);

        return $linked ?? 'avg';
    }

    private function linkedReturnCost(StockNoteLine $line): ?string
    {
        if ($line->linked_document_line_id === null) {
            return null;
        }

        $saleLine = DocumentLine::query()->find($line->linked_document_line_id);
        if ($saleLine === null || bccomp((string) $saleLine->qty, '0', 3) <= 0) {
            return null;
        }

        $perUnit = Exact::of($saleLine->issue_cost ?? '0')->dividedBy(Exact::of($saleLine->qty), 6, RoundingMode::HalfUp);

        return Exact::money($perUnit->multipliedBy(Exact::of($line->qty)));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $docDate,
        string $movement,
        string $direction,
        string $qty,
        string $value,
        int $documentId,
        int $lineId,
        mixed $postedAt,
        ?int $batchId = null,
        ?string $costField = null,
    ): array {
        $row = [
            'doc_date' => $docDate,
            'movement' => $movement,
            'direction' => $direction,
            'qty' => $qty,
            'value' => $value,
            'document_id' => $documentId,
            'line_id' => $lineId,
            'posted_at' => $postedAt,
            'product_batch_id' => $batchId,
            'sort_id' => $lineId,
        ];
        if ($costField !== null) {
            $row['cost_field'] = $costField;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function stockRow(
        StockNote $note,
        StockNoteLine $line,
        string $movement,
        string $direction,
        string $qty,
        string $value,
        ?string $costField = null,
    ): array {
        $row = [
            'doc_date' => $note->doc_date->toDateString(),
            'movement' => $movement,
            'direction' => $direction,
            'qty' => $qty,
            'value' => $value,
            'stock_note_id' => $note->id,
            'stock_note_line_id' => $line->id,
            'posted_at' => $note->posted_at,
            'product_batch_id' => $line->product_batch_id,
            'sort_id' => $line->id,
        ];
        if ($costField === 'restore_cost') {
            $row['restore_field'] = $costField;
        }
        if ($costField === 'loss_cost') {
            $row['loss_field'] = $costField;
        }

        return $row;
    }

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

    private function refreshStockNoteCosts(int $productId): void
    {
        $noteIds = StockNoteLine::query()
            ->where('product_id', $productId)
            ->whereHas('stockNote', fn ($q) => $q->where('status', StockNote::STATUS_POSTED))
            ->pluck('stock_note_id')
            ->unique()
            ->all();

        foreach ($noteIds as $noteId) {
            $loss = StockNoteLine::query()->where('stock_note_id', $noteId)->sum('loss_cost');
            StockNote::query()->whereKey($noteId)->update(['stock_loss_total' => Exact::money($loss)]);
        }
    }
}
