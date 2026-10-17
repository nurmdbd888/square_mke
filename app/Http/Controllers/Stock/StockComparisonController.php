<?php

namespace App\Http\Controllers\Stock;

use App\Domain\Stock\StockComparisonImporter;
use App\Http\Controllers\Controller;
use App\Models\StockComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockComparisonController extends Controller
{
    public function __construct(private readonly StockComparisonImporter $importer) {}

    public function index(): View
    {
        $recent = StockComparison::query()
            ->withCount('lines')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('stock.comparison.index', [
            'recent' => $recent,
            'canCompare' => request()->user()->can('stock.compare'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $this->importer->import($request->file('file'), $request->user());

        return redirect()->route('stock.comparison.show', $result['comparison'])
            ->with('status', __('Comparison uploaded with :count rows.', ['count' => $result['rows']]));
    }

    public function show(StockComparison $comparison): View
    {
        $comparison->load(['lines.product']);

        return view('stock.comparison.show', [
            'comparison' => $comparison,
        ]);
    }
}
