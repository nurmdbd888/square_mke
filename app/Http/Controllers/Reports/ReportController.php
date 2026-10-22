<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Calendar\BusinessCalendar;
use App\Domain\Calendar\FiscalYear;
use App\Domain\Money\Exact;
use App\Domain\Profit\Summary;
use App\Domain\Reports\BrandSalesSummary;
use App\Domain\Reports\InvoiceAdjustmentsSummary;
use App\Domain\Reports\PartyLedger;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Retailer;
use App\Models\Supplier;
use App\Support\Http\BusinessDaysFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly Summary $summary,
        private readonly PartyLedger $partyLedger,
        private readonly BrandSalesSummary $brandSales,
        private readonly InvoiceAdjustmentsSummary $adjustments,
    ) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function profitAndLoss(Request $request): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'business_days' => ['nullable', 'boolean'],
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->endOfMonth()->toDateString();
        $businessDaysOnly = BusinessDaysFilter::fromRequest($request);
        $figures = $this->summary->between($from, $to, $businessDaysOnly);

        $ledgerQuery = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED)
            ->whereBetween('doc_date', [$from, $to]);
        if ($businessDaysOnly) {
            $days = app(BusinessCalendar::class)->businessDaysBetween($from, $to);
            $ledgerQuery->whereIn('doc_date', $days === [] ? ['1900-01-01'] : $days);
        }
        $ledgerRevenue = $ledgerQuery->get(['revenue'])
            ->reduce(fn ($carry, $doc) => $carry->plus(Exact::of($doc->revenue)), Exact::of('0'));

        return view('reports.pl', [
            'from' => $from,
            'to' => $to,
            'figures' => $figures,
            'ledgerRevenue' => Exact::money($ledgerRevenue),
            'fiscalYear' => FiscalYear::containing(now(), (int) setting('fiscal_year_start_month')),
            'businessDaysOnly' => $businessDaysOnly,
        ]);
    }

    public function partyLedger(Request $request): View
    {
        $data = $request->validate([
            'party_id' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $party = 'retailer';
        $partyId = null;
        $raw = (string) ($data['party_id'] ?? '');
        if (preg_match('/^([rs])-(\d+)$/', $raw, $m)) {
            $party = $m[1] === 's' ? 'supplier' : 'retailer';
            $partyId = (int) $m[2];
        }

        $ledger = null;
        $selected = null;
        if ($partyId) {
            if ($party === 'retailer') {
                $selected = Retailer::query()->findOrFail($partyId);
            } else {
                $selected = Supplier::query()->findOrFail($partyId);
            }
            $ledger = $this->partyLedger->between($party, $partyId, $from, $to);
        }

        return view('reports.party-ledger', [
            'party' => $party,
            'partyId' => $partyId,
            'selected' => $selected,
            'from' => $from,
            'to' => $to,
            'ledger' => $ledger,
            'retailers' => Retailer::query()->orderBy('name')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function brandSales(Request $request): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        return view('reports.brand-sales', [
            'from' => $from,
            'to' => $to,
            'rows' => $this->brandSales->between($from, $to),
        ]);
    }

    public function invoiceAdjustments(Request $request): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        return view('reports.invoice-adjustments', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->adjustments->between($from, $to),
        ]);
    }
}
