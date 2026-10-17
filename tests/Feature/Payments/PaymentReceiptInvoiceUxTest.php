<?php

namespace Tests\Feature\Payments;

use App\Domain\Documents\DocumentWriter;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptInvoiceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_form_uses_invoice_number_label(): void
    {
        $this->actingAs($this->userWithRole('accountant'))
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('>'.__('Invoice number').'<', false);
    }

    public function test_receipt_form_separates_receipt_number_and_invoice_picker(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Corner Shop', 'code' => 'R1']);

        app(DocumentWriter::class)->save('sale', [
            'number' => 'S-UX-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '100', 'line_value' => '500']], $owner);

        $this->actingAs($this->userWithRole('collector'))
            ->get(route('payments.create', ['kind' => 'receipt']))
            ->assertOk()
            ->assertSee('data-testid="payment-number"', false)
            ->assertSee(__('Receipt number'), false)
            ->assertSee(__('Your receipt or collection voucher number — not the sales invoice number.'), false);
    }

    public function test_open_invoices_fragment_includes_picker_for_retailer(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);

        app(DocumentWriter::class)->save('sale', [
            'number' => 'S-HTMX-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '200', 'line_value' => '200']], $owner);

        $this->actingAs($owner)
            ->get(route('payments.open-invoices', ['party' => 'retailer', 'retailer_id' => $retailer->id]))
            ->assertOk()
            ->assertSee('data-testid="invoice-picker"', false)
            ->assertSee('S-HTMX-1', false);
    }

    public function test_open_invoices_without_party_returns_fragment_not_redirect(): void
    {
        $this->actingAs($this->owner())
            ->get(route('payments.open-invoices'))
            ->assertOk()
            ->assertSee(__('No open invoices.'), false)
            ->assertDontSee(__('Edit receipt'), false)
            ->assertDontSee(__('The party field is required.'), false)
            ->assertDontSee('id="sidebar"', false);
    }

    public function test_edit_receipt_form_is_single_shell_with_allocation_box(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Motalib', 'code' => '366350']);
        $payment = Payment::query()->create([
            'kind' => Payment::KIND_RECEIPT,
            'number' => 'RC-EDIT-1',
            'retailer_id' => $retailer->id,
            'method' => 'cash',
            'amount' => '2000',
            'payment_date' => '2026-10-18',
            'status' => Payment::STATUS_DRAFT,
        ]);

        $html = $this->actingAs($owner)
            ->get(route('payments.edit', $payment))
            ->assertOk()
            ->assertSee(__('Edit receipt'), false)
            ->assertSee('id="allocation-box"', false)
            ->assertSee('hx-target="#allocation-box"', false)
            ->assertSee('name="party"', false)
            ->assertDontSee(__('The party field is required.'), false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'id="sidebar"'));
        $this->assertSame(1, substr_count($html, '>'.e(__('Edit receipt')).'<'));
    }
}
