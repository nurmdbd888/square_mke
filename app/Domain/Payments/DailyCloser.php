<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use App\Models\DailyClosing;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a day and carries cash/bank balances forward.
 */
final class DailyCloser
{
    public function __construct(private readonly Books $books) {}

    public function close(string $date, ?User $by = null, ?string $notes = null): DailyClosing
    {
        $day = Carbon::parse($date)->toDateString();

        if (DailyClosing::query()->whereDate('closed_on', $day)->exists()) {
            throw ValidationException::withMessages(['closed_on' => __('That day is already closed.')]);
        }

        return DB::transaction(function () use ($day, $by, $notes) {
            $cash = $this->books->cashBook($day, $day);
            $bank = $this->books->bankBook($day, $day);

            $closing = DailyClosing::query()->create([
                'closed_on' => $day,
                'cash_opening' => $cash['opening'],
                'cash_in' => $cash['in'],
                'cash_out' => $cash['out'],
                'cash_closing' => $cash['closing'],
                'bank_opening' => $bank['opening'],
                'bank_in' => $bank['in'],
                'bank_out' => $bank['out'],
                'bank_closing' => $bank['closing'],
                'notes' => $notes,
                'closed_by' => $by?->id,
            ]);

            AuditLogger::log('daily_closing.created', $closing, null, [
                'closed_on' => $day,
                'cash_closing' => Exact::money($closing->cash_closing),
                'bank_closing' => Exact::money($closing->bank_closing),
            ], $by);

            return $closing;
        });
    }
}
