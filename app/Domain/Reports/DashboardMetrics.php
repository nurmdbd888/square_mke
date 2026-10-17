<?php

namespace App\Domain\Reports;

use App\Domain\Calendar\BusinessCalendar;
use App\Domain\Money\Exact;
use App\Domain\Payments\PartyBalance;
use App\Domain\Profit\Summary;
use App\Models\Document;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard KPIs for today and the current calendar month.
 */
final class DashboardMetrics
{
    public function __construct(
        private readonly Summary $summary,
        private readonly PartyBalance $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?Carbon $asOf = null, bool $businessDaysOnly = false): array
    {
        $asOf ??= now();
        $today = $asOf->toDateString();
        $monthStart = $asOf->copy()->startOfMonth()->toDateString();
        $monthEnd = $asOf->copy()->endOfMonth()->toDateString();

        $todayPl = $this->summary->between($today, $today, $businessDaysOnly);
        $monthPl = $this->summary->between($monthStart, $monthEnd, $businessDaysOnly);

        $todaySalesQuery = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED)
            ->whereDate('doc_date', $today);
        $this->applyBusinessDayFilter($todaySalesQuery, 'doc_date', $today, $today, $businessDaysOnly);
        $todaySales = $todaySalesQuery->sum('net_total');

        $monthSalesQuery = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED)
            ->whereBetween('doc_date', [$monthStart, $monthEnd]);
        $this->applyBusinessDayFilter($monthSalesQuery, 'doc_date', $monthStart, $monthEnd, $businessDaysOnly);
        $monthSales = $monthSalesQuery->sum('net_total');

        $revenue = Exact::of($monthPl['revenue']);
        $gross = Exact::of($monthPl['gross']);
        $marginPct = $revenue->isZero()
            ? '0.00'
            : Exact::money($gross->multipliedBy('100')->dividedBy($revenue, 2));

        return [
            'today_sales' => Exact::money(Exact::of($todaySales)),
            'month_sales' => Exact::money(Exact::of($monthSales)),
            'month_gross' => $monthPl['gross'],
            'month_gross_margin_pct' => $marginPct,
            'month_net_profit' => $monthPl['net_profit'],
            'stock_value' => $this->stockValue(),
            'receivables_due' => $this->receivablesDue($today),
            'top_products' => $this->topProducts($monthStart, $monthEnd, businessDaysOnly: $businessDaysOnly),
            'top_retailers' => $this->topRetailers($monthStart, $monthEnd, businessDaysOnly: $businessDaysOnly),
            'today_profit_before_expenses' => $todayPl['profit_before_expenses'],
        ];
    }

    private function stockValue(): string
    {
        $lastIds = StockLedgerEntry::query()
            ->select('product_id')
            ->selectRaw('MAX(id) as last_id')
            ->groupBy('product_id')
            ->pluck('last_id');

        $total = Exact::of('0');
        foreach (StockLedgerEntry::query()->whereIn('id', $lastIds)->get(['running_value']) as $entry) {
            $total = $total->plus(Exact::of($entry->running_value));
        }

        return Exact::money($total);
    }

    private function receivablesDue(string $asOf): string
    {
        $total = Exact::of('0');
        foreach (Retailer::query()->pluck('id') as $retailerId) {
            $balance = $this->balances->retailer((int) $retailerId, $asOf);
            $total = $total->plus(Exact::of($balance['balance']));
        }

        return Exact::money($total);
    }

    /**
     * @return list<array{product: Product, revenue: string}>
     */
    private function topProducts(string $from, string $to, int $limit = 5, bool $businessDaysOnly = false): array
    {
        $query = DB::table('document_lines')
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->join('products', 'products.id', '=', 'document_lines.product_id')
            ->where('documents.type', Document::TYPE_SALE)
            ->where('documents.status', Document::STATUS_POSTED)
            ->whereBetween('documents.doc_date', [$from, $to]);
        $this->applyBusinessDayFilter($query, 'documents.doc_date', $from, $to, $businessDaysOnly);
        $rows = $query
            ->where('document_lines.kind', '!=', 'combo_bonus') // DocumentLine::KIND_COMBO_BONUS
            ->groupBy('document_lines.product_id', 'products.id', 'products.name_en', 'products.code')
            ->orderByDesc(DB::raw('SUM(document_lines.line_value)'))
            ->limit($limit)
            ->get([
                'products.id',
                'products.code',
                'products.name_en',
                DB::raw('SUM(document_lines.line_value) as revenue'),
            ]);

        return $rows->map(fn ($row) => [
            'product' => Product::query()->find($row->id),
            'revenue' => Exact::money(Exact::of($row->revenue)),
        ])->all();
    }

    /**
     * @return list<array{retailer: Retailer, revenue: string}>
     */
    private function topRetailers(string $from, string $to, int $limit = 5, bool $businessDaysOnly = false): array
    {
        $query = DB::table('documents')
            ->join('retailers', 'retailers.id', '=', 'documents.retailer_id')
            ->where('documents.type', Document::TYPE_SALE)
            ->where('documents.status', Document::STATUS_POSTED)
            ->whereBetween('documents.doc_date', [$from, $to]);
        $this->applyBusinessDayFilter($query, 'documents.doc_date', $from, $to, $businessDaysOnly);
        $rows = $query
            ->groupBy('retailers.id', 'retailers.name', 'retailers.code')
            ->orderByDesc(DB::raw('SUM(documents.revenue)'))
            ->limit($limit)
            ->get([
                'retailers.id',
                'retailers.name',
                DB::raw('SUM(documents.revenue) as revenue'),
            ]);

        return $rows->map(fn ($row) => [
            'retailer' => Retailer::query()->find($row->id),
            'revenue' => Exact::money(Exact::of($row->revenue)),
        ])->all();
    }

    /**
     * @param  Builder<Model>|\Illuminate\Database\Query\Builder  $query
     */
    private function applyBusinessDayFilter($query, string $column, string $from, string $to, bool $businessDaysOnly): void
    {
        if (! $businessDaysOnly) {
            return;
        }

        $days = app(BusinessCalendar::class)->businessDaysBetween($from, $to);
        if ($days === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($column, $days);
    }
}
