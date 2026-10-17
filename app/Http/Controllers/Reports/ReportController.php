<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Calendar\FiscalYear;
use App\Domain\Money\Exact;
use App\Domain\Profit\Summary;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly Summary $summary) {}

    public function profitAndLoss(Request $request): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->endOfMonth()->toDateString();
        $figures = $this->summary->between($from, $to);

        $ledgerRevenue = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED)
            ->whereBetween('doc_date', [$from, $to])
            ->get(['revenue'])
            ->reduce(fn ($carry, $doc) => $carry->plus(Exact::of($doc->revenue)), Exact::of('0'));

        return view('reports.pl', [
            'from' => $from,
            'to' => $to,
            'figures' => $figures,
            'ledgerRevenue' => Exact::money($ledgerRevenue),
            'fiscalYear' => FiscalYear::containing(now(), (int) setting('fiscal_year_start_month')),
        ]);
    }
}
