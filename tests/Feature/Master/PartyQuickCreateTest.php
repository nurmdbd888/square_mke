<?php

namespace Tests\Feature\Master;

use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_form_shows_add_retailer_when_permitted(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-testid="party-quick-add"', false)
            ->assertSee(__('Add retailer'), false)
            ->assertSee('retailers', false);
    }

    public function test_purchase_form_shows_add_supplier_when_permitted(): void
    {
        $owner = $this->owner();
        $this->assertTrue($owner->can('masters.manage'));

        $html = $this->actingAs($owner)->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('data-testid="party-quick-add"', false)
            ->assertSee(__('Add supplier'), false)
            ->getContent();

        $this->assertTrue(
            str_contains($html, 'masters') && str_contains($html, 'suppliers'),
            'Purchase form should include suppliers quick-create URL'
        );
    }

    public function test_json_store_creates_retailer_and_returns_party(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->postJson(route('retailers.store'), [
                'name' => 'New Shop',
                'code' => 'NS-1',
                'phone' => '01711112222',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'New Shop')
            ->assertJsonPath('code', 'NS-1')
            ->assertJsonStructure(['id', 'code', 'name']);

        $this->assertDatabaseHas('retailers', ['name' => 'New Shop', 'code' => 'NS-1']);
    }

    public function test_json_store_creates_supplier_and_returns_party(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->postJson(route('masters.directory.store', 'suppliers'), [
                'name' => 'Square Depot',
                'code' => 'SD-9',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Square Depot')
            ->assertJsonPath('code', 'SD-9');

        $this->assertDatabaseHas('suppliers', ['name' => 'Square Depot', 'code' => 'SD-9']);
        $this->assertInstanceOf(Supplier::class, Supplier::query()->where('code', 'SD-9')->first());
        $this->assertInstanceOf(Retailer::class, Retailer::query()->make());
    }

    public function test_html_store_still_redirects(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('retailers.store'), [
                'name' => 'Form Shop',
            ])
            ->assertRedirect(route('retailers.index'));

        $this->assertDatabaseHas('retailers', ['name' => 'Form Shop']);
    }
}
