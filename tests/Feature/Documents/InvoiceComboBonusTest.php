<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\DocumentWriter;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceComboBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_combo_bonus_line_does_not_increase_payable_totals(): void
    {
        $owner = $this->owner();
        $paid = Product::query()->create(['code' => 'PAID', 'name_en' => 'Paid soap', 'unit' => 'Pcs', 'sell_price' => '100', 'buy_price' => '50']);
        $bonus = Product::query()->create(['code' => 'BONUS', 'name_en' => 'Bonus soap', 'name_bn' => 'বোনাস সাবান', 'pack_size' => '100gm', 'unit' => 'Pcs', 'sell_price' => '57.60', 'buy_price' => '40']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop', 'code' => 'R1']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'P-CB-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [
            ['product_id' => $paid->id, 'qty' => '20', 'unit_price' => '50', 'line_value' => '1000'],
            ['product_id' => $bonus->id, 'qty' => '20', 'unit_price' => '40', 'line_value' => '800'],
        ], $owner);

        $sale = $writer->save('sale', [
            'number' => 'S-CB-1', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-10', 'action' => 'post',
        ], [
            [
                'product_id' => $paid->id,
                'kind' => DocumentLine::KIND_NORMAL,
                'qty' => '2',
                'unit_price' => '100',
                'line_value' => '200',
            ],
            [
                'product_id' => $bonus->id,
                'kind' => DocumentLine::KIND_COMBO_BONUS,
                'qty' => '11',
                'unit_price' => '57.60',
                'line_value' => '999.99', // client may send a value; server must zero it
            ],
        ], $owner);

        $sale->refresh()->load('lines');

        $this->assertSame('200.00', (string) $sale->subtotal);
        $this->assertSame('200.00', (string) $sale->net_total);
        $this->assertSame('200.00', (string) $sale->revenue);

        $bonusLine = $sale->lines->firstWhere('kind', DocumentLine::KIND_COMBO_BONUS);
        $this->assertNotNull($bonusLine);
        $this->assertSame('0.00', (string) $bonusLine->line_value);
        $this->assertSame('57.6000', (string) $bonusLine->unit_price);
        $this->assertSame('11.000', (string) $bonusLine->qty);
    }

    public function test_combo_bonus_qty_reduces_stock_as_free_goods(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['code' => 'CB2', 'name_en' => 'Item', 'unit' => 'Pcs', 'buy_price' => '10', 'sell_price' => '20']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'P-CB-2', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '30', 'unit_price' => '10', 'line_value' => '300']], $owner);

        $sale = $writer->save('sale', [
            'number' => 'S-CB-2', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-05', 'action' => 'post',
        ], [
            ['product_id' => $product->id, 'kind' => 'normal', 'qty' => '5', 'unit_price' => '20', 'line_value' => '100'],
            ['product_id' => $product->id, 'kind' => 'combo_bonus', 'qty' => '3', 'unit_price' => '20', 'line_value' => '0'],
        ], $owner);

        $last = StockLedgerEntry::query()->where('product_id', $product->id)->orderByDesc('id')->first();
        $this->assertSame('22.000', (string) $last->running_qty);

        $sale->refresh();
        $this->assertSame('100.00', (string) $sale->subtotal);
        $this->assertGreaterThan(0, (float) $sale->free_goods_cost);
        $this->assertSame('50.00', (string) $sale->cogs);
    }

    public function test_print_invoice_shows_invoice_combo_bonus_section(): void
    {
        $owner = $this->owner();
        $paid = Product::query()->create(['code' => 'P3', 'name_en' => 'Paid', 'unit' => 'Pcs', 'sell_price' => '10', 'buy_price' => '5']);
        $bonus = Product::query()->create([
            'code' => 'B3', 'name_en' => 'Meril Milk Soap', 'name_bn' => 'মেরিল মিল্ক সোপ',
            'pack_size' => '100gm', 'unit' => 'Pcs', 'sell_price' => '57.60', 'buy_price' => '40',
        ]);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'P-CB-3', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [
            ['product_id' => $paid->id, 'qty' => '10', 'unit_price' => '5', 'line_value' => '50'],
            ['product_id' => $bonus->id, 'qty' => '10', 'unit_price' => '40', 'line_value' => '400'],
        ], $owner);

        $sale = $writer->save('sale', [
            'number' => 'S-CB-3', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-10', 'action' => 'post',
        ], [
            ['product_id' => $paid->id, 'kind' => 'normal', 'qty' => '1', 'unit_price' => '10', 'line_value' => '10'],
            ['product_id' => $bonus->id, 'kind' => 'combo_bonus', 'qty' => '11', 'unit_price' => '57.60', 'line_value' => '0'],
        ], $owner);

        $this->actingAs($owner)
            ->get(route('sales.print.invoice', [$sale, 'size' => 'a5']))
            ->assertOk()
            ->assertSee(__('Invoice Combo Bonus'), false)
            ->assertSee('100gm', false)
            ->assertSee('57.60', false)
            ->assertSee(__('Sub Total'), false);

        $this->actingAs($owner)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('data-testid="invoice-combo-bonus-heading"', false)
            ->assertSee('data-testid="combo-bonus-lines"', false)
            ->assertSee(__('Pack').': 100gm', false);

        $this->actingAs($owner)
            ->get(route('sales.print.thermal', $sale))
            ->assertOk()
            ->assertSee(__('Pack').': 100gm', false);

        $this->actingAs($owner)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-testid="line-add-kind"', false)
            ->assertSee(__('Invoice Combo Bonus'), false);
    }

    public function test_sale_form_posts_combo_bonus_kind(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['code' => 'P4', 'name_en' => 'Item', 'unit' => 'Pcs', 'sell_price' => '25']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);

        $this->actingAs($owner)
            ->post(route('sales.store'), [
                'number' => 'S-CB-POST-1',
                'retailer_id' => $retailer->id,
                'doc_date' => '2026-10-18',
                'action' => 'save',
                'header_discount' => '0',
                'slab_adjustment' => '0',
                'combo_bonus_discount' => '0',
                'rounding_adjustment' => '0',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'kind' => 'normal',
                        'qty' => '2',
                        'unit_price' => '25',
                        'line_discount' => '0',
                        'line_value' => '50',
                        'bonus_qty' => '0',
                        'sd_pct' => '0',
                        'vat_pct' => '0',
                        'sd_amount' => '0',
                        'vat_amount' => '0',
                    ],
                    [
                        'product_id' => $product->id,
                        'kind' => 'combo_bonus',
                        'qty' => '4',
                        'unit_price' => '25',
                        'line_discount' => '0',
                        'line_value' => '100',
                        'bonus_qty' => '0',
                        'sd_pct' => '0',
                        'vat_pct' => '0',
                        'sd_amount' => '0',
                        'vat_amount' => '0',
                    ],
                ],
            ])
            ->assertRedirect();

        $doc = Document::query()->where('number', 'S-CB-POST-1')->firstOrFail();
        $this->assertSame('50.00', (string) $doc->subtotal);
        $this->assertSame('50.00', (string) $doc->net_total);
        $this->assertCount(1, $doc->lines()->where('kind', DocumentLine::KIND_COMBO_BONUS)->get());
        $this->assertSame('0.00', (string) $doc->lines()->where('kind', DocumentLine::KIND_COMBO_BONUS)->value('line_value'));
    }
}
