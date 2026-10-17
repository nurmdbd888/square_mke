<?php

namespace Tests\Unit;

use App\Domain\Calendar\FiscalYear;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FiscalYearTest extends TestCase
{
    public function test_acceptance_example(): void
    {
        $year = FiscalYear::containing('2026-10-17');

        $this->assertSame('2026-27', $year->label());
        $this->assertSame('২০২৬-২৭', $year->label('bn'));
        $this->assertSame('2026-07-01', $year->start->toDateString());
        $this->assertSame('2027-06-30', $year->end->toDateString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function boundaries(): array
    {
        return [
            'first day' => ['2026-07-01', '2026-27'],
            'last day' => ['2027-06-30', '2026-27'],
            'day before' => ['2026-06-30', '2025-26'],
            'new year' => ['2027-01-01', '2026-27'],
            'century' => ['2099-12-31', '2099-00'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_boundaries(string $date, string $label): void
    {
        $this->assertSame($label, FiscalYear::containing($date)->label());
    }

    public function test_contains_previous_and_next(): void
    {
        $year = FiscalYear::containing('2026-10-17');

        $this->assertTrue($year->contains('2026-07-01'));
        $this->assertTrue($year->contains('2027-06-30'));
        $this->assertFalse($year->contains('2027-07-01'));
        $this->assertSame('2025-26', $year->previous()->label());
        $this->assertSame('2027-28', $year->next()->label());
    }

    public function test_other_start_months(): void
    {
        $this->assertSame('2026', FiscalYear::containing('2026-10-17', 1)->label());
        $this->assertSame('2026-27', FiscalYear::containing('2027-03-31', 4)->label());
        $this->assertSame('2027-28', FiscalYear::containing('2027-04-01', 4)->label());
    }

    public function test_invalid_start_month_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FiscalYear::containing('2026-10-17', 13);
    }
}
