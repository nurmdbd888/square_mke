<?php

namespace App\Domain\Reports;

use App\Models\MonthLock;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Month-end lock: blocks posting and edits in locked calendar months.
 */
final class MonthLockService
{
    public function isLocked(CarbonInterface|string $date): bool
    {
        $d = Carbon::parse($date);

        return MonthLock::query()
            ->where('year', $d->year)
            ->where('month', $d->month)
            ->whereNull('unlocked_at')
            ->exists();
    }

    public function assertWritable(CarbonInterface|string $date): void
    {
        if (! $this->isLocked($date)) {
            return;
        }

        $d = Carbon::parse($date);

        throw ValidationException::withMessages([
            'doc_date' => __('This month is locked (:month). Only the owner can unlock it.', [
                'month' => local_digits($d->format('m-Y')),
            ]),
        ]);
    }

    public function lock(int $year, int $month, User $by): MonthLock
    {
        $lock = MonthLock::query()->firstOrNew(['year' => $year, 'month' => $month]);
        $wasActive = $lock->exists && $lock->isActive();

        $lock->fill([
            'locked_at' => now(),
            'locked_by' => $by->id,
            'unlocked_at' => null,
            'unlocked_by' => null,
        ]);
        $lock->save();

        if (! $wasActive) {
            AuditLogger::log('month_lock.locked', $lock, null, [
                'year' => $year,
                'month' => $month,
            ], $by);
        }

        return $lock->fresh(['lockedByUser']);
    }

    public function unlock(int $year, int $month, User $by): MonthLock
    {
        if (! $by->isOwner()) {
            abort(403);
        }

        $lock = MonthLock::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereNull('unlocked_at')
            ->firstOrFail();

        $lock->forceFill([
            'unlocked_at' => now(),
            'unlocked_by' => $by->id,
        ])->save();

        AuditLogger::log('month_lock.unlocked', $lock, ['unlocked_at' => null], [
            'year' => $year,
            'month' => $month,
            'unlocked_at' => $lock->unlocked_at?->toIso8601String(),
        ], $by);

        return $lock->fresh(['unlockedByUser']);
    }

    /**
     * @return Collection<int, MonthLock>
     */
    public function activeLocks(): Collection
    {
        return MonthLock::query()
            ->whereNull('unlocked_at')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->with(['lockedByUser'])
            ->get();
    }
}
