<?php

namespace App\Settings;

/**
 * Weekly holidays and calendar exceptions for business-day reporting.
 */
final class CalendarSettings
{
    public const WEEKDAY_OPTIONS = [
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
        '7' => 'Sunday',
    ];

    public static function register(SettingsRegistry $registry): void
    {
        $registry->define(new SettingDefinition(
            key: 'weekly_holiday_weekdays',
            type: SettingDefinition::WEEKDAYS,
            default: [2],
            group: 'calendar',
            label: 'Weekly holiday weekdays',
            help: 'Regular closed days each week (ISO weekday: 1 = Monday). Default is Tuesday only; Saturday and Sunday are not excluded unless you tick them.',
            options: self::WEEKDAY_OPTIONS,
        ));

        $registry->define(new SettingDefinition(
            key: 'calendar_working_dates',
            type: SettingDefinition::DATE_LIST,
            default: [],
            group: 'calendar',
            label: 'Working days on weekly holidays',
            help: 'One date per line (yyyy-mm-dd). These days count as open even when they fall on a weekly holiday (for example a Tuesday you worked).',
        ));

        $registry->define(new SettingDefinition(
            key: 'calendar_extra_holiday_dates',
            type: SettingDefinition::DATE_LIST,
            default: [],
            group: 'calendar',
            label: 'Extra closed dates',
            help: 'One date per line (yyyy-mm-dd). Closed days that are not weekly holidays (for example a public holiday on a Wednesday).',
        ));
    }
}
