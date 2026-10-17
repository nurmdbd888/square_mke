<?php

namespace App\Domain\Profit;

use App\Domain\Calendar\BusinessCalendar;
use App\Domain\Money\Exact;
use App\Models\Document;
use App\Models\Payment;
use App\Models\StockNote;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * P&L figures from posted sales (and later expenses / losses).
 *
 * profit before expenses = revenue − COGS − free-goods cost + net commission
 */
final class Summary
{
    /**
     * @return array{
     *     revenue: string,
     *     cogs: string,
     *     free_goods: string,
     *     gross: string,
     *     commission_gross: string,
     *     commission_vat: string,
     *     commission_net: string,
     *     profit_before_expenses: string,
     *     expenses: string,
     *     stock_loss: string,
     *     net_profit: string
     * }
     */
    public function between(
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
        bool $businessDaysOnly = false,
    ): array {
        $figures = $this->salesBetween($from, $to, $businessDaysOnly);
        $expenses = $this->expensesBetween($from, $to, $businessDaysOnly);
        $stockLoss = $this->stockLossBetween($from, $to, $businessDaysOnly);
        $net = Exact::of($figures['profit_before_expenses'])
            ->minus(Exact::of($expenses))
            ->minus(Exact::of($stockLoss));

        return [
            ...$figures,
            'expenses' => $expenses,
            'stock_loss' => $stockLoss,
            'net_profit' => Exact::money($net),
        ];
    }

    /**
     * @return array{
     *     revenue: string,
     *     cogs: string,
     *     free_goods: string,
     *     gross: string,
     *     commission_gross: string,
     *     commission_vat: string,
     *     commission_net: string,
     *     profit_before_expenses: string
     * }
     */
    public function salesBetween(
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
        bool $businessDaysOnly = false,
    ): array {
        $query = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED);

        if ($from !== null) {
            $query->whereDate('doc_date', '>=', Carbon::parse($from)->toDateString());
        }
        if ($to !== null) {
            $query->whereDate('doc_date', '<=', Carbon::parse($to)->toDateString());
        }
        $this->applyBusinessDayFilter($query, 'doc_date', $from, $to, $businessDaysOnly);

        $revenue = Exact::of('0');
        $cogs = Exact::of('0');
        $free = Exact::of('0');
        $commGross = Exact::of('0');
        $commVat = Exact::of('0');
        $commNet = Exact::of('0');

        foreach ($query->get() as $doc) {
            $revenue = $revenue->plus(Exact::of($doc->revenue));
            $cogs = $cogs->plus(Exact::of($doc->cogs));
            $free = $free->plus(Exact::of($doc->free_goods_cost));
            $commGross = $commGross->plus(Exact::of($doc->commission_gross));
            $commVat = $commVat->plus(Exact::of($doc->commission_vat));
            $commNet = $commNet->plus(Exact::of($doc->commission_net));
        }

        $gross = $revenue->minus($cogs)->minus($free);
        $profit = $gross->plus($commNet);

        return [
            'revenue' => Exact::money($revenue),
            'cogs' => Exact::money($cogs),
            'free_goods' => Exact::money($free),
            'gross' => Exact::money($gross),
            'commission_gross' => Exact::money($commGross),
            'commission_vat' => Exact::money($commVat),
            'commission_net' => Exact::money($commNet),
            'profit_before_expenses' => Exact::money($profit),
        ];
    }

    public function expensesBetween(
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
        bool $businessDaysOnly = false,
    ): string {
        $query = Payment::query()
            ->where('kind', Payment::KIND_PAYMENT)
            ->where('status', Payment::STATUS_POSTED)
            ->whereNotNull('expense_category_id');

        if ($from !== null) {
            $query->whereDate('payment_date', '>=', Carbon::parse($from)->toDateString());
        }
        if ($to !== null) {
            $query->whereDate('payment_date', '<=', Carbon::parse($to)->toDateString());
        }
        $this->applyBusinessDayFilter($query, 'payment_date', $from, $to, $businessDaysOnly);

        $total = Exact::of('0');
        foreach ($query->get(['amount']) as $payment) {
            $total = $total->plus(Exact::of($payment->amount));
        }

        return Exact::money($total);
    }

    public function stockLossBetween(
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
        bool $businessDaysOnly = false,
    ): string {
        $query = StockNote::query()
            ->where('status', StockNote::STATUS_POSTED)
            ->where('stock_loss_total', '>', 0);

        if ($from !== null) {
            $query->whereDate('doc_date', '>=', Carbon::parse($from)->toDateString());
        }
        if ($to !== null) {
            $query->whereDate('doc_date', '<=', Carbon::parse($to)->toDateString());
        }
        $this->applyBusinessDayFilter($query, 'doc_date', $from, $to, $businessDaysOnly);

        $total = Exact::of('0');
        foreach ($query->get(['stock_loss_total']) as $note) {
            $total = $total->plus(Exact::of($note->stock_loss_total));
        }

        return Exact::money($total);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyBusinessDayFilter($query, string $column, mixed $from, mixed $to, bool $businessDaysOnly): void
    {
        if (! $businessDaysOnly || $from === null || $to === null) {
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
