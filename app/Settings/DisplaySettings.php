<?php

namespace App\Settings;

final class DisplaySettings
{
    public const DATE_FORMATS = [
        'd/m/Y' => 'DD/MM/YYYY',
        'd-m-Y' => 'DD-MM-YYYY',
        'm/d/Y' => 'MM/DD/YYYY',
        'Y-m-d' => 'YYYY-MM-DD',
    ];

    public const PRINT_SIZES = [
        'a5' => 'A5 (148 × 210 mm)',
        'a4' => 'A4 (210 × 297 mm)',
    ];

    public static function register(SettingsRegistry $registry): void
    {
        $registry->define(new SettingDefinition(
            key: 'date_display_format',
            type: SettingDefinition::CHOICE,
            default: 'd/m/Y',
            group: 'regional',
            label: 'Date display format',
            help: 'How dates appear everywhere: lists, printouts, and date fields. Type or pick dates in this format.',
            options: self::DATE_FORMATS,
        ));

        $registry->define(new SettingDefinition(
            key: 'default_print_size',
            type: SettingDefinition::CHOICE,
            default: 'a5',
            group: 'firm',
            label: 'Default print page size',
            help: 'Default paper size for invoices and challans (A5 recommended for delivery).',
            options: self::PRINT_SIZES,
        ));
    }
}
