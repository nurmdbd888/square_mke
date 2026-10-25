<?php

namespace Tests\Feature;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Payments\SaleCollectionStatus;
use App\Models\Document;
use App\Models\Payment;
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

    public function test_collections_page_lists_only_due_invoices_and_opens_compact_collection_form(): void
    {
        $invoice = $this->postedSale(); // doc_date 2026-10-01
        $this->travelTo(now()->parse('2026-11-05')); // > 30 days past invoice date

        $collector = $this->userWithRole('collector');

        $html = $this->actingAs($collector)
            ->get(route('collector.index'))
            ->assertOk()
            ->assertSee('data-testid="collector-filters"', false)
            ->assertSee(__('Overdue'), false)
            ->assertSee(route('collector.show', $invoice), false)
            ->assertDontSee(__('Ready to confirm'), false)
            ->assertDontSee(__('View receipts'), false)
            ->getContent();

        $this->assertStringContainsString('collector-overdue-badge', $html);

        $this->actingAs($collector)
            ->get(route('collector.index', ['overdue' => 1, 'status' => 'due']))
            ->assertOk()
            ->assertSee($invoice->number, false);

        $this->actingAs($collector)
            ->get(route('collector.show', $invoice))
            ->assertOk()
            ->assertSee('data-testid="collection-invoice-details"', false)
            ->assertSee('data-testid="collection-payment-form"', false)
            ->assertSee('name="amount"', false)
            ->assertSee('data-testid="collection-balance-due"', false)
            ->assertSee('data-testid="collection-balance-after"', false)
            ->assertDontSee('name="allocations[', false)
            ->assertDontSee('name="method"', false);

        $this->actingAs($collector)
            ->post(route('collector.store', $invoice), ['amount' => '275'])
            ->assertRedirect(route('payments.show', Payment::query()->first()))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Receipt '));

        $receipt = Payment::query()->sole();
        $this->assertSame(Payment::KIND_RECEIPT, $receipt->kind);
        $this->assertTrue($receipt->isPosted());
        $this->assertSame('275.00', $receipt->amount);
        $this->actingAs($collector)
            ->get(route('payments.show', $receipt))
            ->assertOk()
            ->assertSee($receipt->number, false)
            ->assertSee('href="'.route('collector.index').'"', false);
        $this->assertSame('725.00', (string) app(SaleCollectionStatus::class)->remainingAmount($invoice->fresh()));
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
