<?php

namespace Tests\Feature\Purchases;

use App\Domain\Payments\PartyBalance;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_orders_can_be_paid_and_received_in_partial_deliveries(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::query()->create(['name' => 'Soap Supplier']);
        $product = Product::query()->create([
            'name_en' => 'Soap',
            'unit' => 'Pcs',
            'buy_price' => '100.0000',
        ]);

        $this->actingAs($owner)
            ->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('data-testid="purchase-orders-link"', false)
            ->assertSee(__('Purchase Orders'), false);

        $this->actingAs($owner)
            ->post(route('purchases.orders.store'), [
                'supplier_id' => $supplier->id,
                'order_date' => '2026-10-25',
                'expected_delivery_date' => '2026-11-01',
                'items' => [
                    ['product_id' => $product->id, 'qty' => '10', 'unit_price' => '100'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $order = PurchaseOrder::query()->with('lines')->sole();
        $this->assertSame('PO-20261025-000001', $order->number);
        $this->assertSame('Pending', $order->statusLabel());

        $this->actingAs($owner)
            ->post(route('purchases.orders.payments.store', $order), [
                'method' => 'cash',
                'amount' => '1000',
                'payment_date' => '2026-10-25',
            ])
            ->assertSessionHasNoErrors();

        $order->refresh()->load(['lines', 'payments']);
        $this->assertSame('Paid / Waiting for Arrival', $order->statusLabel());
        $payment = $order->payments->sole();
        $this->assertTrue($payment->isPosted());
        $this->assertTrue($payment->is_advance);

        $this->actingAs($owner)
            ->post(route('purchases.orders.receive.store', $order), [
                'received_on' => '2026-10-25',
                'challan_no' => 'CH-101',
                'vehicle_no' => 'DHAKA-METRO-11',
                'items' => [
                    ['purchase_order_line_id' => $order->lines->first()->id, 'status' => 'due', 'due_qty' => '2'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $order->refresh()->load(['lines', 'receipts.document', 'payments.allocations']);
        $firstReceipt = $order->receipts->first();
        $firstInvoice = $firstReceipt->document;
        $this->assertSame(Document::STATUS_POSTED, $firstInvoice->status);
        $this->assertSame($order->id, $firstInvoice->purchase_order_id);
        $this->actingAs($owner)->get(route('purchases.edit', $firstInvoice))->assertNotFound();
        $this->actingAs($owner)
            ->delete(route('purchases.destroy', $firstInvoice))
            ->assertSessionHasErrors('status');
        $this->assertSame(Document::STATUS_POSTED, $firstInvoice->fresh()->status);
        $this->assertSame('8.000', (string) $order->lines->first()->received_qty);
        $this->assertSame('2.000', (string) $firstReceipt->lines->first()->due_qty);
        $this->assertSame('800.00', (string) PaymentAllocation::query()
            ->where('payment_id', $payment->id)
            ->where('document_id', $firstInvoice->id)
            ->value('amount'));
        $this->assertSame('8.000', \App\Domain\Money\Exact::qty(
            StockLedgerEntry::query()->where('document_id', $firstInvoice->id)->sum('qty_in')
        ));
        $this->assertSame('Partially Received', $order->statusLabel());

        $this->actingAs($owner)
            ->post(route('purchases.orders.receive.store', $order), [
                'received_on' => '2026-10-26',
                'items' => [
                    ['purchase_order_line_id' => $order->lines->first()->id, 'status' => 'confirmed', 'due_qty' => '0'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $order->refresh()->load(['lines', 'receipts.document', 'payments']);
        $this->assertTrue($order->isFullyReceived());
        $this->assertSame('Received', $order->statusLabel());
        $this->assertCount(2, $order->receipts);
        $this->assertSame('1000.00', (string) PaymentAllocation::query()->where('payment_id', $payment->id)->sum('amount'));
        $this->assertCount(0, app(PartyBalance::class)->openInvoices('supplier', $supplier->id));
    }

    public function test_delivery_due_quantity_must_be_positive_and_within_remaining_quantity(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::query()->create(['name' => 'Soap Supplier']);
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'buy_price' => '100']);
        $order = PurchaseOrder::query()->create([
            'number' => 'PO-TEST-1',
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-25',
        ]);
        $line = $order->lines()->create([
            'product_id' => $product->id,
            'line_no' => 1,
            'qty' => '5',
            'unit_price' => '100',
        ]);

        $this->actingAs($owner)
            ->from(route('purchases.orders.receive', $order))
            ->post(route('purchases.orders.receive.store', $order), [
                'received_on' => '2026-10-25',
                'items' => [
                    ['purchase_order_line_id' => $line->id, 'status' => 'due', 'due_qty' => '6'],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, PurchaseOrderReceipt::query()->count());
        $this->assertSame(0, Document::query()->where('type', Document::TYPE_PURCHASE)->count());
    }

    public function test_payment_recorded_after_delivery_is_allocated_to_the_purchase_invoice(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::query()->create(['name' => 'Soap Supplier']);
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'buy_price' => '100']);
        $order = PurchaseOrder::query()->create([
            'number' => 'PO-TEST-2',
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-25',
        ]);
        $line = $order->lines()->create([
            'product_id' => $product->id,
            'line_no' => 1,
            'qty' => '1',
            'unit_price' => '100',
        ]);

        $this->actingAs($owner)
            ->post(route('purchases.orders.receive.store', $order), [
                'received_on' => '2026-10-25',
                'items' => [
                    ['purchase_order_line_id' => $line->id, 'status' => 'confirmed', 'due_qty' => '0'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $invoice = Document::query()->where('purchase_order_id', $order->id)->sole();
        $this->actingAs($owner)
            ->post(route('purchases.orders.payments.store', $order), [
                'method' => 'cash',
                'amount' => '100',
                'payment_date' => '2026-10-25',
            ])
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();
        $this->assertSame($invoice->id, $payment->allocations()->sole()->document_id);
        $this->assertSame('100.00', (string) $payment->allocations()->sole()->amount);
        $this->assertSame('0.00', $order->fresh()->remainingPayment());
    }

    public function test_split_delivery_rounding_matches_the_ordered_line_total(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::query()->create(['name' => 'Soap Supplier']);
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'buy_price' => '0.01']);
        $order = PurchaseOrder::query()->create([
            'number' => 'PO-TEST-3',
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-25',
        ]);
        $line = $order->lines()->create([
            'product_id' => $product->id,
            'line_no' => 1,
            'qty' => '2.4',
            'unit_price' => '0.01',
        ]);

        foreach (['1.8', '1.2', '0.6'] as $index => $dueQty) {
            $this->actingAs($owner)
                ->post(route('purchases.orders.receive.store', $order), [
                    'received_on' => '2026-10-25',
                    'items' => [
                        ['purchase_order_line_id' => $line->id, 'status' => 'due', 'due_qty' => $dueQty],
                    ],
                ])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($owner)
            ->post(route('purchases.orders.receive.store', $order), [
                'received_on' => '2026-10-25',
                'items' => [
                    ['purchase_order_line_id' => $line->id, 'status' => 'confirmed', 'due_qty' => '0'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.02', $order->fresh()->totalAmount());
        $this->assertSame('0.02', (string) Document::query()->where('purchase_order_id', $order->id)->sum('net_total'));
        $this->assertSame('0.02', (string) DocumentLine::query()
            ->whereHas('document', fn ($query) => $query->where('purchase_order_id', $order->id))
            ->sum('line_value'));
    }
}
