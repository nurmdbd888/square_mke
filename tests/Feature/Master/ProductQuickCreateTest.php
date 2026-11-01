<?php

namespace Tests\Feature\Master;

use App\Models\Brand;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_forms_offer_product_quick_create_to_product_managers(): void
    {
        $owner = $this->owner();
        $brand = Brand::query()->create(['name' => 'Quick Brand', 'purchase_discount_pct' => '5.00']);
        ProductType::query()->create(['brand_id' => $brand->id, 'name' => 'Soap']);

        foreach (['sales.create', 'purchases.create'] as $route) {
            $this->actingAs($owner)->get(route($route))
                ->assertOk()
                ->assertSee('data-testid="product-search-empty"', false)
                ->assertSee('data-testid="product-quick-add"', false)
                ->assertSee('data-testid="product-quick-add-dialog"', false)
                ->assertSee('data-testid="quick-add-brand"', false)
                ->assertSee('Quick Brand', false)
                ->assertSee('productCreateUrl', false)
                ->assertSee(__('The product will be available in sales, purchases and stock.'), false);
        }
    }

    public function test_json_product_store_creates_a_shared_product_and_returns_catalog_data(): void
    {
        $owner = $this->owner();
        $brand = Brand::query()->create(['name' => 'Quick Brand', 'purchase_discount_pct' => '5.00']);
        $type = ProductType::query()->create(['brand_id' => $brand->id, 'name' => 'Soap']);

        $response = $this->actingAs($owner)
            ->postJson(route('products.store'), [
                'code' => 'QS-1',
                'name_en' => 'Quick Soap',
                'name_bn' => 'দ্রুত সাবান',
                'brand' => 'Quick Brand',
                'brand_id' => $brand->id,
                'product_type_id' => $type->id,
                'pack_size' => '100 g',
                'unit' => 'Pcs',
                'buy_price' => '12.5000',
                'purchase_discount_pct' => '',
                'sell_price' => '15.0000',
                'sd_pct' => '0',
                'vat_pct' => '15',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'id', 'code', 'brand', 'brand_id', 'brandId', 'product_type_id', 'typeId', 'pack_size', 'name', 'name_bn', 'searchText',
                'buy', 'sell', 'sd', 'vat', 'purchase_discount_pct',
            ])
            ->assertJsonPath('code', 'QS-1')
            ->assertJsonPath('name', 'Quick Soap')
            ->assertJsonPath('buy', '12.5000')
            ->assertJsonPath('sell', '15.0000')
            ->assertJsonPath('purchase_discount_pct', '5.00');

        $product = Product::query()->findOrFail($response->json('id'));
        $this->assertSame('Quick Brand', $product->brand);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($type->id, $product->product_type_id);
        $this->assertSame('100 g', $product->pack_size);
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

    public function test_sales_form_suggests_the_most_recent_posted_sale_price(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Price Shop']);
        $product = Product::query()->create([
            'name_en' => 'Price Soap',
            'unit' => 'Pcs',
            'sell_price' => '12.0000',
        ]);

        foreach ([['2026-10-10', '14.0000'], ['2026-10-11', '17.0000']] as [$date, $price]) {
            $document = Document::query()->create([
                'type' => Document::TYPE_SALE,
                'number' => 'S-PRICE-'.$date,
                'retailer_id' => $retailer->id,
                'doc_date' => $date,
                'status' => Document::STATUS_POSTED,
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id,
                'line_no' => 1,
                'product_id' => $product->id,
                'kind' => DocumentLine::KIND_NORMAL,
                'qty' => '1',
                'unit_price' => $price,
                'line_value' => $price,
            ]);
        }

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('\u0022sell\u0022:\u002217.00\u0022', false);
    }
}
