<?php

namespace Tests\Feature;

use App\Domain\Documents\DocumentWriter;
use App\Models\Document;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UxPaymentsCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_sale_starts_as_due(): void
    {
        $invoice = $this->postedSale();

        $this->assertSame(Document::COLLECTION_DUE, $invoice->fresh()->collection_status);
    }

    public function test_partial_receipt_updates_collection_status(): void
    {
        $owner = $this->owner();
        $invoice = $this->postedSale();
        $collector = $this->userWithRole('collector');

        $this->actingAs($collector)->post(route('payments.store'), [
            'kind' => 'receipt',
            'number' => 'RC-PART-1',
            'retailer_id' => $invoice->retailer_id,
            'method' => 'cash',
            'amount' => '100',
            'payment_date' => '2026-10-17',
            'action' => 'post',
            'allocations' => [
                ['document_id' => $invoice->id, 'amount' => '100'],
            ],
        ])->assertRedirect();

        $this->assertSame(Document::COLLECTION_PARTIAL, $invoice->fresh()->collection_status);
    }

    public function test_collector_can_confirm_fully_paid_invoice(): void
    {
        $owner = $this->owner();
        $invoice = $this->postedSale();
        $collector = $this->userWithRole('collector');

        $this->actingAs($owner)->post(route('payments.store'), [
            'kind' => 'receipt',
            'number' => 'RC-FULL-1',
            'retailer_id' => $invoice->retailer_id,
            'method' => 'cash',
            'amount' => '1000',
            'payment_date' => '2026-10-17',
            'action' => 'post',
            'allocations' => [
                ['document_id' => $invoice->id, 'amount' => '1000'],
            ],
        ])->assertRedirect();

        $this->actingAs($collector)
            ->post(route('sales.collection.confirm', $invoice))
            ->assertRedirect();

        $this->assertSame(Document::COLLECTION_COMPLETED, $invoice->fresh()->collection_status);
    }

    public function test_open_invoices_htmx_with_retailer_id(): void
    {
        $invoice = $this->postedSale();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('payments.open-invoices', ['party' => 'retailer', 'retailer_id' => $invoice->retailer_id]))
            ->assertOk()
            ->assertSee($invoice->number, false);
    }

    private function postedSale(): Document
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '100']);
        $retailer = Retailer::query()->create(['name' => 'Corner Shop', 'credit_limit' => '50000']);

        return app(DocumentWriter::class)->save('sale', [
            'number' => 'S-UX-'.uniqid(),
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '100', 'line_value' => '1000']], $owner);
    }
}
