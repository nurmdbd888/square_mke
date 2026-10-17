<?php

namespace App\Domain\Pricing;

use App\Models\PriceListItem;
use App\Models\Product;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The sell price of a product on a date: the price-list row in force that
 * day, or the product's own sell price when the list has none.
 */
final class PriceLookup
{
    public function sell(Product $product, ?int $priceListId, CarbonInterface|string|null $on = null): ?string
    {
        $date = ($on === null ? now() : Carbon::parse($on))->toDateString();

        if ($priceListId !== null) {
            $row = PriceListItem::query()
                ->where('price_list_id', $priceListId)
                ->where('product_id', $product->id)
                ->whereDate('effective_from', '<=', $date)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($row !== null) {
                return $this->plain($row->price);
            }
        }

        return $product->sell_price === null ? null : $this->plain($product->sell_price);
    }

    private function plain(mixed $value): string
    {
        $plain = BigDecimal::of((string) $value)->toScale(4)->__toString();

        return str_contains($plain, '.') ? rtrim(rtrim($plain, '0'), '.') : $plain;
    }
}
