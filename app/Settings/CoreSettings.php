<?php

namespace App\Settings;

use App\Support\Access\PermissionRegistry;

/**
 * Settings used from M1. Business-rule settings (VAT, SD, commission, ...)
 * are registered by the milestones that read them.
 */
final class CoreSettings
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->define(new SettingDefinition(
            key: 'firm_name',
            type: SettingDefinition::STRING,
            default: fn () => (string) config('sdm.firm_name'),
            group: 'firm',
            label: 'Firm name',
            help: 'Printed on every page, invoice, challan and report.',
            rules: ['max:120'],
        ));

        $registry->define(new SettingDefinition(
            key: 'distributor_code',
            type: SettingDefinition::STRING,
            default: fn () => (string) config('sdm.distributor_code'),
            group: 'firm',
            label: 'Square distributor code',
            rules: ['max:20'],
        ));

        $registry->define(new SettingDefinition(
            key: 'default_locale',
            type: SettingDefinition::CHOICE,
            default: fn () => (string) config('app.locale', 'en'),
            group: 'regional',
            label: 'Default language',
            help: 'Used on the login page and for users who have not chosen a language.',
            options: config('sdm.supported_locales'),
        ));

        $registry->define(new SettingDefinition(
            key: 'fiscal_year_start_month',
            type: SettingDefinition::INTEGER,
            default: 7,
            group: 'regional',
            label: 'Fiscal year starts in month',
            help: '7 = July (Bangladesh fiscal year July to June).',
            rules: ['between:1,12'],
        ));

        $registry->define(new SettingDefinition(
            key: 'require_2fa_roles',
            type: SettingDefinition::ROLES,
            default: [],
            group: 'security',
            label: 'Require two-factor sign-in for roles',
            help: 'Users with these roles must set up an authenticator app before they can use the app.',
            options: array_combine(PermissionRegistry::ROLES, PermissionRegistry::ROLES),
        ));
    }
}
