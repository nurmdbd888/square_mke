<?php

namespace Tests\Golden;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Money\Exact;
use App\Domain\Profit\Summary;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Brief golden scenario (core rule 7):
 * buy 100 at 23.83 with 15% recoverable VAT; sell 40 + 5 bonus at 30.
 */
class GoldenTest extends TestCase
{
    use RefreshDatabase;

    public function test_golden_profit_figures(): void
    {
        $owner = $this->owner();
        $product = Product::query()->where('code', 'P01')->firstOrFail();
        $supplier = Supplier::query()->create(['code' => 'SQ', 'name' => 'Square Toiletries']);
        $retailer = Retailer::query()->create(['code' => 'R1', 'name' => 'Corner Shop']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'GOLD-BUY-1',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '100',
            'bonus_qty' => '0',
            'unit_price' => '23.83',
            'line_value' => '2383.00',
            'line_discount' => '0',
            'sd_pct' => '0',
            'vat_pct' => '15',
            'sd_amount' => '0',
            'vat_amount' => '357.45',
        ]], $owner);

        $ledger = StockLedgerEntry::query()->where('product_id', $product->id)->sole();
        $this->assertTrue(BigDecimal::of((string) $ledger->running_qty)->isEqualTo('100'));
        $this->assertTrue(BigDecimal::of((string) $ledger->running_value)->isEqualTo('2383.00'));
        $this->assertTrue(BigDecimal::of((string) $ledger->average_cost)->isEqualTo('23.83'));

        $sale = $writer->save('sale', [
            'number' => 'GOLD-SELL-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-02',
            'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '40',
            'bonus_qty' => '5',
            'unit_price' => '30',
            'line_value' => '1200.00',
            'line_discount' => '0',
            'sd_pct' => '0',
            'vat_pct' => '0',
            'sd_amount' => '0',
            'vat_amount' => '0',
        ]], $owner);

        $this->assertSame('1200.00', Exact::money($sale->revenue));
        $this->assertSame('953.20', Exact::money($sale->cogs));
        $this->assertSame('119.15', Exact::money($sale->free_goods_cost));
        $this->assertSame('60.00', Exact::money($sale->commission_gross));
        $this->assertSame('7.44', Exact::money($sale->commission_vat));
        $this->assertSame('52.56', Exact::money($sale->commission_net));

        $summary = app(Summary::class)->between('2026-10-01', '2026-10-31');
        $this->assertSame('1200.00', $summary['revenue']);
        $this->assertSame('953.20', $summary['cogs']);
        $this->assertSame('119.15', $summary['free_goods']);
        $this->assertSame('127.65', $summary['gross']);
        $this->assertSame('60.00', $summary['commission_gross']);
        $this->assertSame('7.44', $summary['commission_vat']);
        $this->assertSame('52.56', $summary['commission_net']);
        $this->assertSame('180.21', $summary['profit_before_expenses']);
    }
}
