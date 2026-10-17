<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use App\Models\DailyClosing;
use App\Models\OpeningBalance;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cash book, bank book and day book derived from posted payments.
 * PDC counts only after it is marked cleared (on the cleared date).
 */
final class Books
{
    /**
     * @return array{opening: string, in: string, out: string, closing: string, rows: Collection<int, array<string, mixed>>}
     */
    public function cashBook(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        return $this->book('cash', $from, $to);
    }

    /**
     * @return array{opening: string, in: string, out: string, closing: string, rows: Collection<int, array<string, mixed>>}
     */
    public function bankBook(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        return $this->book('bank', $from, $to);
    }

    /**
     * Day book = cash + bank movements for the range.
     *
     * @return array{cash: array{in: string, out: string}, bank: array{in: string, out: string}, in: string, out: string, rows: Collection<int, array<string, mixed>>}
     */
    public function dayBook(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $cash = $this->cashBook($from, $to);
        $bank = $this->bankBook($from, $to);
        $rows = $cash['rows']->concat($bank['rows'])
            ->sortBy([
                fn ($r) => $r['date'],
                fn ($r) => $r['id'],
            ])
            ->values();

        return [
            'cash' => ['in' => $cash['in'], 'out' => $cash['out']],
            'bank' => ['in' => $bank['in'], 'out' => $bank['out']],
            'in' => Exact::money(Exact::of($cash['in'])->plus(Exact::of($bank['in']))),
            'out' => Exact::money(Exact::of($cash['out'])->plus(Exact::of($bank['out']))),
            'rows' => $rows,
        ];
    }

    /**
     * Opening for a book on a date: last daily closing, else opening balances, else 0.
     */
    public function opening(string $book, CarbonInterface|string $on): string
    {
        $date = Carbon::parse($on)->toDateString();
        $prior = DailyClosing::query()
            ->whereDate('closed_on', '<', $date)
            ->orderByDesc('closed_on')
            ->first();

        if ($prior !== null) {
            return Exact::money($book === 'cash' ? $prior->cash_closing : $prior->bank_closing);
        }

        $kind = $book === 'cash' ? 'cash' : 'bank';
        $sum = OpeningBalance::query()
            ->where('kind', $kind)
            ->whereDate('as_of', '<=', $date)
            ->sum('amount');

        return Exact::money($sum);
    }

    /**
     * @return array{opening: string, in: string, out: string, closing: string, rows: Collection<int, array<string, mixed>>}
     */
    private function book(string $book, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $fromDate = Carbon::parse($from)->toDateString();
        $toDate = Carbon::parse($to)->toDateString();
        $methods = $book === 'cash' ? Payment::CASH_METHODS : Payment::BANK_METHODS;

        $payments = Payment::query()
            ->with(['retailer', 'supplier', 'expenseCategory'])
            ->where('status', Payment::STATUS_POSTED)
            ->whereIn('method', $methods)
            ->get()
            ->filter(function (Payment $payment) use ($fromDate, $toDate) {
                if (! $payment->countsInBooks()) {
                    return false;
                }
                $bookDate = $payment->bookDate();

                return $bookDate !== null && $bookDate >= $fromDate && $bookDate <= $toDate;
            })
            ->sortBy(fn (Payment $p) => [$p->bookDate(), $p->id])
            ->values();

        $in = Exact::of('0');
        $out = Exact::of('0');
        $rows = collect();

        foreach ($payments as $payment) {
            $amount = Exact::of($payment->amount);
            $isIn = $payment->isReceipt();
            if ($isIn) {
                $in = $in->plus($amount);
            } else {
                $out = $out->plus($amount);
            }

            $rows->push([
                'id' => $payment->id,
                'date' => $payment->bookDate(),
                'number' => $payment->number,
                'kind' => $payment->kind,
                'method' => $payment->method,
                'party' => $payment->partyLabel(),
                'in' => $isIn ? Exact::money($amount) : '0.00',
                'out' => $isIn ? '0.00' : Exact::money($amount),
                'book' => $book,
            ]);
        }

        $opening = Exact::of($this->opening($book, $fromDate));
        // Movements before fromDate after last closing adjust opening.
        $prior = $this->netBefore($book, $fromDate);
        $opening = $opening->plus(Exact::of($prior));
        $closing = $opening->plus($in)->minus($out);

        return [
            'opening' => Exact::money($opening),
            'in' => Exact::money($in),
            'out' => Exact::money($out),
            'closing' => Exact::money($closing),
            'rows' => $rows,
        ];
    }

    private function netBefore(string $book, string $fromDate): string
    {
        $priorClosing = DailyClosing::query()
            ->whereDate('closed_on', '<', $fromDate)
            ->orderByDesc('closed_on')
            ->first();
        $after = $priorClosing?->closed_on?->toDateString();

        $methods = $book === 'cash' ? Payment::CASH_METHODS : Payment::BANK_METHODS;
        $in = Exact::of('0');
        $out = Exact::of('0');

        Payment::query()
            ->where('status', Payment::STATUS_POSTED)
            ->whereIn('method', $methods)
            ->get()
            ->each(function (Payment $payment) use ($fromDate, $after, &$in, &$out) {
                if (! $payment->countsInBooks()) {
                    return;
                }
                $bookDate = $payment->bookDate();
                if ($bookDate === null || $bookDate >= $fromDate) {
                    return;
                }
                if ($after !== null && $bookDate <= $after) {
                    return;
                }
                $amount = Exact::of($payment->amount);
                if ($payment->isReceipt()) {
                    $in = $in->plus($amount);
                } else {
                    $out = $out->plus($amount);
                }
            });

        return Exact::money($in->minus($out));
    }
}
