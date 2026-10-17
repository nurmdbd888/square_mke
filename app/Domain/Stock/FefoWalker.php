<?php

namespace App\Domain\Stock;

use App\Domain\Costing\StockState;
use App\Domain\Money\Exact;
use App\Models\ProductBatch;
use Brick\Math\BigDecimal;

/**
 * Per-batch stock states with FEFO issue (earliest expiry first).
 */
final class FefoWalker
{
    /** @var array<int, StockState> */
    private array $batches = [];

    private StockState $total;

    public function __construct()
    {
        $this->total = new StockState;
    }

    public function totalState(): StockState
    {
        return $this->total;
    }

    /**
     * @return list<int> batch ids sorted by expiry (nulls last), then id
     */
    public function batchOrder(int $productId): array
    {
        $ids = array_keys($this->batches);
        if ($ids === []) {
            return [];
        }

        $meta = ProductBatch::query()->whereIn('id', $ids)->where('product_id', $productId)->get()->keyBy('id');

        usort($ids, function (int $a, int $b) use ($meta) {
            $ea = $meta->get($a)?->expiry_date?->toDateString() ?? '9999-12-31';
            $eb = $meta->get($b)?->expiry_date?->toDateString() ?? '9999-12-31';
            $cmp = strcmp($ea, $eb);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a <=> $b;
        });

        return $ids;
    }

    public function receive(?int $batchId, BigDecimal|string $qty, BigDecimal|string $value): void
    {
        $this->total->receive($qty, $value);
        if ($batchId !== null) {
            $this->batches[$batchId] ??= new StockState;
            $this->batches[$batchId]->receive($qty, $value);
        }
    }

    /**
     * Issue qty using FEFO across batches, then unbatched pool on total if needed.
     *
     * @return list<array{batch_id: ?int, qty: string, cost: string}>
     */
    public function issueFefo(int $productId, BigDecimal|string $qty, ?string $fallbackUnitCost = null): array
    {
        $remaining = Exact::of($qty);
        $splits = [];

        foreach ($this->batchOrder($productId) as $batchId) {
            if ($remaining->isZero()) {
                break;
            }
            $state = $this->batches[$batchId] ?? new StockState;
            if (! $state->qty->isPositive()) {
                continue;
            }
            $take = $remaining->isLessThan($state->qty) ? $remaining : $state->qty;
            $cost = $state->issue($take, $fallbackUnitCost);
            $this->reduceTotal($take, $cost);
            $splits[] = ['batch_id' => $batchId, 'qty' => Exact::qty($take), 'cost' => $cost];
            $remaining = $remaining->minus($take);
            $this->batches[$batchId] = $state;
        }

        if ($remaining->isPositive()) {
            $cost = $this->total->issue($remaining, $fallbackUnitCost);
            $splits[] = ['batch_id' => null, 'qty' => Exact::qty($remaining), 'cost' => $cost];
        }

        return $splits;
    }

    private function reduceTotal(BigDecimal|string $qty, string $cost): void
    {
        $this->total->qty = $this->total->qty->minus(Exact::of($qty));
        $this->total->value = $this->total->value->minus(Exact::of($cost));
    }

    /**
     * Issue at product average (no batch split) — used for loss lines.
     */
    public function issueAverage(BigDecimal|string $qty, ?string $fallbackUnitCost = null): string
    {
        return $this->total->issue($qty, $fallbackUnitCost);
    }
}
