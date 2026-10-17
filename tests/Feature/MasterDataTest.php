<?php

namespace Tests\Feature;

use App\Domain\Pricing\PriceLookup;
use App\Models\CustomField;
use App\Models\OpeningBalance;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_loads_the_24_sample_products(): void
    {
        $this->assertSame(24, Product::query()->count());

        $soap = Product::query()->where('code', 'P01')->firstOrFail();
        $this->assertSame('Chaka Advanced Ball Soap - 125gm', $soap->name_en);
        $this->assertTrue(BigDecimal::of((string) $soap->buy_price)->isEqualTo('23.83'));
        $this->assertTrue(BigDecimal::of((string) $soap->buy_price_exact)->isEqualTo('23.8260'));
        $this->assertTrue(BigDecimal::of((string) $soap->sd_pct)->isEqualTo('0'));
        $this->assertTrue(BigDecimal::of((string) $soap->vat_pct)->isEqualTo('15'));
        $this->assertTrue($soap->aliases()->where('name', $soap->name_bn)->exists());

        $this->assertTrue(BigDecimal::of((string) Product::query()->where('code', 'P05')->value('sd_pct'))->isEqualTo('5'));
        $this->assertTrue(BigDecimal::of((string) Product::query()->where('code', 'P09')->value('sd_pct'))->isEqualTo('10'));
        $this->assertTrue(BigDecimal::of((string) Product::query()->where('code', 'P14')->value('sd_pct'))->isEqualTo('15'));

        $salesOnly = Product::query()->where('code', 'S01')->firstOrFail();
        $this->assertNull($salesOnly->buy_price);
        $this->assertNull($salesOnly->buy_price_exact);
        $this->assertTrue(BigDecimal::of((string) $salesOnly->sell_price)->isEqualTo('302'));
    }

    public function test_price_lookup_uses_the_row_in_force_on_that_date(): void
    {
        $entry = $this->userWithRole('data_entry');
        $product = Product::query()->create([
            'name_en' => 'Priced soap',
            'sell_price' => '10.5',
            'unit' => 'Pcs',
            'sd_pct' => '0',
            'vat_pct' => '15',
        ]);

        $this->actingAs($entry)->post('/masters/price-lists', ['name' => 'Trade'])->assertRedirect();
        $list = PriceList::query()->where('name', 'Trade')->firstOrFail();

        $this->post("/masters/price-lists/{$list->id}/items", [
            'product_id' => $product->id,
            'price' => '20',
            'effective_from' => '2026-01-01',
        ])->assertRedirect();
        $this->post("/masters/price-lists/{$list->id}/items", [
            'product_id' => $product->id,
            'price' => '25',
            'effective_from' => '2026-06-01',
        ])->assertRedirect();

        $lookup = app(PriceLookup::class);
        $this->assertSame('10.5', $lookup->sell($product, $list->id, '2025-12-01'));
        $this->assertSame('20', $lookup->sell($product, $list->id, '2026-03-01'));
        $this->assertSame('25', $lookup->sell($product, $list->id, '2026-10-17'));
        $this->assertSame('10.5', $lookup->sell($product, null, '2026-10-17'));
        $this->get("/masters/price-lists/{$list->id}")->assertOk()->assertSee('Trade')->assertSee('01-01-2026');

        $this->post("/masters/price-lists/{$list->id}/items", [
            'product_id' => $product->id,
            'price' => '21',
            'effective_from' => '2026-01-01',
        ])->assertRedirect()->assertSessionHas('status', 'Price replaced for that date.');
        $this->assertSame('21', $lookup->sell($product->fresh(), $list->id, '2026-03-01'));
        $this->assertSame(1, PriceListItem::query()->where('price_list_id', $list->id)->whereDate('effective_from', '2026-01-01')->count());
    }

    public function test_a_soft_deleted_retailer_can_be_restored_and_the_phone_is_normalised(): void
    {
        $this->actingAs($this->owner())->post('/retailers', [
            'name' => 'Corner Shop',
            'phone' => '+880 1711-459077',
        ])->assertRedirect(route('retailers.index'));

        $retailer = Retailer::query()->where('name', 'Corner Shop')->firstOrFail();
        $this->assertSame('01711459077', $retailer->phone);

        $this->delete("/retailers/{$retailer->id}")->assertRedirect();
        $this->assertSoftDeleted('retailers', ['id' => $retailer->id]);

        $this->post("/retailers/{$retailer->id}/restore")->assertRedirect();
        $this->assertNull($retailer->fresh()->deleted_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'restored',
            'auditable_type' => 'retailer',
            'auditable_id' => $retailer->id,
        ]);
    }

    public function test_product_columns_bulk_edit_and_custom_fields(): void
    {
        $entry = $this->userWithRole('data_entry');

        $this->actingAs($this->userWithRole('viewer'))->get('/products')->assertOk()->assertDontSee('Add product');
        $this->actingAs($entry)->get('/products')->assertOk()->assertSee('Add product');

        $this->actingAs($entry)->get('/products?cols_set=1&cols[]=brand')
            ->assertOk()
            ->assertDontSee('data-col="code"', false)
            ->assertSee('Chaka Advanced Ball Soap - 125gm');

        $ids = Product::query()->whereIn('code', ['P01', 'P02'])->pluck('id')->all();
        $this->actingAs($entry)->post('/products/bulk', [
            'ids' => $ids,
            'field' => 'brand',
            'value' => 'BulkBrand',
        ])->assertRedirect();
        $this->assertSame('BulkBrand', Product::query()->where('code', 'P01')->value('brand'));
        $this->assertSame('BulkBrand', Product::query()->where('code', 'P02')->value('brand'));

        $this->actingAs($entry)->post('/masters/custom-fields', [
            'entity' => 'product',
            'key' => 'shelf',
            'label' => 'Shelf',
            'type' => 'string',
            'sort' => 1,
        ])->assertRedirect();
        $this->assertInstanceOf(CustomField::class, CustomField::query()->where('key', 'shelf')->first());

        $this->get('/products/create')->assertOk()->assertSee('Shelf');
        $this->post('/products', [
            'name_en' => 'Shelf Soap',
            'unit' => 'Pcs',
            'sd_pct' => '0',
            'vat_pct' => '15',
            'aliases' => "Shelf Soap alias\n",
            'custom' => ['shelf' => 'A1', 'nope' => 'x'],
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('name_en', 'Shelf Soap')->firstOrFail();
        $this->assertSame(['shelf' => 'A1'], $product->custom);
        $this->assertTrue($product->aliases()->where('name', 'Shelf Soap alias')->exists());
    }

    public function test_supplier_phone_and_opening_balances_are_stored_without_posting(): void
    {
        $entry = $this->userWithRole('data_entry');

        $this->actingAs($entry)->post('/masters/suppliers', [
            'name' => 'Square Toiletries',
            'phone' => '8801711459077',
        ])->assertRedirect();
        $supplier = Supplier::query()->where('name', 'Square Toiletries')->firstOrFail();
        $this->assertSame('01711459077', $supplier->phone);

        $this->delete("/masters/suppliers/{$supplier->id}")->assertRedirect();
        $this->post("/masters/suppliers/{$supplier->id}/restore")->assertRedirect();
        $this->assertNull($supplier->fresh()->deleted_at);

        $this->post('/masters/opening-balances', [
            'kind' => 'cash',
            'as_of' => '2026-07-01',
            'amount' => '1500.50',
            'note' => 'Till',
        ])->assertRedirect();
        $this->post('/masters/opening-balances', [
            'kind' => 'stock',
            'as_of' => '2026-07-01',
            'amount' => '100',
            'qty' => '4',
            'product' => 'P01',
        ])->assertRedirect();

        $this->assertDatabaseHas('opening_balances', ['kind' => 'cash', 'note' => 'Till']);
        $stock = OpeningBalance::query()->where('kind', 'stock')->firstOrFail();
        $this->assertSame(Product::query()->where('code', 'P01')->value('id'), $stock->product_id);
        $this->get('/masters/opening-balances')->assertOk()->assertSee('not posted to stock or the books yet');
    }

    public function test_ten_thousand_retailers_stay_within_the_list_budget(): void
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= 10000; $i++) {
            $rows[] = [
                'name' => $i === 5000 ? 'Needle Shop' : sprintf('Retailer %05d', $i),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('retailers')->insert($chunk);
        }

        $this->actingAs($this->owner());
        memory_reset_peak_usage();
        $floor = memory_get_peak_usage(true);
        $started = hrtime(true);

        $response = $this->get('/retailers');

        $seconds = (hrtime(true) - $started) / 1e9;
        $delta = memory_get_peak_usage(true) - $floor;

        $response->assertOk()->assertSee('Showing 1 to 50 of 10000', false);
        $this->assertLessThan(30, $seconds, 'Retailer list took '.$seconds.'s');
        $this->assertLessThan(128 * 1024 * 1024, $delta, 'Retailer list grew by '.$delta.' bytes');

        $this->get('/retailers?q=Needle')->assertOk()->assertSee('Needle Shop')->assertDontSee('Retailer 00001');
    }
}
