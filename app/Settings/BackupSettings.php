<?php

namespace App\Settings;

final class BackupSettings
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->define(new SettingDefinition(
            key: 'backup_retention_days',
            type: SettingDefinition::INTEGER,
            default: 14,
            group: 'security',
            label: 'Backup retention (days)',
            help: 'Local backups and Google Drive copies older than this are deleted automatically after each nightly backup.',
            rules: ['between:1,90'],
        ));
    }
}
