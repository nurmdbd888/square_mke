<?php

namespace Tests\Feature\M9;

use App\Domain\Documents\DocumentWriter;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_invoice_print_includes_firm_name_and_qr(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Corner Shop']);

        $document = app(DocumentWriter::class)->save('sale', [
            'number' => 'S-PRINT-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '2', 'unit_price' => '100', 'line_value' => '200']], $owner);

        $response = $this->actingAs($owner)->get(route('sales.print.invoice', $document));
        $response->assertOk();
        $response->assertSee(setting('firm_name'), false);
        $response->assertSee('<svg', false);
        $response->assertSee('S-PRINT-1', false);
    }

    public function test_purchase_challan_print_works(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs']);
        $supplier = Supplier::query()->create(['name' => 'Square']);

        $document = app(DocumentWriter::class)->save('purchase', [
            'number' => 'P-PRINT-1',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '50', 'line_value' => '500']], $owner);

        $this->actingAs($owner)
            ->get(route('purchases.print.challan', $document))
            ->assertOk()
            ->assertSee('P-PRINT-1', false);
    }

    public function test_thermal_print_view_renders(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);

        $document = app(DocumentWriter::class)->save('sale', [
            'number' => 'S-TH-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '10', 'line_value' => '10']], $owner);

        $this->actingAs($owner)
            ->get(route('sales.print.thermal', $document))
            ->assertOk()
            ->assertSee('80mm', false);
    }
}
