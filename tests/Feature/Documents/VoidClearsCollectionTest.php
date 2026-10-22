<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\DocumentWriter;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoidClearsCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_voiding_posted_sale_clears_collection_queue_fields(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $product = Product::query()->create([
            'name_en' => 'Soap',
            'code' => 'S1',
            'buy_price' => '10',
            'sell_price' => '12',
            'sd_pct' => '0',
            'vat_pct' => '15',
        ]);
        $document = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-VOID-COLL',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_DRAFT,
            'subtotal' => '12',
            'net_total' => '12',
        ]);
        DocumentLine::query()->create([
            'document_id' => $document->id,
            'product_id' => $product->id,
            'kind' => 'sale',
            'qty' => '1',
            'bonus_qty' => '0',
            'unit_price' => '12',
            'line_discount' => '0',
            'line_value' => '12',
            'sd_pct' => '0',
            'vat_pct' => '0',
            'sd_amount' => '0',
            'vat_amount' => '0',
        ]);

        $writer = app(DocumentWriter::class);
        $document->load('lines');
        $writer->post($document, $owner);
        $document->refresh();
        $this->assertSame(Document::COLLECTION_DUE, $document->collection_status);

        $writer->void($document->fresh(['lines']), $owner);
        $document->refresh();

        $this->assertSame(Document::STATUS_VOID, $document->status);
        $this->assertNull($document->collection_status);
        $this->assertNull($document->collection_confirmed_at);
    }
}
