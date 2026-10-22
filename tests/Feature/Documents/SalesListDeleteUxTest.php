<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesListDeleteUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_index_shows_delete_beside_add_when_permitted(): void
    {
        $owner = $this->owner();
        $this->assertTrue($owner->can('sales.delete'));

        $this->actingAs($owner)->get(route('sales.index'))
            ->assertOk()
            ->assertSee(__('Add sale'), false)
            ->assertSee('data-testid="documents-bulk-delete"', false)
            ->assertSee(__('Delete'), false)
            ->assertSee('id="documents-bulk-form"', false);
    }

    public function test_viewer_does_not_see_sales_delete_button(): void
    {
        $viewer = $this->userWithRole('viewer');
        $this->assertFalse($viewer->can('sales.delete'));

        $this->actingAs($viewer)->get(route('sales.index'))
            ->assertOk()
            ->assertDontSee('data-testid="documents-bulk-delete"', false);
    }

    public function test_sale_show_uses_back_link_button(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $document = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-BACK-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_DRAFT,
            'subtotal' => '0',
            'net_total' => '0',
        ]);

        $this->actingAs($owner)->get(route('sales.show', $document))
            ->assertOk()
            ->assertSee('data-testid="back-link"', false)
            ->assertSee(__('Back to sales'), false)
            ->assertSee('btn-secondary', false)
            ->assertDontSee('text-sm text-brand-700 underline">'.__('Back to sales'), false);
    }

    public function test_purchase_index_also_has_header_delete_when_permitted(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('data-testid="documents-bulk-delete"', false);
    }
}
