<?php

namespace App\Support\Catalog;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductType;

final class ProductCatalogLinker
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function withBrandAndType(array $attributes, ?Product $existing = null): array
    {
        if (! array_key_exists('brand', $attributes)) {
            return $attributes;
        }

        $name = trim((string) $attributes['brand']);
        if ($name === '') {
            $attributes['brand'] = null;
            $attributes['brand_id'] = null;
            $attributes['product_type_id'] = null;

            return $attributes;
        }

        $brand = Brand::withTrashed()->firstOrCreate(['name' => $name]);
        $type = $existing?->product_type_id
            ? ProductType::withTrashed()
                ->whereKey($existing->product_type_id)
                ->where('brand_id', $brand->id)
                ->first()
            : null;
        $type ??= ProductType::withTrashed()->firstOrCreate([
            'brand_id' => $brand->id,
            'name' => 'Uncategorized',
        ]);

        $attributes['brand'] = $brand->name;
        $attributes['brand_id'] = $brand->id;
        $attributes['product_type_id'] = $type->id;

        return $attributes;
    }
}
