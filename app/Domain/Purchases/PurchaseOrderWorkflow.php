<?php

namespace App\Domain\Purchases;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Money\Exact;
use App\Domain\Payments\PaymentWriter;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PurchaseOrderWorkflow
{
    public function __construct(
        private readonly DocumentWriter $documents,
        private readonly PaymentWriter $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array{product_id: int, qty: string, unit_price: string}>  $items
     */
    public function create(array $header, array $items, User $by): PurchaseOrder
    {
        return DB::transaction(function () use ($header, $items, $by) {
            $order = PurchaseOrder::query()->create([
                'number' => 'PO-TEMP-'.Str::uuid(),
                'supplier_id' => (int) $header['supplier_id'],
                'order_date' => Carbon::parse($header['order_date'])->toDateString(),
                'expected_delivery_date' => empty($header['expected_delivery_date'])
                    ? null
                    : Carbon::parse($header['expected_delivery_date'])->toDateString(),
                'notes' => $header['notes'] ?? null,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);

            $order->number = 'PO-'.Carbon::parse($order->order_date)->format('Ymd').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($items as $index => $item) {
                PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $order->id,
                    'product_id' => (int) $item['product_id'],
                    'line_no' => $index + 1,
                    'qty' => Exact::qty($item['qty']),
                    'unit_price' => Exact::price($item['unit_price']),
                ]);
            }

            AuditLogger::log('purchase_order.created', $order, null, [
                'number' => $order->number,
                'supplier_id' => $order->supplier_id,
                'line_count' => count($items),
            ], $by);

            return $order->fresh(['supplier', 'lines.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array{purchase_order_line_id: int, status: string, due_qty: string|null}>  $items
     */
    public function receive(PurchaseOrder $purchaseOrder, array $header, array $items, User $by): PurchaseOrderReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $header, $items, $by) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            $lines = $order->lines()->lockForUpdate()->get()->keyBy('id');
            $openLines = $lines->filter(fn (PurchaseOrderLine $line) => Exact::of($line->qty)->isGreaterThan(Exact::of($line->received_qty)));
            $itemsById = collect($items)->keyBy(fn (array $item) => (int) $item['purchase_order_line_id']);

            if ($openLines->isEmpty()) {
                throw ValidationException::withMessages(['purchase_order' => __('This purchase order has already been fully received.')]);
            }

            if ($itemsById->count() !== count($items) || $itemsById->keys()->sort()->values()->all() !== $openLines->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages(['items' => __('Refresh the page and submit a delivery status for every outstanding product.')]);
            }

            $receipt = PurchaseOrderReceipt::query()->create([
                'purchase_order_id' => $order->id,
                'number' => 'GRN-TEMP-'.Str::uuid(),
                'received_on' => Carbon::parse($header['received_on'])->toDateString(),
                'challan_no' => $header['challan_no'] ?? null,
                'vehicle_no' => $header['vehicle_no'] ?? null,
                'notes' => $header['notes'] ?? null,
                'received_by' => $by->id,
            ]);
            $receipt->number = 'GRN-'.Carbon::parse($receipt->received_on)->format('Ymd').'-'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT);
            $receipt->save();

            $acceptedLines = [];
            $acceptedQuantity = Exact::of('0');
            $invoicedValues = DocumentLine::query()
                ->whereHas('document', fn ($query) => $query
                    ->where('purchase_order_id', $order->id)
                    ->where('status', Document::STATUS_POSTED))
                ->selectRaw('product_id, SUM(line_value) as invoiced_value')
                ->groupBy('product_id')
                ->pluck('invoiced_value', 'product_id');

            foreach ($openLines as $line) {
                $item = $itemsById->get($line->id);
                $remaining = Exact::of($line->qty)->minus(Exact::of($line->received_qty));
                $submittedDue = Exact::of($item['due_qty'] ?? 0);

                if ($item['status'] === PurchaseOrderReceiptLine::STATUS_CONFIRMED && $submittedDue->isPositive()) {
                    throw ValidationException::withMessages(['items' => __('A confirmed product cannot have a due quantity.')]);
                }
                $due = Exact::of($item['status'] === PurchaseOrderReceiptLine::STATUS_CONFIRMED ? 0 : $submittedDue);
                if ($item['status'] === PurchaseOrderReceiptLine::STATUS_DUE && (! $due->isPositive() || $due->isGreaterThan($remaining))) {
                    throw ValidationException::withMessages(['items' => __('Enter a due quantity greater than zero and no more than the quantity still outstanding.')]);
                }

                $received = $remaining->minus($due);
                PurchaseOrderReceiptLine::query()->create([
                    'purchase_order_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $line->id,
                    'received_qty' => Exact::qty($received),
                    'due_qty' => Exact::qty($due),
                    'status' => $item['status'],
                ]);
                $line->received_qty = Exact::qty(Exact::of($line->received_qty)->plus($received));
                $line->save();

                if ($received->isPositive()) {
                    $acceptedQuantity = $acceptedQuantity->plus($received);
                    $cumulativeValue = Exact::money(
                        Exact::of($line->received_qty)->multipliedBy(Exact::of($line->unit_price))
                    );
                    $lineValue = Exact::money(
                        Exact::of($cumulativeValue)->minus(Exact::of($invoicedValues->get($line->product_id, '0')))
                    );
                    $acceptedLines[] = [
                        'product_id' => $line->product_id,
                        'qty' => Exact::qty($received),
                        'unit_price' => Exact::price($line->unit_price),
                        'line_value' => $lineValue,
                        'line_discount' => '0.00',
                        'sd_pct' => '0.0000',
                        'vat_pct' => '0.0000',
                        'sd_amount' => '0.00',
                        'vat_amount' => '0.00',
                    ];
                }
            }

            if ($acceptedLines !== []) {
                $number = 'PO-'.$order->id.'-GRN-'.$receipt->id;
                $document = $this->documents->save('purchase', [
                    'number' => $number,
                    'supplier_id' => $order->supplier_id,
                    'doc_date' => $receipt->received_on->toDateString(),
                    'delivery_date' => $receipt->received_on->toDateString(),
                    'notes' => $this->receiptDocumentNotes($order, $receipt),
                    'action' => 'post',
                ], $acceptedLines, $by);

                $document->forceFill(['purchase_order_id' => $order->id])->save();
                $receipt->document_id = $document->id;
                $receipt->save();
                $this->allocateOrderPayments($order, $document, $by);
            }

            AuditLogger::log('purchase_order.received', $receipt, null, [
                'number' => $receipt->number,
                'purchase_order_id' => $order->id,
                'received_quantity' => Exact::qty($acceptedQuantity),
                'due_quantity' => Exact::qty($receipt->lines()->sum('due_qty')),
                'document_id' => $receipt->document_id,
            ], $by);

            return $receipt->fresh(['lines.orderLine.product', 'document']);
        });
    }

    /**
     * @param  array<string, mixed>  $header
     */
    public function recordPayment(PurchaseOrder $purchaseOrder, array $header, User $by): Payment
    {
        return DB::transaction(function () use ($purchaseOrder, $header, $by) {
            $order = PurchaseOrder::query()->lockForUpdate()->with('lines')->findOrFail($purchaseOrder->id);
            $amount = Exact::of($header['amount']);
            $paid = Exact::of(
                $order->payments()
                    ->where('payments.status', Payment::STATUS_POSTED)
                    ->sum('payments.amount')
            );
            $remaining = Exact::of($order->totalAmount())->minus($paid);

            if (! $amount->isPositive() || $amount->isGreaterThan($remaining)) {
                throw ValidationException::withMessages([
                    'amount' => __('The payment must be greater than zero and no more than the unpaid purchase order total.'),
                ]);
            }

            $payment = $this->payments->save([
                'kind' => Payment::KIND_PAYMENT,
                'number' => 'PO-PAY-'.Str::upper(Str::random(12)),
                'supplier_id' => $order->supplier_id,
                'method' => $header['method'],
                'amount' => Exact::money($amount),
                'payment_date' => $header['payment_date'],
                'due_date' => $header['due_date'] ?? null,
                'reference' => $header['reference'] ?? $order->number,
                'notes' => $header['notes'] ?? __('Advance payment for purchase order :number', ['number' => $order->number]),
                'is_advance' => true,
                'action' => 'post',
            ], [], $by);

            $order->payments()->attach($payment->id);
            $order->receipts()
                ->whereNotNull('document_id')
                ->with('document')
                ->get()
                ->each(function (PurchaseOrderReceipt $receipt) use ($order, $by) {
                    if ($receipt->document?->isPosted()) {
                        $this->allocateOrderPayments($order, $receipt->document, $by);
                    }
                });
            AuditLogger::log('purchase_order.payment_recorded', $order, null, [
                'payment_id' => $payment->id,
                'payment_number' => $payment->number,
                'amount' => $payment->amount,
            ], $by);

            return $payment;
        });
    }

    private function allocateOrderPayments(PurchaseOrder $order, Document $document, User $by): void
    {
        $payments = $order->payments()
            ->where('payments.status', Payment::STATUS_POSTED)
            ->with('allocations')
            ->orderBy('payments.payment_date')
            ->orderBy('payments.id')
            ->get();

        foreach ($payments as $payment) {
            $alreadyAllocated = Exact::of('0');
            foreach ($payment->allocations as $allocation) {
                $alreadyAllocated = $alreadyAllocated->plus(Exact::of($allocation->amount));
            }
            $available = Exact::of($payment->amount)->minus($alreadyAllocated);
            if (! $available->isPositive()) {
                continue;
            }

            $allocatedToInvoice = Exact::of(
                PaymentAllocation::query()
                    ->where('document_id', $document->id)
                    ->whereHas('payment', fn ($query) => $query->where('status', Payment::STATUS_POSTED))
                    ->sum('amount')
            );
            $invoiceDue = Exact::of($document->net_total)->minus($allocatedToInvoice);
            if (! $invoiceDue->isPositive()) {
                continue;
            }

            $amount = $available->isLessThan($invoiceDue) ? $available : $invoiceDue;
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'document_id' => $document->id,
                'amount' => Exact::money($amount),
            ]);
            $payment->is_advance = $amount->isLessThan($available);
            $payment->updated_by = $by->id;
            $payment->save();

            AuditLogger::log('purchase_order.payment_allocated', $payment, null, [
                'document_id' => $document->id,
                'amount' => Exact::money($amount),
            ], $by);
        }
    }

    private function receiptDocumentNotes(PurchaseOrder $order, PurchaseOrderReceipt $receipt): string
    {
        $parts = [
            __('Received against purchase order :number', ['number' => $order->number]),
            $receipt->challan_no ? __('Challan No.: :value', ['value' => $receipt->challan_no]) : null,
            $receipt->vehicle_no ? __('Vehicle No.: :value', ['value' => $receipt->vehicle_no]) : null,
            $receipt->notes,
        ];

        return implode("\n", array_filter($parts));
    }
}
