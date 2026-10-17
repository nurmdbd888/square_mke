<?php

namespace App\Domain\Stock;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockLedgerEntry;
use App\Settings\SettingsStore;
use Illuminate\Support\Collection;

final class StockAlerts
{
    public function __construct(private readonly SettingsStore $settings) {}

    /**
     * @return Collection<int, Product>
     */
    public function lowStock(): Collection
    {
        $balances = $this->balances();

        return Product::query()
            ->whereNotNull('reorder_level')
            ->where('reorder_level', '>', 0)
            ->get()
            ->filter(function (Product $product) use ($balances) {
                $qty = $balances[$product->id] ?? '0';

                return bccomp($qty, (string) $product->reorder_level, 3) < 0;
            })
            ->values();
    }

    /**
     * @return Collection<int, ProductBatch>
     */
    public function nearExpiry(): Collection
    {
        $days = (int) $this->settings->get('near_expiry_days', now());
        $until = now()->addDays($days)->toDateString();

        return ProductBatch::query()
            ->with('product')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $until)
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->orderBy('expiry_date')
            ->get()
            ->filter(fn (ProductBatch $batch) => bccomp($this->batchQty($batch->id), '0', 3) > 0)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function balances(): array
    {
        $lastIds = StockLedgerEntry::query()
            ->select('product_id')
            ->selectRaw('MAX(id) as last_id')
            ->groupBy('product_id')
            ->pluck('last_id', 'product_id');

        $out = [];
        foreach (StockLedgerEntry::query()->whereIn('id', $lastIds->values())->get() as $entry) {
            $out[$entry->product_id] = (string) $entry->running_qty;
        }

        return $out;
    }

    private function batchQty(int $batchId): string
    {
        $rows = StockLedgerEntry::query()->where('product_batch_id', $batchId)->get();
        $in = '0';
        $out = '0';
        foreach ($rows as $row) {
            $in = bcadd($in, (string) $row->qty_in, 3);
            $out = bcadd($out, (string) $row->qty_out, 3);
        }

        return bcsub($in, $out, 3);
    }
}
