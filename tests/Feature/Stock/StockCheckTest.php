<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\StockCheck;
use App\Models\StockLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_index_shows_stock_check_instead_of_compare_with_export(): void
    {
        $this->actingAs($this->owner())
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('data-testid="stock-check-start"', false)
            ->assertSee(__('Stock Check'), false)
            ->assertDontSee(__('Compare with export'), false);
    }

    public function test_checking_mode_shows_in_store_column_and_actions(): void
    {
        Product::query()->create(['code' => 'SC1', 'name_en' => 'Check item', 'unit' => 'Pcs']);

        $html = $this->actingAs($this->owner())
            ->get(route('stock.index', ['checking' => 1]))
            ->assertOk()
            ->assertSee('data-testid="in-store-header"', false)
            ->assertSee(__('IN STORE'), false)
            ->assertSee(__('CONFIRM STOCK'), false)
            ->assertSee(__('CANCEL'), false)
            ->assertSee(__('Available'), false)
            ->assertSee(__('Not Available'), false)
            ->getContent();

        $this->assertStringContainsString('stockCheckPage', $html);
        $this->assertTrue(
            str_contains($html, '"checking":true')
            || str_contains($html, '\u0022checking\u0022:true'),
            'Expected checking:true in Alpine config'
        );
    }

    public function test_confirm_stock_check_and_export_report(): void
    {
        $owner = $this->owner();
        $a = Product::query()->create(['code' => 'A1', 'name_en' => 'Alpha', 'unit' => 'Pcs']);
        $b = Product::query()->create(['code' => 'B1', 'name_en' => 'Beta', 'unit' => 'Pcs']);

        StockLedgerEntry::query()->create([
            'product_id' => $a->id,
            'doc_date' => '2026-10-01',
            'movement' => 'purchase',
            'rank' => 1,
            'posted_at' => now(),
            'qty_in' => 5,
            'qty_out' => 0,
            'value_in' => 50,
            'value_out' => 0,
            'running_qty' => 5,
            'running_value' => 50,
            'average_cost' => 10,
        ]);

        $this->actingAs($owner)
            ->post(route('stock.checks.store'), [
                'items' => [
                    ['product_id' => $a->id, 'in_store' => 'available'],
                    ['product_id' => $b->id, 'in_store' => 'not_available'],
                ],
            ])
            ->assertRedirect();

        $check = StockCheck::query()->first();
        $this->assertNotNull($check);
        $this->assertSame(2, $check->lines()->count());
        $this->assertSame('available', $check->lines()->where('product_id', $a->id)->value('in_store'));
        $this->assertSame('not_available', $check->lines()->where('product_id', $b->id)->value('in_store'));
        $this->assertSame('5.000', (string) $check->lines()->where('product_id', $a->id)->value('on_hand_qty'));

        $csv = $this->actingAs($owner)
            ->get(route('stock.checks.export', $check))
            ->assertOk()
            ->assertHeader('content-disposition')
            ->streamedContent();

        $this->assertStringContainsString('IN STORE', $csv);
        $this->assertStringContainsString('Alpha', $csv);
        $this->assertStringContainsString('Available', $csv);
        $this->assertStringContainsString('Not Available', $csv);

        $this->actingAs($owner)
            ->get(route('stock.index', ['export_check' => $check->id]))
            ->assertOk()
            ->assertSee('data-testid="stock-check-export"', false)
            ->assertSee(__('Export report'), false);
    }

    public function test_viewer_cannot_start_stock_check(): void
    {
        $this->actingAs($this->userWithRole('viewer'))
            ->get(route('stock.index'))
            ->assertOk()
            ->assertDontSee('data-testid="stock-check-start"', false);

        $product = Product::query()->create(['code' => 'V1', 'name_en' => 'Viewer item', 'unit' => 'Pcs']);

        $this->actingAs($this->userWithRole('viewer'))
            ->post(route('stock.checks.store'), [
                'items' => [
                    ['product_id' => $product->id, 'in_store' => 'available'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_stock_check_history_lists_who_when_marks_and_export(): void
    {
        $owner = $this->owner();
        $a = Product::query()->create(['code' => 'H1', 'name_en' => 'History Alpha', 'unit' => 'Pcs']);
        $b = Product::query()->create(['code' => 'H2', 'name_en' => 'History Beta', 'unit' => 'Pcs']);

        $this->actingAs($owner)
            ->post(route('stock.checks.store'), [
                'items' => [
                    ['product_id' => $a->id, 'in_store' => 'available'],
                    ['product_id' => $b->id, 'in_store' => 'not_available'],
                ],
            ])
            ->assertRedirect();

        $check = StockCheck::query()->first();
        $this->assertNotNull($check);

        $this->actingAs($owner)
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('data-testid="stock-check-history"', false)
            ->assertSee(__('Past checks'), false);

        $this->actingAs($owner)
            ->get(route('stock.checks.index'))
            ->assertOk()
            ->assertSee('data-testid="stock-checks-table"', false)
            ->assertSee($owner->name, false)
            ->assertSee('data-testid="stock-check-export-'.$check->id.'"', false);

        $this->actingAs($owner)
            ->get(route('stock.checks.show', $check))
            ->assertOk()
            ->assertSee('data-testid="stock-check-meta"', false)
            ->assertSee('History Alpha', false)
            ->assertSee(__('Available'), false)
            ->assertSee(__('Not Available'), false);

        $csv = $this->actingAs($owner)
            ->get(route('stock.checks.export', $check))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('History Alpha', $csv);
        $this->assertStringContainsString('Not Available', $csv);
    }
}
