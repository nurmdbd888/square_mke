<?php

namespace Tests\Feature\Documents;

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
}
