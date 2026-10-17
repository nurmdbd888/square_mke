<?php

namespace Tests\Feature\Stock;

use App\Domain\Costing\Recompute;
use App\Domain\Documents\DocumentWriter;
use App\Domain\Returns\StockNoteWriter;
use App\Domain\Stock\StockAlerts;
use App\Domain\Stock\StockComparisonImporter;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Retailer;
use App\Models\StockComparisonLine;
use App\Models\StockLedgerEntry;
use App\Models\StockNote;
use App\Models\StockNoteLine;
use App\Models\Supplier;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReturnsAndBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_return_restores_stock_at_original_issue_cost(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['code' => 'R1', 'name_en' => 'Return item', 'unit' => 'Pcs', 'buy_price' => '10']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $docWriter = app(DocumentWriter::class);
        $returnWriter = app(StockNoteWriter::class);

        $docWriter->save('purchase', [
            'number' => 'P-RET-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '10', 'line_value' => '100']], $owner);

        $sale = $docWriter->save('sale', [
            'number' => 'S-RET-1', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-05', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '20', 'line_value' => '100']], $owner);

        $saleLine = $sale->lines->first();
        $this->assertSame('50.00', (string) $saleLine->fresh()->issue_cost);

        $returnWriter->save(StockNote::TYPE_SALES_RETURN, [
            'number' => 'SR-1', 'doc_date' => '2026-10-06', 'retailer_id' => $retailer->id, 'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '5',
            'line_condition' => StockNoteLine::CONDITION_GOOD,
            'linked_document_line_id' => $saleLine->id,
        ]], $owner);

        app(Recompute::class)->one($product->id, null);

        $last = StockLedgerEntry::query()->where('product_id', $product->id)->orderByDesc('id')->first();
        $this->assertSame('10.000', (string) $last->running_qty);

        $returnLine = StockNoteLine::query()->first();
        $this->assertSame('50.00', (string) $returnLine->restore_cost);
    }

    public function test_damaged_return_posts_stock_loss_without_restock(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['code' => 'R2', 'name_en' => 'Damaged', 'unit' => 'Pcs', 'buy_price' => '10']);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $docWriter = app(DocumentWriter::class);
        $returnWriter = app(StockNoteWriter::class);

        $docWriter->save('purchase', [
            'number' => 'P-RET-2', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '10', 'line_value' => '100']], $owner);

        $sale = $docWriter->save('sale', [
            'number' => 'S-RET-2', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-05', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '20', 'line_value' => '100']], $owner);

        $saleLine = $sale->lines->first();

        $returnWriter->save(StockNote::TYPE_SALES_RETURN, [
            'number' => 'SR-2', 'doc_date' => '2026-10-06', 'retailer_id' => $retailer->id, 'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '5',
            'line_condition' => StockNoteLine::CONDITION_DAMAGED,
            'linked_document_line_id' => $saleLine->id,
        ]], $owner);

        app(Recompute::class)->one($product->id, null);

        $last = StockLedgerEntry::query()->where('product_id', $product->id)->orderByDesc('id')->first();
        $this->assertSame('5.000', (string) $last->running_qty);

        $lossLine = StockNoteLine::query()->first();
        $this->assertSame('50.00', (string) $lossLine->loss_cost);
    }

    public function test_fefo_issues_earliest_expiry_first(): void
    {
        $owner = $this->owner();
        $settings = app(SettingsStore::class);
        $settings->set(['costing_mode' => 'batch_fefo']);

        $product = Product::query()->create([
            'code' => 'FEFO', 'name_en' => 'Batch item', 'unit' => 'Pcs', 'buy_price' => '10', 'tracks_expiry' => true,
        ]);
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $docWriter = app(DocumentWriter::class);

        $docWriter->save('purchase', [
            'number' => 'P-LATE', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [[
            'product_id' => $product->id, 'qty' => '10', 'unit_price' => '20', 'line_value' => '200',
            'batch_no' => 'LOT-LATE', 'expiry_date' => '2026-12-01',
        ]], $owner);

        $docWriter->save('purchase', [
            'number' => 'P-EARLY', 'supplier_id' => $supplier->id, 'doc_date' => '2026-10-02', 'action' => 'post',
        ], [[
            'product_id' => $product->id, 'qty' => '10', 'unit_price' => '10', 'line_value' => '100',
            'batch_no' => 'LOT-EARLY', 'expiry_date' => '2026-11-01',
        ]], $owner);

        $earlyBatch = ProductBatch::query()->where('batch_no', 'LOT-EARLY')->firstOrFail();

        $docWriter->save('sale', [
            'number' => 'S-FEFO', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-03', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '30', 'line_value' => '150']], $owner);

        app(Recompute::class)->one($product->id, null);

        $issue = StockLedgerEntry::query()
            ->where('product_id', $product->id)
            ->where('movement', 'sale')
            ->where('qty_out', '>', 0)
            ->first();

        $this->assertSame($earlyBatch->id, $issue->product_batch_id);
        $this->assertSame('50.00', (string) $issue->value_out);
    }

    public function test_near_expiry_alert_lists_batches_within_setting(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set(['near_expiry_days' => 60]);

        $product = Product::query()->create(['code' => 'EXP', 'name_en' => 'Expiry', 'unit' => 'Pcs', 'tracks_expiry' => true]);
        $batch = ProductBatch::query()->create([
            'product_id' => $product->id,
            'batch_no' => 'B-30',
            'expiry_date' => now()->addDays(30)->toDateString(),
        ]);

        StockLedgerEntry::query()->create([
            'product_id' => $product->id,
            'product_batch_id' => $batch->id,
            'doc_date' => now()->toDateString(),
            'movement' => 'purchase',
            'rank' => 1,
            'qty_in' => '5',
            'qty_out' => '0',
            'value_in' => '50',
            'value_out' => '0',
            'running_qty' => '5',
            'running_value' => '50',
            'average_cost' => '10',
            'posted_at' => now(),
        ]);

        $alerts = app(StockAlerts::class)->nearExpiry();
        $this->assertTrue($alerts->contains(fn ($b) => $b->id === $batch->id));
    }

    public function test_comparison_flags_qty_difference_and_unknown_product(): void
    {
        $owner = $this->owner();
        $known = Product::query()->create(['code' => 'CMP1', 'name_en' => 'Known', 'unit' => 'Pcs']);
        StockLedgerEntry::query()->create([
            'product_id' => $known->id,
            'doc_date' => now()->toDateString(),
            'movement' => 'purchase',
            'rank' => 1,
            'qty_in' => '100',
            'qty_out' => '0',
            'value_in' => '1000',
            'value_out' => '0',
            'running_qty' => '100',
            'running_value' => '1000',
            'average_cost' => '10',
            'posted_at' => now(),
        ]);

        $csv = "code,name,qty\nCMP1,Known,90\nGHOST,Unknown SKU,5\n";
        $file = UploadedFile::fake()->createWithContent('export.csv', $csv);

        $result = app(StockComparisonImporter::class)->import($file, $owner);

        $lines = $result['comparison']->lines;
        $this->assertTrue($lines->contains(fn ($l) => $l->status === StockComparisonLine::STATUS_QTY_DIFF && $l->product_code === 'CMP1'));
        $this->assertTrue($lines->contains(fn ($l) => $l->status === StockComparisonLine::STATUS_UNKNOWN_PRODUCT && $l->product_code === 'GHOST'));
    }
}
