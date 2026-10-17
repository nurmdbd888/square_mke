<?php

namespace Tests\Feature;

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
}
