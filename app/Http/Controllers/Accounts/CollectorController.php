<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\Payments\SaleCollectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment;
use App\Support\Access\PaymentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CollectorController extends Controller
{
    public function __construct(private readonly SaleCollectionStatus $collection) {}

    public function index(Request $request): View
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);

        $pending = $this->collection->pendingForCollector(150);
        $todayTotal = Payment::query()
            ->where('kind', Payment::KIND_RECEIPT)
            ->where('status', Payment::STATUS_POSTED)
            ->whereDate('payment_date', Carbon::today())
            ->when(! PaymentAccess::canManage($request->user()), fn ($q) => $q->where('created_by', $request->user()->id))
            ->sum('amount');

        return view('collector.index', [
            'pending' => $pending,
            'todayTotal' => $todayTotal,
            'collection' => $this->collection,
        ]);
    }

    public function confirm(Request $request, Document $document): RedirectResponse
    {
        abort_unless(PaymentAccess::canCollectReceipts($request->user()), 403);
        abort_unless($document->isSale(), 404);

        $this->collection->confirmCollected($document, $request->user());

        return redirect()->back()->with('status', __('Invoice :number marked as fully collected.', ['number' => $document->number]));
    }
}
