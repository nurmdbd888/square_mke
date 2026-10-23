<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\DocumentWriter;
use App\Models\Document;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Square Toiletries Invoice Adjustment math (sample RUP-261000510):
 * Net Invoice = subtotal − Loading − Combo Discount
 * Total Payable = Net Invoice + Total TDS Amount
 */
class InvoiceAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_payable_follows_square_invoice_adjustment_math(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::query()->create(['name' => 'Square Toiletries']);
        $product = Product::query()->create([
            'code' => 'P-ADJ', 'name_en' => 'Soap', 'unit' => 'Pcs', 'buy_price' => '10', 'sell_price' => '12',
        ]);

        // Sample-shaped numbers: 496845.64 − 134 + 2477.71 = 499189.35
        $doc = app(DocumentWriter::class)->save('purchase', [
            'number' => 'RUP-261000510',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-10',
            'action' => 'post',
            'header_discount' => '0',
            'slab_adjustment' => '134.00',
            'combo_bonus_discount' => '0.00',
            'tds_amount' => '2477.71',
            'rounding_adjustment' => '0',
        ], [
            ['product_id' => $product->id, 'qty' => '1', 'unit_price' => '496845.6400', 'line_value' => '496845.64'],
        ], $owner);

        $this->assertSame('496845.64', (string) $doc->subtotal);
        $this->assertSame('134.00', (string) $doc->slab_adjustment);
        $this->assertSame('0.00', (string) $doc->combo_bonus_discount);
        $this->assertSame('2477.71', (string) $doc->tds_amount);
        $this->assertSame('499189.35', (string) $doc->net_total);
    }

    public function test_purchase_form_shows_square_invoice_adjustment_fields(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('purchases.create'))
            ->assertOk()
            ->assertSee(__('Invoice Adjustment'), false)
            ->assertSee(__('Total TDS Amount'), false)
            ->assertSee(__('Loading Adjustment'), false)
            ->assertSee(__('Combo Discount'), false)
            ->assertSee('data-testid="invoice-adjustment"', false)
            ->assertSee('data-testid="tds-amount"', false)
            ->assertDontSee('>'.__('Slab adjustment').'<', false)
            ->assertDontSee('>'.__('Combo bonus discount').'<', false);
    }

    public function test_sale_form_shares_invoice_adjustment_block(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-testid="invoice-adjustment"', false)
            ->assertSee(__('Total TDS Amount'), false);
    }

    public function test_sale_detail_separates_document_details_and_highlights_total_payable(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Corner Shop']);
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs']);
        $document = app(DocumentWriter::class)->save(Document::TYPE_SALE, [
            'number' => 'S-SUMMARY-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-10',
            'action' => 'save',
        ], [
            ['product_id' => $product->id, 'qty' => '2', 'unit_price' => '10', 'line_value' => '20'],
        ], $owner);

        $this->actingAs($owner)->get(route('sales.show', $document))
            ->assertOk()
            ->assertSee('data-testid="document-summary"', false)
            ->assertSee('data-testid="document-date"', false)
            ->assertSee('data-testid="document-party"', false)
            ->assertSee(__('Date'), false)
            ->assertSee(__('Retailer'), false)
            ->assertSee('data-testid="invoice-total-card"', false)
            ->assertSee(__('Total payable'), false)
            ->assertSee(format_money(20), false);
    }
}
