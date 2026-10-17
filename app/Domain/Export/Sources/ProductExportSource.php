<?php

namespace App\Domain\Export\Sources;

use App\Domain\Export\ExportSource;
use App\Models\Product;
use Illuminate\Support\LazyCollection;

final class ProductExportSource implements ExportSource
{
    public function allColumns(): array
    {
        return ['code', 'name_en', 'name_bn', 'brand', 'pack_size', 'unit', 'buy_price', 'sell_price', 'sd_pct', 'vat_pct'];
    }

    public function defaultColumns(): array
    {
        return ['code', 'name_en', 'brand', 'pack_size', 'buy_price', 'sell_price'];
    }

    public function rows(array $filters, array $columns): array
    {
        $columns = array_values(array_intersect($this->allColumns(), $columns));
        $status = $filters['status'] ?? 'active';

        $query = Product::query()
            ->when($status === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes((string) $term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('name_en', 'like', $like)->orWhere('name_bn', 'like', $like)->orWhere('code', 'like', $like)->orWhere('brand', 'like', $like));
            })
            ->when($filters['brand'] ?? null, fn ($q, $brand) => $q->where('brand', $brand))
            ->orderBy('name_en');

        $headers = array_map(fn ($c) => str_replace('_', ' ', ucfirst($c)), $columns);

        $rows = LazyCollection::make(function () use ($query, $columns) {
            foreach ($query->cursor() as $product) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = match ($col) {
                        'code' => $product->code,
                        'name_en' => $product->name_en,
                        'name_bn' => $product->name_bn,
                        'brand' => $product->brand,
                        'pack_size' => $product->pack_size,
                        'unit' => $product->unit,
                        'buy_price' => $product->buy_price,
                        'sell_price' => $product->sell_price,
                        'sd_pct' => $product->sd_pct,
                        'vat_pct' => $product->vat_pct,
                        default => null,
                    };
                }
                yield $line;
            }
        });

        return ['headers' => $headers, 'rows' => $rows];
    }
}
