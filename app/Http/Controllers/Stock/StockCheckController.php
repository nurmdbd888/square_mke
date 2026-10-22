<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\StockCheck;
use App\Models\StockCheckLine;
use App\Models\StockLedgerEntry;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockCheckController extends Controller
{
    public function index(): View
    {
        $recent = StockCheck::query()
            ->with('creator')
            ->withCount([
                'lines',
                'lines as available_count' => fn ($q) => $q->where('in_store', StockCheckLine::IN_STORE_AVAILABLE),
                'lines as not_available_count' => fn ($q) => $q->where('in_store', StockCheckLine::IN_STORE_NOT_AVAILABLE),
            ])
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('stock.checks.index', [
            'recent' => $recent,
        ]);
    }

    public function show(StockCheck $stockCheck): View
    {
        $stockCheck->load(['creator', 'lines.product']);

        $available = $stockCheck->lines->where('in_store', StockCheckLine::IN_STORE_AVAILABLE)->count();
        $notAvailable = $stockCheck->lines->where('in_store', StockCheckLine::IN_STORE_NOT_AVAILABLE)->count();

        return view('stock.checks.show', [
            'check' => $stockCheck,
            'availableCount' => $available,
            'notAvailableCount' => $notAvailable,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.in_store' => ['required', 'in:available,not_available'],
        ]);

        $productIds = collect($data['items'])->pluck('product_id')->all();
        $balances = StockLedgerEntry::query()
            ->select('product_id')
            ->selectRaw('MAX(id) as last_id')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->pluck('last_id', 'product_id');

        $latest = StockLedgerEntry::query()
            ->whereIn('id', $balances->values())
            ->get()
            ->keyBy('product_id');

        $check = DB::transaction(function () use ($data, $request, $latest) {
            $check = StockCheck::query()->create([
                'created_by' => $request->user()->id,
                'confirmed_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $row = $latest->get($item['product_id']);
                StockCheckLine::query()->create([
                    'stock_check_id' => $check->id,
                    'product_id' => $item['product_id'],
                    'in_store' => $item['in_store'],
                    'on_hand_qty' => $row?->running_qty ?? 0,
                    'on_hand_value' => $row?->running_value ?? 0,
                ]);
            }

            return $check;
        });

        AuditLogger::log('stock_check.confirmed', $check, null, [
            'lines' => $check->lines()->count(),
        ]);

        return redirect()
            ->route('stock.index', ['export_check' => $check->id])
            ->with('status', __('Stock check confirmed. You can export the report.'));
    }

    public function export(StockCheck $stockCheck): StreamedResponse
    {
        $stockCheck->load(['lines.product']);

        $filename = 'stock-check-'.$stockCheck->id.'-'.$stockCheck->confirmed_at->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($stockCheck) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Product', 'On hand', 'Stock value', 'IN STORE']);

            foreach ($stockCheck->lines->sortBy(fn (StockCheckLine $line) => $line->product?->name_en ?? '') as $line) {
                fputcsv($out, [
                    $line->product?->code ?? '',
                    $line->product?->name_en ?? '',
                    (string) $line->on_hand_qty,
                    (string) $line->on_hand_value,
                    $line->in_store === StockCheckLine::IN_STORE_AVAILABLE ? 'Available' : 'Not Available',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
