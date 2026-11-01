<?php

namespace Tests\Feature\Import;

use App\Domain\Import\ProductMatcher;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_it_matches_product_names_with_punctuation_and_pack_format_variations(): void
    {
        $product = Product::query()->where('code', 'P01')->firstOrFail();

        $matched = app(ProductMatcher::class)->match(
            null,
            'Chaka Advanced Ball Soap (125 gm)',
            '125 GM',
        );

        $this->assertSame($product->id, $matched?->id);
    }

    public function test_it_matches_a_unique_product_prefix_when_pack_size_disambiguates_it(): void
    {
        $product = Product::query()->where('code', 'P01')->firstOrFail();

        $matched = app(ProductMatcher::class)->match(
            null,
            'Chaka Advanced Ball Soap',
            '125 gm',
        );

        $this->assertSame($product->id, $matched?->id);
    }

    public function test_it_matches_sales_descriptions_that_omit_pack_size_from_the_product_name(): void
    {
        $product = Product::query()->where('code', 'S02')->firstOrFail();

        $matched = app(ProductMatcher::class)->match(
            null,
            'Meril Petroleum Jelly (39 Pcs Jar)',
            '15ml',
        );

        $this->assertSame($product->id, $matched?->id);
    }

    public function test_it_does_not_guess_when_multiple_products_match_the_same_name_and_pack(): void
    {
        $matched = app(ProductMatcher::class)->match(
            null,
            'Meril Petroleum Jelly',
            '15 ml',
        );

        $this->assertNull($matched);
    }
}
