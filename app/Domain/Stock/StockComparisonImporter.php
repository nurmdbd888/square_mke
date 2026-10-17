<?php

namespace App\Domain\Stock;

use App\Models\Product;
use App\Models\StockComparison;
use App\Models\StockComparisonLine;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Compares on-hand qty to a CSV export (code, name, qty columns).
 */
final class StockComparisonImporter
{
    /**
     * @return array{comparison: StockComparison, rows: int}
     */
    public function import(UploadedFile $file, ?User $by = null): array
    {
        return DB::transaction(function () use ($file, $by) {
            $comparison = StockComparison::query()->create([
                'original_name' => $file->getClientOriginalName(),
                'created_by' => $by?->id,
            ]);

            $their = $this->parseCsv($file->getRealPath());
            $ours = $this->currentBalances();

            $seenCodes = [];
            $count = 0;

            foreach ($their as $row) {
                $code = $row['code'];
                $seenCodes[$code] = true;
                $product = $code !== '' ? Product::query()->where('code', $code)->first() : null;

                if ($product === null && $row['name'] !== '') {
                    $product = Product::query()
                        ->where('name_en', $row['name'])
                        ->orWhere('name_bn', $row['name'])
                        ->first();
                }

                $ourQty = $product !== null ? ($ours[$product->id] ?? '0') : null;
                $status = StockComparisonLine::STATUS_MATCH;

                if ($product === null) {
                    $status = StockComparisonLine::STATUS_UNKNOWN_PRODUCT;
                } elseif ($ourQty !== null && bccomp($ourQty, $row['qty'], 3) !== 0) {
                    $status = StockComparisonLine::STATUS_QTY_DIFF;
                }

                StockComparisonLine::query()->create([
                    'stock_comparison_id' => $comparison->id,
                    'product_code' => $code !== '' ? $code : null,
                    'product_name' => $row['name'] !== '' ? $row['name'] : null,
                    'product_id' => $product?->id,
                    'our_qty' => $ourQty,
                    'their_qty' => $row['qty'],
                    'status' => $status,
                ]);
                $count++;
            }

            foreach ($ours as $productId => $qty) {
                $product = Product::query()->find($productId);
                if ($product === null || isset($seenCodes[$product->code ?? ''])) {
                    continue;
                }
                StockComparisonLine::query()->create([
                    'stock_comparison_id' => $comparison->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name_en,
                    'product_id' => $product->id,
                    'our_qty' => $qty,
                    'their_qty' => null,
                    'status' => StockComparisonLine::STATUS_ONLY_OURS,
                ]);
                $count++;
            }

            return ['comparison' => $comparison->load('lines'), 'rows' => $count];
        });
    }

    /**
     * @return array<int, string> product_id => qty
     */
    private function currentBalances(): array
    {
        $lastIds = StockLedgerEntry::query()
            ->select('product_id')
            ->selectRaw('MAX(id) as last_id')
            ->groupBy('product_id')
            ->pluck('last_id', 'product_id');

        $entries = StockLedgerEntry::query()->whereIn('id', $lastIds->values())->get();

        $out = [];
        foreach ($entries as $entry) {
            $out[$entry->product_id] = (string) $entry->running_qty;
        }

        return $out;
    }

    /**
     * @return list<array{code: string, name: string, qty: string}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $map = $this->mapHeaders($header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === []) {
                continue;
            }
            $code = trim((string) ($data[$map['code']] ?? ''));
            $name = trim((string) ($data[$map['name']] ?? ''));
            $qtyRaw = trim((string) ($data[$map['qty']] ?? '0'));
            $qtyRaw = str_replace(',', '', $qtyRaw);
            if ($code === '' && $name === '') {
                continue;
            }
            $rows[] = ['code' => $code, 'name' => $name, 'qty' => $qtyRaw !== '' ? $qtyRaw : '0'];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $header
     * @return array{code: int, name: int, qty: int}
     */
    private function mapHeaders(array $header): array
    {
        $code = 0;
        $name = 1;
        $qty = 2;

        foreach ($header as $i => $col) {
            $key = strtolower(trim((string) $col));
            if (in_array($key, ['code', 'product code', 'sku', 'item code'], true)) {
                $code = $i;
            } elseif (in_array($key, ['name', 'product', 'product name', 'item'], true)) {
                $name = $i;
            } elseif (in_array($key, ['qty', 'quantity', 'stock', 'on hand', 'balance'], true)) {
                $qty = $i;
            }
        }

        return ['code' => $code, 'name' => $name, 'qty' => $qty];
    }
}
