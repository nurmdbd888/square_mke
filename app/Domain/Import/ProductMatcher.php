<?php

namespace App\Domain\Import;

use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use Illuminate\Support\Str;

final class ProductMatcher
{
    public function match(?string $code, string $label, ?string $packSize): ?Product
    {
        if ($code !== null && trim($code) !== '') {
            $byCode = Product::query()->where('code', trim($code))->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        $normLabel = InvoiceText::normalizeKey($label);
        $normPack = $packSize !== null && trim($packSize) !== ''
            ? InvoiceText::normalizeKey($packSize)
            : null;

        $products = Product::query()->get();
        foreach ($products as $product) {
            $names = array_filter([
                $product->name_en,
                $product->name_bn,
            ]);
            foreach ($names as $name) {
                if ($this->namePackMatches($normLabel, $normPack, InvoiceText::normalizeKey((string) $name), $product->pack_size)) {
                    return $product;
                }
            }
        }

        $alias = ProductAlias::query()
            ->where('name', $label)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($label)])
            ->first();
        if ($alias !== null) {
            return $alias->product;
        }

        foreach ($products as $product) {
            if ($product->name_bn !== null && InvoiceText::normalizeKey($product->name_bn) === $normLabel) {
                return $product;
            }
        }

        return null;
    }

    public function rememberAlias(Product $product, string $label, ?User $by = null): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        ProductAlias::query()->firstOrCreate(
            ['product_id' => $product->id, 'name' => $label],
        );
    }

    private function namePackMatches(string $label, ?string $pack, string $productName, ?string $productPack): bool
    {
        if ($label === $productName) {
            if ($pack === null || $productPack === null) {
                return true;
            }

            return InvoiceText::normalizeKey($productPack) === $pack
                || str_contains($label, InvoiceText::normalizeKey($productPack));
        }

        if (str_contains($label, $productName) || str_contains($productName, $label)) {
            if ($pack === null || $productPack === null) {
                return true;
            }

            return str_contains($label, InvoiceText::normalizeKey($productPack));
        }

        return false;
    }
}
