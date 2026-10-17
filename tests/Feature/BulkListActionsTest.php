<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkListActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_list_supports_bulk_post(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Bulk soap', 'unit' => 'Pcs', 'buy_price' => '10']);
        $supplier = Supplier::query()->create(['name' => 'Bulk supplier']);

        $this->actingAs($owner)->post('/purchases', [
            'number' => 'PO-BULK-1',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-01',
            'action' => 'save',
            'lines' => [
                ['product_id' => $product->id, 'qty' => '1', 'unit_price' => '100'],
            ],
        ])->assertRedirect();

        $document = Document::query()->where('number', 'PO-BULK-1')->first();
        $this->assertNotNull($document);
        $this->assertTrue($document->isDraft());

        $this->actingAs($owner)->post(route('purchases.bulk'), [
            'ids' => [$document->id],
            'action' => 'post',
        ])->assertRedirect(route('purchases.index'));

        $this->assertTrue($document->fresh()->isPosted());
    }

    public function test_sales_index_includes_bulk_toolbar(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('sales.index'))
            ->assertOk()
            ->assertSee(route('sales.bulk'), false)
            ->assertSee(__('Apply to selected'), false);
    }
}
