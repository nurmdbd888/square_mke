<?php

namespace Tests\Feature\M9;

use App\Models\Document;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\SalesSyncKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDraftSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_idempotent(): void
    {
        $sr = $this->userWithRole('sr');
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Corner Shop']);

        $payload = [
            'number' => 'S-OFF-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'action' => 'save',
            'lines' => [
                ['product_id' => $product->id, 'qty' => '3', 'unit_price' => '10', 'line_value' => '30'],
            ],
        ];

        $first = $this->actingAs($sr)
            ->withHeader('Idempotency-Key', 'offline-key-1')
            ->postJson(route('sales.sync-draft'), $payload);

        $first->assertCreated();
        $documentId = $first->json('document_id');
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(1, SalesSyncKey::query()->count());

        $second = $this->actingAs($sr)
            ->withHeader('Idempotency-Key', 'offline-key-1')
            ->postJson(route('sales.sync-draft'), $payload);

        $second->assertOk();
        $second->assertJson(['duplicate' => true, 'document_id' => $documentId]);
        $this->assertSame(1, Document::query()->count());
    }
}
