<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\Payments\SaleCollectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\Access\PaymentAccess;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CollectorController extends Controller
{
    public function __construct(private readonly SaleCollectionStatus $collection) {}

    public function index(Request $request): View
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'due', 'partial', 'ready'])],
            'overdue' => ['nullable', Rule::in(['0', '1', 'true', 'false', 'on'])],
        ]);

        $status = $filters['status'] ?? 'all';
        $overdueOnly = in_array((string) ($filters['overdue'] ?? ''), ['1', 'true', 'on'], true);
        $q = $filters['q'] ?? null;

        $pending = collect();
        $summary = [
            'receipt_count' => 0,
            'receipt_total' => '0.00',
            'confirmed_today' => 0,
            'overdue_count' => 0,
            'partial_open' => 0,
            'ready_count' => 0,
        ];
        $warning = null;
        $dueDays = 30;

        try {
            $dueDays = $this->collection->dueDays();
            $summary = $this->collection->todaySummary($request->user());
            $pending = $this->collection->pendingForCollector([
                'limit' => 150,
                'status' => $status === 'all' ? null : $status,
                'overdue' => $overdueOnly,
                'q' => $q,
            ]);
        } catch (Throwable $e) {
            report($e);
            $warning = __('Run migrations on the Maintenance page first.');
        }

        return view('collector.index', [
            'pending' => $pending instanceof Collection ? $pending : collect($pending),
            'summary' => $summary,
            'todayTotal' => $summary['receipt_total'],
            'collection' => $this->collection,
            'warning' => $warning,
            'dueDays' => $dueDays,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'overdue' => $overdueOnly,
            ],
        ]);
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
}
