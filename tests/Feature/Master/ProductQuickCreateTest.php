<?php

namespace Tests\Feature\Master;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_forms_offer_product_quick_create_to_product_managers(): void
    {
        $owner = $this->owner();

        foreach (['sales.create', 'purchases.create'] as $route) {
            $this->actingAs($owner)->get(route($route))
                ->assertOk()
                ->assertSee('data-testid="product-search-empty"', false)
                ->assertSee('data-testid="product-quick-add"', false)
                ->assertSee('data-testid="product-quick-add-dialog"', false)
                ->assertSee('productCreateUrl', false)
                ->assertSee(__('The product will be available in sales, purchases and stock.'), false);
        }
    }

    public function test_json_product_store_creates_a_shared_product_and_returns_catalog_data(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)
            ->postJson(route('products.store'), [
                'code' => 'QS-1',
                'name_en' => 'Quick Soap',
                'name_bn' => 'দ্রুত সাবান',
                'brand' => 'Quick Brand',
                'unit' => 'Pcs',
                'buy_price' => '12.5000',
                'sell_price' => '15.0000',
                'sd_pct' => '0',
                'vat_pct' => '15',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'id', 'code', 'brand', 'pack_size', 'name', 'name_bn', 'searchText',
                'buy', 'sell', 'sd', 'vat',
            ])
            ->assertJsonPath('code', 'QS-1')
            ->assertJsonPath('name', 'Quick Soap')
            ->assertJsonPath('buy', '12.5000')
            ->assertJsonPath('sell', '15.0000');

        $product = Product::query()->findOrFail($response->json('id'));
        $this->assertSame('Quick Brand', $product->brand);
        $this->assertTrue($product->aliases()->where('name', 'দ্রুত সাবান')->exists());

        $this->actingAs($owner)->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Quick Soap', false);
        $this->actingAs($owner)->get(route('stock.index'))
            ->assertOk()
            ->assertSee('data-testid="stock-row-'.$product->id.'"', false)
            ->assertSee('Quick Soap', false);
    }

    public function test_product_quick_create_is_hidden_and_forbidden_without_product_manage_permission(): void
    {
        $salesRep = $this->userWithRole('sr');

        $this->actingAs($salesRep)->get(route('sales.create'))
            ->assertOk()
            ->assertDontSee('data-testid="product-quick-add"', false);

        $this->actingAs($salesRep)
            ->postJson(route('products.store'), [
                'name_en' => 'Unauthorized product',
                'unit' => 'Pcs',
                'sd_pct' => '0',
                'vat_pct' => '15',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['name_en' => 'Unauthorized product']);
    }
}
