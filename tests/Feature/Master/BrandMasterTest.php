<?php

namespace Tests\Feature\Master;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($owner)->get(route('masters.index'))
            ->assertOk()
            ->assertSee(__('Brands'), false);

        $this->actingAs($owner)->post(route('masters.directory.store', 'brands'), [
            'name' => 'Preview Brand',
        ])->assertRedirect(route('masters.directory.index', 'brands'));

        $this->assertDatabaseHas('brands', ['name' => 'Preview Brand']);

        $this->actingAs($owner)->get(route('products.create'))
            ->assertOk()
            ->assertSee('<select name="brand"', false)
            ->assertSee('Preview Brand', false);

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-testid="quick-add-brand"', false)
            ->assertSee('Preview Brand', false);
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
