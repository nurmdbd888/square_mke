<?php

namespace Tests\Feature\Payments;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Payments\PartyBalance;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
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

    public function test_receipt_form_hides_automatic_number_and_shows_payment_method_first(): void
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

        $html = $this->actingAs($this->userWithRole('collector'))
            ->get(route('payments.create', ['kind' => 'receipt']))
            ->assertOk()
            ->assertSee('href="'.route('collector.index').'"', false)
            ->assertSee(__('Back to Collections'), false)
            ->assertDontSee('data-testid="payment-number"', false)
            ->assertDontSee(__('Receipt number'), false)
            ->assertDontSee(__('Your receipt or collection voucher number — not the sales invoice number.'), false)
            ->assertDontSee('data-testid="ocr-scan-panel"', false)
            ->assertDontSee('data-testid="slip-photos-input"', false)
            ->assertDontSee(__('Payment slip photos'), false)
            ->assertDontSee('class="card grid gap-4 sm:grid-cols-2"', false)
            ->assertDontSee('class="grid gap-4 sm:grid-cols-2"', false)
            ->assertSee('name="payment_date"', false)
            ->assertSee('name="amount"', false)
            ->assertSee('name="retailer_id"', false)
            ->assertSee('name="method"', false)
            ->getContent();

        $this->assertLessThan(strpos($html, 'name="payment_date"'), strpos($html, 'name="method"'));
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

    public function test_payments_page_and_invoice_payment_are_supplier_purchase_invoice_focused(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $supplier = Supplier::query()->create(['name' => 'Soap Supplier', 'code' => 'SUP-1']);
        $invoice = app(DocumentWriter::class)->save('purchase', [
            'number' => 'P-PAY-1',
            'supplier_id' => $supplier->id,
            'doc_date' => now()->toDateString(),
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '100', 'line_value' => '500']], $owner);

        $this->actingAs($owner)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('data-testid="purchase-invoices-due"', false)
            ->assertSee('data-testid="supplier-pay-'.$invoice->id.'"', false)
            ->assertSee('P-PAY-1', false)
            ->assertSee('Soap Supplier', false)
            ->assertDontSee('data-testid="collector-collect-'.$invoice->id.'"', false);

        $this->actingAs($owner)
            ->get(route('payments.create', [
                'kind' => 'payment',
                'supplier_id' => $supplier->id,
                'document_id' => $invoice->id,
            ]))
            ->assertOk()
            ->assertSee('data-testid="purchase-invoice-details"', false)
            ->assertSee('name="amount"', false)
            ->assertSee('name="method"', false)
            ->assertDontSee('name="allocations[0][amount]"', false)
            ->assertSee('action="'.route('payments.store', ['document_id' => $invoice->id]).'"', false);

        $this->actingAs($owner)
            ->post(route('payments.store', ['document_id' => $invoice->id]), [
                'kind' => 'payment',
                'number' => 'PAY-PUR-1',
                'supplier_id' => $supplier->id,
                'method' => 'bank',
                'amount' => '200',
                'payment_date' => now()->toDateString(),
                'action' => 'post',
            ])
            ->assertRedirect();

        $payment = Payment::query()->sole();
        $this->assertSame(Payment::KIND_PAYMENT, $payment->kind);
        $this->assertSame($invoice->id, $payment->allocations()->sole()->document_id);
        $this->assertSame('200.00', (string) $payment->allocations()->sole()->amount);
        $this->assertSame('300.00', (string) app(PartyBalance::class)
            ->openInvoices('supplier', $supplier->id)->sole()->remaining);
    }

    public function test_collection_started_from_invoice_is_scoped_to_that_invoice(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Corner Shop', 'code' => 'R1']);
        $invoiceIds = [];

        foreach (['S-LOCK-1', 'S-LOCK-2'] as $number) {
            $invoiceIds[] = app(DocumentWriter::class)->save('sale', [
                'number' => $number,
                'retailer_id' => $retailer->id,
                'doc_date' => now()->toDateString(),
                'action' => 'post',
            ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '100', 'line_value' => '500']], $owner)->id;
        }

        [$selectedInvoiceId, $otherInvoiceId] = $invoiceIds;
        $collector = $this->userWithRole('collector');
        $this->actingAs($collector)
            ->get(route('payments.create', [
                'kind' => 'receipt',
                'retailer_id' => $retailer->id,
                'document_id' => $selectedInvoiceId,
            ]))
            ->assertOk()
            ->assertSee('S-LOCK-1', false)
            ->assertDontSee('S-LOCK-2', false)
            ->assertDontSee('data-testid="invoice-picker"', false)
            ->assertDontSee('data-testid="payment-retailer-picker"', false)
            ->assertSee('name="allocations[0][document_id]" value="'.$selectedInvoiceId.'"', false)
            ->assertSee('action="'.route('payments.store', ['document_id' => $selectedInvoiceId]).'"', false);

        $receiptData = [
            'kind' => 'receipt',
            'retailer_id' => $retailer->id,
            'method' => 'cash',
            'amount' => '100',
            'payment_date' => now()->toDateString(),
            'action' => 'post',
        ];

        $this->post(route('payments.store', ['document_id' => $selectedInvoiceId]), $receiptData + [
            'allocations' => [
                ['document_id' => $selectedInvoiceId, 'amount' => '50'],
                ['document_id' => $otherInvoiceId, 'amount' => '50'],
            ],
        ])->assertSessionHasErrors('allocations');
        $this->assertSame(0, Payment::query()->count());

        $this->post(route('payments.store', ['document_id' => $selectedInvoiceId]), $receiptData + [
            'allocations' => [
                ['document_id' => $selectedInvoiceId, 'amount' => '100'],
            ],
        ])->assertRedirect();

        $receipt = Payment::query()->sole();
        $this->assertMatchesRegularExpression('/^RC-\d{8}-[A-Z0-9]{8}$/', $receipt->number);
        $this->actingAs($collector)
            ->get(route('payments.show', $receipt))
            ->assertOk()
            ->assertSee($receipt->number, false)
            ->assertSee(__('Receipt'), false)
            ->assertSee(__('Method'), false);

        $this->assertSame(
            [$selectedInvoiceId],
            PaymentAllocation::query()->pluck('document_id')->all()
        );
        $this->assertSame(Document::COLLECTION_PARTIAL, Document::query()->findOrFail($selectedInvoiceId)->collection_status);
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
