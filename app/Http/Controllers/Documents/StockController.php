<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Stock\StockAlerts;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockAlerts $alerts) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'checking' => ['nullable', 'boolean'],
            'export_check' => ['nullable', 'integer', 'exists:stock_checks,id'],
        ]);

        $canStockCheck = $request->user()->can('stock.compare');
        $checking = $canStockCheck && ($request->boolean('checking') || $request->boolean('stock_check'));

        $query = Product::query()
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('name_en', 'like', $like)->orWhere('code', 'like', $like)->orWhere('brand', 'like', $like));
            })
            ->orderBy('name_en');

        // Stock check needs the full list so marks survive without pagination.
        $products = $checking
            ? $query->get()
            : $query->paginate(50)->withQueryString();

        $balances = StockLedgerEntry::query()
            ->select('product_id')
            ->selectRaw('MAX(id) as last_id')
            ->whereIn('product_id', $products->pluck('id'))
            ->groupBy('product_id')
            ->pluck('last_id', 'product_id');

        $latest = StockLedgerEntry::query()
            ->whereIn('id', $balances->values())
            ->get()
            ->keyBy('product_id');

        return view('stock.index', [
            'products' => $products,
            'latest' => $latest,
            'filters' => $filters,
            'pending' => (bool) cache()->get('sdm.costing_pending'),
            'lowStock' => $this->alerts->lowStock(),
            'nearExpiry' => $this->alerts->nearExpiry(),
            'canStockCheck' => $canStockCheck,
            'checking' => $checking,
            'exportCheckId' => $canStockCheck ? ($filters['export_check'] ?? null) : null,
        ]);
    }

    public function show(Product $product): View
    {
        $entries = StockLedgerEntry::query()
            ->where('product_id', $product->id)
            ->with('document')
            ->orderBy('doc_date')
            ->orderBy('rank')
            ->orderBy('posted_at')
            ->orderBy('id')
            ->paginate(100);

        return view('stock.show', [
            'product' => $product,
            'entries' => $entries,
            'pending' => (bool) cache()->get('sdm.costing_pending'),
        ]);
    }
}
