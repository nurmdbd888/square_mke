<?php

namespace Tests\Feature\Master;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_brands_are_available_from_masters_and_product_forms(): void
    {
        $owner = $this->owner();

        $this->assertDatabaseHas('brands', [
            'name' => Product::query()->whereNotNull('brand')->value('brand'),
        ]);
        foreach (Product::query()->whereNotNull('brand')->get() as $product) {
            $this->assertNotNull($product->brand_id);
            $this->assertSame($product->brand_id, $product->productType?->brand_id);
            $this->assertDatabaseHas('brands', ['id' => $product->brand_id, 'name' => $product->brand]);
        }

        $this->actingAs($owner)->get(route('masters.index'))
            ->assertOk()
            ->assertSee(__('Brands'), false);

        $this->actingAs($owner)->post(route('masters.directory.store', 'brands'), [
            'name' => 'Preview Brand',
            'purchase_discount_pct' => '5.25',
        ])->assertRedirect(route('masters.directory.index', 'brands'));

        $this->assertDatabaseHas('brands', ['name' => 'Preview Brand', 'purchase_discount_pct' => '5.25']);

        $this->actingAs($owner)->get(route('products.create'))
            ->assertOk()
            ->assertSee('<select name="brand_id"', false)
            ->assertSee('Preview Brand', false);

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-testid="quick-add-brand"', false)
            ->assertSee('Preview Brand', false);

        $type = ProductType::query()->where('brand_id', Brand::query()->where('name', 'Preview Brand')->value('id'))->firstOrFail();
        $this->actingAs($owner)->post(route('products.store'), [
            'name_en' => 'Discount Override Product',
            'brand_id' => $type->brand_id,
            'product_type_id' => $type->id,
            'purchase_discount_pct' => '2.50',
            'unit' => 'Pcs',
            'sd_pct' => '0',
            'vat_pct' => '15',
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('name_en', 'Discount Override Product')->firstOrFail();
        $this->assertSame('2.50', $product->purchase_discount_pct);
        $this->actingAs($owner)->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('purchase_discount_pct', false)
            ->assertSee('2.50', false);
    }

    public function test_brand_and_product_images_are_normalized_to_800_square(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('masters.directory.store', 'brands'), [
            'name' => 'Photo Brand',
            'image' => UploadedFile::fake()->image('brand.jpg', 400, 200),
        ])->assertRedirect();

        $brand = Brand::query()->where('name', 'Photo Brand')->firstOrFail();
        $this->assertNotNull($brand->image_path);
        $brandSize = getimagesize(Storage::disk('public')->path($brand->image_path));
        $this->assertSame([800, 800], [$brandSize[0], $brandSize[1]]);

        $type = ProductType::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->actingAs($owner)->post(route('products.store'), [
            'name_en' => 'Photo Product',
            'brand_id' => $brand->id,
            'product_type_id' => $type->id,
            'unit' => 'Pcs',
            'sd_pct' => '0',
            'vat_pct' => '15',
            'image' => UploadedFile::fake()->image('product.jpg', 120, 400),
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('name_en', 'Photo Product')->firstOrFail();
        $this->assertNotNull($product->image_path);
        $productSize = getimagesize(Storage::disk('public')->path($product->image_path));
        $this->assertSame([800, 800], [$productSize[0], $productSize[1]]);
    }

    public function test_duplicate_brand_names_are_rejected(): void
    {
        $owner = $this->owner();
        Brand::query()->create(['name' => 'Unique Brand']);

        $this->actingAs($owner)->from(route('masters.directory.create', 'brands'))
            ->post(route('masters.directory.store', 'brands'), ['name' => 'Unique Brand'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Brand::query()->where('name', 'Unique Brand')->count());
    }
}
