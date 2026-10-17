<?php

namespace App\Domain\Calendar;

use App\Domain\Money\Formatter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Bangladesh fiscal year: 1 July to 30 June by default, labelled "2026-27".
 */
final class FiscalYear
{
    private function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function containing(CarbonInterface|string $date, int $startMonth = 7): self
    {
        if ($startMonth < 1 || $startMonth > 12) {
            throw new InvalidArgumentException('Fiscal year start month must be 1 to 12.');
        }

        $day = CarbonImmutable::parse($date instanceof CarbonInterface ? $date->toDateString() : $date)->startOfDay();
        $startYear = $day->month >= $startMonth ? $day->year : $day->year - 1;
        $start = CarbonImmutable::create($startYear, $startMonth, 1)->startOfDay();

        return new self($start, $start->addYear()->subDay()->endOfDay());
    }

    /**
     * "2026-27"; a fiscal year starting in January is a plain "2026".
     */
    public function label(?string $locale = null): string
    {
        $label = $this->start->year === $this->end->year
            ? (string) $this->start->year
            : $this->start->year.'-'.substr((string) $this->end->year, -2);

        return Formatter::localiseDigits($label, $locale ?? 'en');
    }

    public function contains(CarbonInterface|string $date): bool
    {
        $day = CarbonImmutable::parse($date instanceof CarbonInterface ? $date->toDateString() : $date);

        return $day->betweenIncluded($this->start, $this->end);
    }

    public function previous(): self
    {
        return self::containing($this->start->subDay(), $this->start->month);
    }

    public function next(): self
    {
        return self::containing($this->end->addDay(), $this->start->month);
    }
}
