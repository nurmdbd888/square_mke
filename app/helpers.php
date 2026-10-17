<?php

use App\Domain\Money\Formatter;
use App\Settings\DisplaySettings;
use App\Settings\SettingsRegistry;
use App\Settings\SettingsStore;
use Brick\Math\BigNumber;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, CarbonInterface|string|null $on = null): mixed
    {
        try {
            if (! app(SettingsRegistry::class)->has($key)) {
                return null;
            }

            return app(SettingsStore::class)->get($key, $on);
        } catch (Throwable) {
            return null;
        }
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

if (! function_exists('app_date_format')) {
    /**
     * Active PHP date format from settings (e.g. d/m/Y). Always one of DisplaySettings::DATE_FORMATS.
     */
    function app_date_format(): string
    {
        $format = setting('date_display_format');
        if (! is_string($format) || ! array_key_exists($format, DisplaySettings::DATE_FORMATS)) {
            return 'd/m/Y';
        }

        return $format;
    }
}

if (! function_exists('app_date_placeholder')) {
    /** Lowercase placeholder matching the active display format (e.g. dd/mm/yyyy). */
    function app_date_placeholder(): string
    {
        return match (app_date_format()) {
            'd-m-Y' => 'dd-mm-yyyy',
            'm/d/Y' => 'mm/dd/yyyy',
            'Y-m-d' => 'yyyy-mm-dd',
            default => 'dd/mm/yyyy',
        };
    }
}

if (! function_exists('format_app_date')) {
    function format_app_date(CarbonInterface|DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return local_digits($date->format(app_date_format()));
    }
}

if (! function_exists('parse_app_date')) {
    /**
     * Parse a user-entered date (display format or Y-m-d) into Y-m-d, or null if invalid.
     */
    function parse_app_date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = Formatter::toAsciiDigits(trim($value));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            try {
                $dt = Carbon::createFromFormat('Y-m-d', $value);

                return $dt && $dt->format('Y-m-d') === $value ? $value : null;
            } catch (Throwable) {
                return null;
            }
        }

        $format = app_date_format();

        try {
            $dt = Carbon::createFromFormat('!'.$format, $value);
            if ($dt === false) {
                return null;
            }

            $errors = Carbon::getLastErrors();
            if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
                return null;
            }

            return $dt->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}

if (! function_exists('local_date')) {
    function local_date(CarbonInterface|DateTimeInterface|string|null $date): string
    {
        return format_app_date($date);
    }
}
