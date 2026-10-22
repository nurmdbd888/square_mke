<?php

namespace Tests\Feature\Reports;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Payments\PaymentWriter;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P5ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_index_and_new_report_pages_load(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('data-testid="reports-index"', false)
            ->assertSee('data-testid="reports-party-ledger"', false)
            ->assertSee('data-testid="reports-brand-sales"', false)
            ->assertSee('data-testid="reports-invoice-adjustments"', false);

        $this->actingAs($owner)->get(route('reports.brand-sales'))
            ->assertOk()
            ->assertSee('data-testid="brand-sales-table"', false);

        $this->actingAs($owner)->get(route('reports.invoice-adjustments'))
            ->assertOk()
            ->assertSee('data-testid="invoice-adjustments-summary"', false);
    }

    public function test_brand_sales_and_adjustments_reflect_posted_sale(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create([
            'code' => 'BR1',
            'name_en' => 'Brand Soap',
            'brand' => 'Acme',
            'unit' => 'Pcs',
            'sell_price' => '100',
        ]);
        $retailer = Retailer::query()->create(['name' => 'Shop A', 'code' => 'RA']);

        app(DocumentWriter::class)->save('sale', [
            'number' => 'S-P5-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-10',
            'tds_amount' => '50',
            'slab_adjustment' => '25',
            'combo_bonus_discount' => '10',
            'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '5',
            'unit_price' => '100',
            'line_value' => '500',
        ]], $owner);

        $this->actingAs($owner)
            ->get(route('reports.brand-sales', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Acme', false)
            ->assertSee('500.00', false);

        $html = $this->actingAs($owner)
            ->get(route('reports.invoice-adjustments', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('50.00', $html);
        $this->assertStringContainsString('25.00', $html);
        $this->assertStringContainsString('10.00', $html);
    }

    public function test_party_ledger_shows_invoice_and_receipt_running_balance(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '100']);
        $retailer = Retailer::query()->create(['name' => 'Ledger Shop', 'code' => 'LL']);

        $invoice = app(DocumentWriter::class)->save('sale', [
            'number' => 'S-LEDGER-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-05',
            'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '10',
            'unit_price' => '100',
            'line_value' => '1000',
        ]], $owner);

        app(PaymentWriter::class)->save([
            'kind' => 'receipt',
            'number' => 'RC-LEDGER-1',
            'retailer_id' => $retailer->id,
            'method' => 'cash',
            'amount' => '400',
            'payment_date' => '2026-10-08',
            'action' => 'post',
        ], [
            ['document_id' => $invoice->id, 'amount' => '400'],
        ], $owner);

        $this->actingAs($owner)
            ->get(route('reports.party-ledger', [
                'party_id' => 'r-'.$retailer->id,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('data-testid="party-ledger-table"', false)
            ->assertSee('S-LEDGER-1', false)
            ->assertSee('RC-LEDGER-1', false)
            ->assertSee('600.00', false);
    }

    public function test_dashboard_shows_backup_stale_banner_when_no_backup(): void
    {
        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-testid="backup-stale-banner"', false);
    }

    public function test_audit_index_offers_document_and_payment_filters(): void
    {
        $this->actingAs($this->owner())
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('document.*', false)
            ->assertSee('payment.*', false)
            ->assertSee('stock_note.*', false);
    }
}
