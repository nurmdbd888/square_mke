<?php

namespace Tests\Feature\Documents;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFormUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_and_sale_forms_show_sl_no_and_invoice_total(): void
    {
        $owner = $this->owner();

        foreach (['purchases.create', 'sales.create'] as $route) {
            $this->actingAs($owner)->get(route($route))
                ->assertOk()
                ->assertSee(__('SL. NO.'), false)
                ->assertSee('data-testid="line-sl-no"', false)
                ->assertSee('data-testid="invoice-total-summary"', false)
                ->assertSee('data-testid="invoice-grand-total"', false)
                ->assertSee('data-testid="invoice-total-footer"', false)
                ->assertSee('invoiceTotal', false)
                ->assertSee('linesSubtotal', false)
                ->assertSee(__('Total payable'), false)
                ->assertSee('data-testid="ocr-scan-panel"', false)
                ->assertSee('data-ocr-mode="invoice"', false);
        }
    }

    public function test_print_line_tables_use_sl_no_heading(): void
    {
        $this->assertStringContainsString(
            "__('SL. NO.')",
            file_get_contents(resource_path('views/print/partials/line-table.blade.php'))
        );
        $this->assertStringContainsString(
            "__('SL. NO.')",
            file_get_contents(resource_path('views/print/thermal-80mm.blade.php'))
        );
    }

    public function test_invoice_line_prices_use_two_decimals_and_product_column_has_room(): void
    {
        $owner = $this->owner();
        $owner->revokePermissionTo('settings.view');
        Product::query()->create([
            'name_en' => 'Price precision test',
            'pack_size' => '250 ml',
            'unit' => 'Pcs',
            'buy_price' => '10.5000',
            'sell_price' => '12.3456',
        ]);

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('\u0022buy\u0022:\u002210.50\u0022', false)
            ->assertSee('\u0022sell\u0022:\u002212.35\u0022', false)
            ->assertSee('\u0022pack_size\u0022:\u0022250 ml\u0022', false)
            ->assertSee('unit_price: priceInput(line.unit_price)', false)
            ->assertSee('min-w-[58rem] table-fixed', false)
            ->assertSee('w-[28%] min-w-[16rem]', false)
            ->assertSee(__('Pack size').': <span x-text="product.pack_size"></span>', false);
    }

    public function test_hidden_retailer_quick_create_field_does_not_block_sales_form_submit(): void
    {
        $this->actingAs($this->owner())->get(route('sales.create'))
            ->assertOk()
            ->assertSee('x-ref="createName"', false)
            ->assertSee(':required="creating"', false)
            ->assertSee('name="action" value="save"', false)
            ->assertSee('name="action" value="post"', false);
    }
}
