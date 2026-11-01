<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\Money\Exact;
use App\Domain\Payments\PaymentWriter;
use App\Domain\Payments\SaleCollectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\Access\PaymentAccess;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CollectorController extends Controller
{
    public function __construct(
        private readonly SaleCollectionStatus $collection,
        private readonly PaymentWriter $writer,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'due', 'partial'])],
            'overdue' => ['nullable', Rule::in(['0', '1', 'true', 'false', 'on'])],
            'sort' => ['nullable', Rule::in(['date', 'invoice', 'amount'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $status = $filters['status'] ?? 'all';
        $overdueOnly = in_array((string) ($filters['overdue'] ?? ''), ['1', 'true', 'on'], true);
        $q = $filters['q'] ?? null;

        $pending = new LengthAwarePaginator([], 0, 50, 1, ['path' => route('collector.index')]);
        $warning = null;
        $dueDays = 30;

        try {
            $dueDays = $this->collection->dueDays();
            $pending = $this->collection->paginateForCollector([
                'status' => in_array($status, ['due', 'partial'], true) ? $status : null,
                'overdue' => $overdueOnly,
                'q' => $q,
                'sort' => $filters['sort'] ?? 'date',
                'direction' => $filters['direction'] ?? 'asc',
            ]);
        } catch (Throwable $e) {
            report($e);
            $warning = __('Run migrations on the Maintenance page first.');
        }

        return view('collector.index', [
            'pending' => $pending,
            'collection' => $this->collection,
            'warning' => $warning,
            'dueDays' => $dueDays,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'overdue' => $overdueOnly,
                'sort' => $filters['sort'] ?? 'date',
                'direction' => $filters['direction'] ?? 'asc',
            ],
        ]);
    }

    public function show(Request $request, Document $document): View
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);
        abort_unless($document->isSale() && $document->isPosted(), 404);

        $remaining = $this->collection->remainingAmount($document);
        abort_if(! $remaining->isPositive(), 404);

        $document->load(['retailer', 'lines.product']);

        return view('collector.invoice', [
            'invoice' => $document,
            'remaining' => $remaining,
        ]);
    }

    public function store(Request $request, Document $document): RedirectResponse
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);
        abort_unless($document->isSale() && $document->isPosted() && $document->retailer_id, 404);

        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
        ]);
        $remaining = $this->collection->remainingAmount($document);
        abort_if(! $remaining->isPositive(), 404);
        if (Exact::of($data['amount'])->isGreaterThan($remaining)) {
            throw ValidationException::withMessages([
                'amount' => __('The amount cannot exceed the invoice balance due.'),
            ]);
        }

        $payment = $this->writer->save([
            'kind' => 'receipt',
            'number' => '',
            'retailer_id' => $document->retailer_id,
            'method' => 'cash',
            'amount' => $data['amount'],
            'payment_date' => now()->toDateString(),
            'action' => 'post',
        ], [[
            'document_id' => $document->id,
            'amount' => $data['amount'],
        ]], $request->user());

        return redirect()->route('payments.show', $payment)
            ->with('status', __('Collection saved. Receipt :number', ['number' => $payment->number]));
    }

    public function confirm(Request $request, Document $document): RedirectResponse
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);
        abort_unless($document->isSale(), 404);

        $this->collection->confirmCollected($document, $request->user());

        AuditLogger::log('collection.confirmed', $document, null, [
            'number' => $document->number,
            'status' => SaleCollectionStatus::COMPLETED,
        ]);

        return redirect()->back()->with('status', __('Invoice :number marked as fully collected.', ['number' => $document->number]));
    }

    public function paid(Request $request, Document $document): RedirectResponse
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);
        abort_unless($document->isSale() && $document->isPosted() && $document->retailer_id, 404);

        $remaining = $this->collection->remainingAmount($document);
        abort_if(! $remaining->isPositive(), 404);
        $payment = $this->writer->save([
            'kind' => 'receipt',
            'number' => '',
            'retailer_id' => $document->retailer_id,
            'method' => 'cash',
            'amount' => Exact::money($remaining),
            'payment_date' => now()->toDateString(),
            'action' => 'post',
        ], [[
            'document_id' => $document->id,
            'amount' => Exact::money($remaining),
        ]], $request->user());
        $this->collection->confirmCollected($document->fresh(), $request->user());
        AuditLogger::log('collection.confirmed', $document, null, [
            'number' => $document->number,
            'status' => SaleCollectionStatus::COMPLETED,
        ], $request->user());

        return redirect()->route('payments.show', $payment)
            ->with('status', __('Invoice :number paid in full. Receipt :receipt.', [
                'number' => $document->number,
                'receipt' => $payment->number,
            ]));
    }
}
