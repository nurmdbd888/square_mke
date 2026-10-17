<?php

namespace Tests\Unit;

use App\Domain\Calendar\BusinessCalendar;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_tuesday_is_weekly_holiday_by_default(): void
    {
        $calendar = app(BusinessCalendar::class);

        $this->assertFalse($calendar->isBusinessDay('2026-10-20')); // Tuesday
        $this->assertTrue($calendar->isBusinessDay('2026-10-21')); // Wednesday
        $this->assertTrue($calendar->isBusinessDay('2026-10-18')); // Sunday — not auto-closed
    }

    public function test_working_exception_opens_a_weekly_holiday(): void
    {
        app(SettingsStore::class)->set([
            'calendar_working_dates' => ['2026-10-20'],
        ], $this->owner());

        $calendar = app(BusinessCalendar::class);

        $this->assertTrue($calendar->isBusinessDay('2026-10-20'));
    }

    public function test_extra_holiday_closes_an_open_weekday(): void
    {
        app(SettingsStore::class)->set([
            'calendar_extra_holiday_dates' => ['2026-10-21'],
        ], $this->owner());

        $calendar = app(BusinessCalendar::class);

        $this->assertFalse($calendar->isBusinessDay('2026-10-21'));
    }

    public function test_business_days_between_skips_weekly_holidays(): void
    {
        $calendar = app(BusinessCalendar::class);
        $days = $calendar->businessDaysBetween('2026-10-19', '2026-10-22');

        $this->assertSame(['2026-10-19', '2026-10-21', '2026-10-22'], $days);
    }
}
