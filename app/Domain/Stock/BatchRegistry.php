<?php

namespace App\Domain\Stock;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Carbon;

/**
 * Creates or finds a batch row for purchase and return lines.
 */
final class BatchRegistry
{
    public function forLine(Product $product, ?string $batchNo, Carbon|string|null $expiry): ?ProductBatch
    {
        $batchNo = trim((string) $batchNo);
        if ($batchNo === '') {
            return null;
        }

        $expiryDate = $expiry === null || $expiry === ''
            ? null
            : Carbon::parse($expiry)->toDateString();

        return ProductBatch::query()->firstOrCreate(
            ['product_id' => $product->id, 'batch_no' => $batchNo],
            ['expiry_date' => $expiryDate],
        );
    }
}
