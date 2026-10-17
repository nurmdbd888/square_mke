<?php

namespace App\Domain\Calendar;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Weekly holidays and one-off exceptions for business-day reporting.
 *
 * ISO weekday: 1 = Monday … 7 = Sunday (matches Carbon::dayOfWeekIso()).
 */
final class BusinessCalendar
{
    /**
     * @return list<int>
     */
    public function weeklyHolidayWeekdays(): array
    {
        $raw = setting('weekly_holiday_weekdays');
        $days = array_map('intval', (array) $raw);
        $days = array_values(array_unique(array_filter($days, fn (int $d) => $d >= 1 && $d <= 7)));
        sort($days);

        return $days;
    }

    /**
     * @return list<string>
     */
    public function workingExceptionDates(): array
    {
        return $this->normalizeDates((array) setting('calendar_working_dates'));
    }

    /**
     * @return list<string>
     */
    public function extraHolidayDates(): array
    {
        return $this->normalizeDates((array) setting('calendar_extra_holiday_dates'));
    }

    public function isBusinessDay(CarbonInterface|string $date): bool
    {
        $day = Carbon::parse($date)->toDateString();

        if (in_array($day, $this->workingExceptionDates(), true)) {
            return true;
        }

        if (in_array($day, $this->extraHolidayDates(), true)) {
            return false;
        }

        $iso = (int) Carbon::parse($day)->format('N');

        return ! in_array($iso, $this->weeklyHolidayWeekdays(), true);
    }

    /**
     * @return list<string> Y-m-d dates inclusive
     */
    public function businessDaysBetween(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        if ($start->gt($end)) {
            return [];
        }

        $out = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($this->isBusinessDay($cursor)) {
                $out[] = $cursor->toDateString();
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<string>
     */
    private function normalizeDates(array $raw): array
    {
        $dates = [];
        foreach ($raw as $item) {
            if (! is_string($item) || $item === '') {
                continue;
            }
            try {
                $dates[] = Carbon::parse($item)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }
        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }
}
