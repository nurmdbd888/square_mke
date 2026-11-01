<?php

namespace App\Domain\Import;

use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ProductMatcher
{
    /** @var Collection<int, Product>|null */
    private ?Collection $products = null;

    /** @var Collection<int, ProductAlias>|null */
    private ?Collection $aliases = null;

    public function match(?string $code, string $label, ?string $packSize): ?Product
    {
        if ($code !== null && trim($code) !== '') {
            $byCode = Product::query()
                ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])
                ->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        $normLabel = $this->normalizeName($label);
        if ($normLabel === '') {
            return null;
        }

        $aliases = $this->aliases()
            ->filter(fn (ProductAlias $alias): bool => $this->normalizeName($alias->name) === $normLabel)
            ->map(fn (ProductAlias $alias): ?Product => $alias->product)
            ->filter()
            ->unique('id')
            ->values();
        if ($aliases->count() === 1) {
            return $aliases->first();
        }

        $normPack = $packSize !== null && trim($packSize) !== ''
            ? $this->normalizePack($packSize)
            : null;
        $matches = $this->products()->filter(function (Product $product) use ($normLabel, $normPack): bool {
            foreach (array_filter([$product->name_en, $product->name_bn]) as $name) {
                if ($this->namePackMatches(
                    $normLabel,
                    $normPack,
                    $this->normalizeName((string) $name),
                    $product->pack_size,
                )) {
                    return true;
                }
            }

            return false;
        })->unique('id')->values();

        return $matches->count() === 1 ? $matches->first() : null;
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
        $this->aliases = null;
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return $this->products ??= Product::query()->get();
    }

    /**
     * @return Collection<int, ProductAlias>
     */
    private function aliases(): Collection
    {
        return $this->aliases ??= ProductAlias::query()->with('product')->get();
    }

    private function namePackMatches(string $label, ?string $pack, string $productName, ?string $productPack): bool
    {
        if ($label === $productName) {
            return $this->packsMatch($pack, $productPack);
        }

        if ($pack === null || ! $this->packsMatch($pack, $productPack)) {
            return false;
        }

        $nameWithoutPack = $this->removePack($productName, $productPack);
        if ($nameWithoutPack === $label) {
            return true;
        }

        return str_starts_with($label, $nameWithoutPack.' ')
            || str_starts_with($nameWithoutPack, $label.' ');
    }

    private function normalizeName(string $value): string
    {
        $value = InvoiceText::normalizeKey($value);
        $value = preg_replace('/(\d+(?:\.\d+)?)\s*(?:grams?|gm|g)\b/u', '$1g', $value) ?? $value;
        $value = preg_replace('/(\d+(?:\.\d+)?)\s*(?:milliliters?|millilitres?|ml)\b/u', '$1ml', $value) ?? $value;
        $value = preg_replace('/(\d+(?:\.\d+)?)\s*(?:kilograms?|kgs?|kg)\b/u', '$1kg', $value) ?? $value;
        $value = preg_replace('/(\d+(?:\.\d+)?)\s*(?:liters?|litres?|ltr|l)\b/u', '$1l', $value) ?? $value;
        $value = preg_replace('/(\d+(?:\.\d+)?)\s*(?:pieces?|pcs?|[\'’]s)\b/u', '$1pc', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function normalizePack(string $value): string
    {
        $normalized = $this->normalizeName($value);
        if (preg_match('/\d+(?:\.\d+)?(?:kg|ml|g|l|pc)\b/u', $normalized, $match) === 1) {
            return $match[0];
        }

        return $normalized;
    }

    private function packsMatch(?string $pack, ?string $productPack): bool
    {
        if ($pack === null || $productPack === null || trim($productPack) === '') {
            return true;
        }

        return $pack === $this->normalizePack($productPack);
    }

    private function removePack(string $productName, ?string $productPack): string
    {
        if ($productPack === null || trim($productPack) === '') {
            return $productName;
        }

        $pack = $this->normalizePack($productPack);
        $withoutPack = preg_replace(
            '/(?<![\p{L}\p{N}])'.preg_quote($pack, '/').'(?![\p{L}\p{N}])/u',
            ' ',
            $productName,
        );

        return trim(preg_replace('/\s+/u', ' ', $withoutPack ?? $productName) ?? $productName);
    }
}
