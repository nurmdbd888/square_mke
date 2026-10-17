<?php

use App\Domain\Money\Formatter;
use App\Settings\SettingsStore;
use Brick\Math\BigNumber;
use Carbon\CarbonInterface;

if (! function_exists('setting')) {
    function setting(string $key, CarbonInterface|string|null $on = null): mixed
    {
        return app(SettingsStore::class)->get($key, $on);
    }
}

if (! function_exists('format_number')) {
    function format_number(BigNumber|int|string $value, int $scale = 2): string
    {
        return Formatter::number($value, $scale);
    }
}

if (! function_exists('format_money')) {
    function format_money(BigNumber|int|string $value): string
    {
        return Formatter::money($value);
    }
}

if (! function_exists('local_digits')) {
    function local_digits(string|int $text): string
    {
        return Formatter::localiseDigits((string) $text);
    }
}

if (! function_exists('format_app_date')) {
    function format_app_date(CarbonInterface|\DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if (is_string($date)) {
            $date = \Illuminate\Support\Carbon::parse($date);
        }

        $format = (string) setting('date_display_format', 'd/m/Y');

        return local_digits($date->format($format));
    }
}

if (! function_exists('local_date')) {
    function local_date(CarbonInterface|\DateTimeInterface|string|null $date): string
    {
        return format_app_date($date);
    }
}
