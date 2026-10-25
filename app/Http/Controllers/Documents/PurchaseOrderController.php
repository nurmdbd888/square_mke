<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Money\Exact;
use App\Domain\Purchases\PurchaseOrderWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $orders = PurchaseOrder::query()
            ->with(['supplier', 'lines.product', 'payments'])
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(function ($where) use ($like) {
                    $where->where('number', 'like', $like)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $like)->orWhere('code', 'like', $like));
                });
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'canManage' => $request->user()->can('purchases.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('purchase-orders.form', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->orderBy('name_en')->get(['id', 'code', 'name_en', 'name_bn', 'unit', 'buy_price']),
            'orderDate' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, PurchaseOrderWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'expected_delivery_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.qty' => ['required', 'decimal:0,3', 'gt:0'],
            'items.*.unit_price' => ['required', 'decimal:0,4', 'min:0'],
        ]);

        $order = $workflow->create($data, array_values($data['items']), $request->user());

        return redirect()->route('purchases.orders.show', $order)
            ->with('status', __('Purchase order :number was created and is pending.', ['number' => $order->number]));
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'lines.product',
            'receipts.lines.orderLine.product',
            'receipts.document',
            'receipts.receiver',
            'payments',
        ]);

        return view('purchase-orders.show', [
            'order' => $purchaseOrder,
            'canManage' => $request->user()->can('purchases.manage'),
        ]);
    }

    public function receive(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'lines.product']);
        $openLines = $purchaseOrder->lines
            ->filter(fn (PurchaseOrderLine $line) => Exact::of($line->qty)->isGreaterThan(Exact::of($line->received_qty)))
            ->values();

        abort_if($openLines->isEmpty(), 404);

        return view('purchase-orders.receive', [
            'order' => $purchaseOrder,
            'openLines' => $openLines,
            'receivedOn' => now()->toDateString(),
        ]);
    }

    public function storeReceipt(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'received_on' => ['required', 'date_format:Y-m-d'],
            'challan_no' => ['nullable', 'string', 'max:80'],
            'vehicle_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.purchase_order_line_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_lines,id'],
            'items.*.status' => ['required', Rule::in([PurchaseOrderReceiptLine::STATUS_CONFIRMED, PurchaseOrderReceiptLine::STATUS_DUE])],
            'items.*.due_qty' => ['nullable', 'decimal:0,3', 'min:0'],
        ]);

        $receipt = $workflow->receive(
            $purchaseOrder,
            $data,
            array_values($data['items']),
            $request->user(),
        );
        $message = $receipt->document_id
            ? __('Delivery :number was posted and the received goods were added to stock.', ['number' => $receipt->number])
            : __('Delivery :number was recorded; all outstanding quantities remain due.', ['number' => $receipt->number]);

        return redirect()->route('purchases.orders.show', $purchaseOrder)->with('status', $message);
    }

    public function storePayment(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'required_if:method,pdc', 'date_format:Y-m-d', 'after_or_equal:payment_date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment = $workflow->recordPayment($purchaseOrder, $data, $request->user());

        return redirect()->route('purchases.orders.show', $purchaseOrder)
            ->with('status', __('Payment :number was recorded against purchase order :order.', [
                'number' => $payment->number,
                'order' => $purchaseOrder->number,
            ]));
    }
}
