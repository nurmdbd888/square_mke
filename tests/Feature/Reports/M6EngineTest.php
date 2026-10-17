<?php

namespace Tests\Feature\Reports;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Payments\PaymentWriter;
use App\Domain\Profit\Summary;
use App\Domain\Reports\MonthLockService;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class M6EngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_golden_net_profit_with_operating_expense(): void
    {
        $owner = $this->owner();
        $product = Product::query()->where('code', 'P01')->firstOrFail();
        $supplier = Supplier::query()->create(['code' => 'SQ', 'name' => 'Square Toiletries']);
        $retailer = Retailer::query()->create(['code' => 'R1', 'name' => 'Corner Shop']);
        $writer = app(DocumentWriter::class);
        $payments = app(PaymentWriter::class);

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

        $writer->save('sale', [
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

        $fuel = ExpenseCategory::query()->where('key', 'fuel')->value('id');
        $payments->save([
            'kind' => 'payment',
            'number' => 'EXP-1',
            'method' => 'petty',
            'amount' => '50.00',
            'payment_date' => '2026-10-05',
            'expense_category_id' => $fuel,
            'action' => 'post',
        ], [], $owner);

        $summary = app(Summary::class)->between('2026-10-01', '2026-10-31');
        $this->assertSame('180.21', $summary['profit_before_expenses']);
        $this->assertSame('50.00', $summary['expenses']);
        $this->assertSame('0.00', $summary['stock_loss']);
        $this->assertSame('130.21', $summary['net_profit']);
    }

    public function test_locked_month_blocks_posting_for_every_role(): void
    {
        $owner = $this->owner();
        app(MonthLockService::class)->lock(2026, 10, $owner);

        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'buy_price' => '10', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $accountant = $this->userWithRole('accountant');

        $this->expectException(ValidationException::class);
        app(DocumentWriter::class)->save('sale', [
            'number' => 'LOCK-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-15',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '20', 'line_value' => '20']], $accountant);
    }

    public function test_owner_unlock_is_audited(): void
    {
        $owner = $this->owner();
        $locks = app(MonthLockService::class);
        $locks->lock(2026, 9, $owner);
        $locks->unlock(2026, 9, $owner);

        $this->assertDatabaseHas('audit_logs', ['event' => 'month_lock.unlocked']);
        $log = AuditLog::query()->where('event', 'month_lock.unlocked')->sole();
        $this->assertSame('month_lock', $log->auditable_type);
    }

    public function test_pl_revenue_matches_posted_sales_ledger(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Item', 'unit' => 'Pcs', 'buy_price' => '5', 'sell_price' => '10']);
        $retailer = Retailer::query()->create(['name' => 'R']);
        $supplier = Supplier::query()->create(['code' => 'S', 'name' => 'Sup']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'P-1', 'supplier_id' => $supplier->id, 'doc_date' => '2026-11-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '50', 'unit_price' => '5', 'line_value' => '250']], $owner);

        $writer->save('sale', [
            'number' => 'S-1', 'retailer_id' => $retailer->id, 'doc_date' => '2026-11-02', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '10', 'line_value' => '100']], $owner);

        $from = '2026-11-01';
        $to = '2026-11-30';
        $figures = app(Summary::class)->between($from, $to);
        $ledger = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED)
            ->whereBetween('doc_date', [$from, $to])
            ->get()
            ->sum(fn ($d) => (float) $d->revenue);

        $this->assertSame($figures['revenue'], number_format($ledger, 2, '.', ''));
    }

    public function test_dashboard_shows_metrics_for_report_viewers(): void
    {
        $this->actingAs($this->userWithRole('accountant'))
            ->get('/')
            ->assertOk()
            ->assertSee('data-testid="dashboard-metrics"', false);
    }

    public function test_pl_report_page(): void
    {
        $this->actingAs($this->owner())
            ->get('/reports/pl')
            ->assertOk()
            ->assertSee('data-testid="pl-summary"', false);
    }
}
