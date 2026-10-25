<?php

namespace Tests\Feature\Documents;

use App\Domain\Costing\Recompute;
use App\Domain\Documents\DocumentWriter;
use App\Domain\Money\Exact;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CostingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_challan_line_average_and_sd_in_cost(): void
    {
        $owner = $this->owner();
        $soap = Product::query()->where('code', 'P01')->firstOrFail();
        $lipgel = Product::query()->where('code', 'P05')->firstOrFail();
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'INV-ADU/4344/1026',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-10',
            'action' => 'post',
        ], [
            [
                'product_id' => $soap->id,
                'qty' => '1440',
                'unit_price' => '23.83',
                'line_value' => '34309.44',
                'vat_amount' => '5146.42',
                'sd_amount' => '0',
            ],
            [
                'product_id' => $lipgel->id,
                'qty' => '10',
                'unit_price' => '32.67',
                'line_value' => '326.70',
                'sd_amount' => '16.34',
                'vat_amount' => '51.46',
            ],
        ], $owner);

        $soapLedger = StockLedgerEntry::query()->where('product_id', $soap->id)->sole();
        $this->assertTrue(BigDecimal::of((string) $soapLedger->average_cost)->isEqualTo('23.8260'));
        $this->assertTrue(BigDecimal::of((string) $soapLedger->running_value)->isEqualTo('34309.44'));

        $lipgelLedger = StockLedgerEntry::query()->where('product_id', $lipgel->id)->sole();
        // value 326.70 + SD 16.34 = 343.04 when sd_in_cost is on and VAT recoverable
        $this->assertTrue(BigDecimal::of((string) $lipgelLedger->running_value)->isEqualTo('343.04'));
    }

    public function test_same_date_purchase_costs_before_sale(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['code' => 'X', 'name_en' => 'Item', 'unit' => 'Pcs', 'buy_price' => '10']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $writer = app(DocumentWriter::class);

        // Sale entered first, same date as purchase — purchase must still cost first.
        $writer->save('sale', [
            'number' => 'S-1', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-17', 'action' => 'post',
        ], [[
            'product_id' => $product->id, 'qty' => '5', 'unit_price' => '20', 'line_value' => '100',
        ]], $owner);

        $writer->save('purchase', [
            'number' => 'P-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-17', 'action' => 'post',
        ], [[
            'product_id' => $product->id, 'qty' => '10', 'unit_price' => '10', 'line_value' => '100',
        ]], $owner);

        app(Recompute::class)->one($product->id, null);

        $sale = Document::query()->where('number', 'S-1')->first();
        $this->assertSame('50.00', Exact::money($sale->cogs));
        $last = StockLedgerEntry::query()->where('product_id', $product->id)->orderByDesc('id')->first();
        $this->assertTrue(BigDecimal::of((string) $last->running_qty)->isEqualTo('5'));
    }

    public function test_duplicate_number_party_total_is_rejected(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Item', 'unit' => 'Pcs']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'DUP-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '10', 'line_value' => '10']], $owner);

        $this->expectException(ValidationException::class);
        $writer->save('purchase', [
            'number' => 'DUP-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-02', 'action' => 'save',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '10', 'line_value' => '10']], $owner);
    }

    public function test_sale_invoice_number_cannot_be_reused_for_another_retailer_or_total(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Item', 'unit' => 'Pcs']);
        $firstRetailer = Retailer::query()->create(['name' => 'First Shop']);
        $secondRetailer = Retailer::query()->create(['name' => 'Second Shop']);
        $writer = app(DocumentWriter::class);

        $writer->save('sale', [
            'number' => 'SALE-UNIQUE-1', 'retailer_id' => $firstRetailer->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '10', 'line_value' => '10']], $owner);

        try {
            $writer->save('sale', [
                'number' => 'SALE-UNIQUE-1', 'retailer_id' => $secondRetailer->id, 'doc_date' => '2026-10-02', 'action' => 'save',
            ], [['product_id' => $product->id, 'qty' => '2', 'unit_price' => '10', 'line_value' => '20']], $owner);
            $this->fail('A duplicate sale invoice number should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('number', $exception->errors());
            $this->assertSame(__('A non-void document with this number already exists.'), $exception->errors()['number'][0]);
        }
    }

    public function test_editing_posted_document_writes_audit_and_recomputes(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Item', 'unit' => 'Pcs', 'buy_price' => '10']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $writer = app(DocumentWriter::class);

        $doc = $writer->save('purchase', [
            'number' => 'ED-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '10', 'line_value' => '100']], $owner);

        $writer->save('purchase', [
            'number' => 'ED-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '20', 'unit_price' => '10', 'line_value' => '200']], $owner, $doc);

        $this->assertTrue(
            AuditLog::query()->where('event', 'document.posted_edited')->where('auditable_type', 'document')->exists()
        );
        $ledger = StockLedgerEntry::query()->where('product_id', $product->id)->sole();
        $this->assertTrue(BigDecimal::of((string) $ledger->running_qty)->isEqualTo('20'));
    }

    public function test_incremental_recompute_matches_full_rebuild(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Item', 'unit' => 'Pcs', 'buy_price' => '10']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $writer = app(DocumentWriter::class);
        $recompute = app(Recompute::class);

        for ($i = 0; $i < 20; $i++) {
            $day = sprintf('2026-09-%02d', ($i % 28) + 1);
            if ($i % 3 === 0) {
                $writer->save('purchase', [
                    'number' => 'P-'.$i, 'supplier_id' => $supplier->id, 'doc_date' => $day, 'action' => 'post',
                ], [['product_id' => $product->id, 'qty' => (string) (5 + ($i % 7)), 'unit_price' => '10', 'line_value' => Exact::money((5 + ($i % 7)) * 10)]], $owner);
            } else {
                $writer->save('sale', [
                    'number' => 'S-'.$i, 'retailer_id' => $retailer->id, 'doc_date' => $day, 'action' => 'post',
                ], [['product_id' => $product->id, 'qty' => '2', 'bonus_qty' => $i % 5 === 0 ? '1' : '0', 'unit_price' => '15', 'line_value' => '30']], $owner);
            }

            if ($i % 7 === 0 && $i > 0) {
                $victim = Document::query()->where('status', Document::STATUS_POSTED)->inRandomOrder()->first();
                if ($victim !== null) {
                    $writer->void($victim, $owner);
                }
            }
        }

        $incremental = StockLedgerEntry::query()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get(['doc_date', 'movement', 'qty_in', 'qty_out', 'value_in', 'value_out', 'running_qty', 'running_value', 'average_cost'])
            ->map(fn ($r) => $r->toArray())
            ->all();

        $recompute->all();

        $full = StockLedgerEntry::query()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get(['doc_date', 'movement', 'qty_in', 'qty_out', 'value_in', 'value_out', 'running_qty', 'running_value', 'average_cost'])
            ->map(fn ($r) => $r->toArray())
            ->all();

        $this->assertSame($full, $incremental);
    }

    public function test_http_purchase_and_sale_pages(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'buy_price' => '23.83']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);

        $this->actingAs($owner)->post('/purchases', [
            'number' => 'HTTP-P1',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
            'lines' => [[
                'product_id' => $product->id,
                'qty' => '100',
                'unit_price' => '23.83',
                'line_value' => '2383.00',
                'vat_pct' => '15',
                'vat_amount' => '357.45',
            ]],
        ])->assertRedirect();

        $this->actingAs($owner)->post('/sales', [
            'number' => 'HTTP-S1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-02',
            'action' => 'post',
            'lines' => [[
                'product_id' => $product->id,
                'qty' => '40',
                'bonus_qty' => '5',
                'unit_price' => '30',
                'line_value' => '1200.00',
            ]],
        ])->assertRedirect();

        $this->actingAs($owner)->get('/sales')->assertOk()->assertSee('HTTP-S1');
        $this->actingAs($owner)->get('/stock')->assertOk()->assertSee('Soap');
        $this->actingAs($owner)->get('/stock/'.$product->id)->assertOk()->assertSee('23.83');
    }
}
