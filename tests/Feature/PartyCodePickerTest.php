<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyCodePickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_sale_form_renders_retailer_code_search(): void
    {
        $owner = $this->owner();
        Retailer::query()->create(['code' => '545996', 'name' => 'Ms Jamuna Genarel Store']);

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('partyCodePicker', false)
            ->assertSee('545996', false)
            ->assertSee('Ms Jamuna Genarel Store', false)
            ->assertSee('name="retailer_id"', false);
    }

    public function test_add_sale_form_uses_compact_product_search_layout(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-testid="document-lines"', false)
            ->assertSee(__('Search on the left'), false);
    }

    public function test_product_search_catalog_includes_bangla_names(): void
    {
        $owner = $this->owner();
        Product::query()->where('code', 'P01')->update(['name_bn' => 'চাকা সাবান']);

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('চাকা সাবান', false)
            ->assertSee('name_bn', false);
    }
}
