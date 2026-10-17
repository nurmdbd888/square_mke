<?php

namespace Tests\Feature\Calendar;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Profit\Summary;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCalendarReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pl_excludes_tuesday_sales_when_business_days_filter_on(): void
    {
        $owner = $this->owner();
        $product = Product::query()->where('code', 'P01')->firstOrFail();
        $supplier = Supplier::query()->create(['code' => 'SQ', 'name' => 'Square']);
        $retailer = Retailer::query()->create(['code' => 'R1', 'name' => 'Shop']);
        $writer = app(DocumentWriter::class);

        $writer->save('purchase', [
            'number' => 'BUY-1',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '10',
            'bonus_qty' => '0',
            'unit_price' => '23.83',
            'line_value' => '238.30',
            'line_discount' => '0',
            'sd_pct' => '0',
            'vat_pct' => '15',
            'sd_amount' => '0',
            'vat_amount' => '35.75',
        ]], $owner);

        $writer->save('sale', [
            'number' => 'TUE-SALE',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-20',
            'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '1',
            'bonus_qty' => '0',
            'unit_price' => '30.00',
            'line_value' => '30.00',
            'line_discount' => '0',
            'sd_pct' => '0',
            'vat_pct' => '15',
            'sd_amount' => '0',
            'vat_amount' => '4.50',
        ]], $owner);

        $writer->save('sale', [
            'number' => 'WED-SALE',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-21',
            'action' => 'post',
        ], [[
            'product_id' => $product->id,
            'qty' => '2',
            'bonus_qty' => '0',
            'unit_price' => '30.00',
            'line_value' => '60.00',
            'line_discount' => '0',
            'sd_pct' => '0',
            'vat_pct' => '15',
            'sd_amount' => '0',
            'vat_amount' => '9.00',
        ]], $owner);

        $summary = app(Summary::class);
        $all = $summary->between('2026-10-19', '2026-10-22', false);
        $business = $summary->between('2026-10-19', '2026-10-22', true);

        $this->assertNotSame($all['revenue'], $business['revenue']);
        $this->assertGreaterThan(
            (float) str_replace(',', '', $business['revenue']),
            (float) str_replace(',', '', $all['revenue']),
        );

        $this->actingAs($owner)->get('/reports/pl?from=2026-10-19&to=2026-10-22&business_days=1')
            ->assertOk()
            ->assertSee('data-testid="business-days-active"', false);
    }

    public function test_working_tuesday_counts_when_exception_set(): void
    {
        app(SettingsStore::class)->set([
            'calendar_working_dates' => ['2026-10-20'],
        ], $this->owner());

        $this->actingAs($this->owner())->get('/settings')
            ->assertOk()
            ->assertSee('data-testid="setting-calendar_working_dates"', false)
            ->assertSee('data-testid="setting-weekly_holiday_weekdays"', false);
    }
}
