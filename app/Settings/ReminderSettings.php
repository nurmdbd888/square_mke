<?php

namespace App\Settings;

final class ReminderSettings
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->define(new SettingDefinition(
            key: 'reminder_enabled',
            type: SettingDefinition::BOOLEAN,
            default: false,
            group: 'reminders',
            label: 'Send due payment reminders',
            help: 'When on, the daily schedule queues SMS or WhatsApp reminders for overdue retailers.',
        ));

        $registry->define(new SettingDefinition(
            key: 'reminder_channel',
            type: SettingDefinition::CHOICE,
            default: 'log',
            group: 'reminders',
            label: 'Reminder channel',
            options: [
                'log' => 'Log only (testing)',
                'http_sms' => 'HTTP SMS gateway',
                'whatsapp' => 'WhatsApp Cloud API',
            ],
        ));

        $registry->define(new SettingDefinition(
            key: 'reminder_due_days',
            type: SettingDefinition::INTEGER,
            default: 30,
            group: 'reminders',
            label: 'Due after days',
            help: 'Remind when the oldest open invoice is older than this many days.',
            rules: ['between:1,365'],
        ));

        $registry->define(new SettingDefinition(
            key: 'reminder_http_sms_url',
            type: SettingDefinition::STRING,
            default: '',
            group: 'reminders',
            label: 'HTTP SMS endpoint URL',
            help: 'POST JSON with phone, message, retailer and balance.',
            rules: ['nullable', 'max:500'],
        ));

        $registry->define(new SettingDefinition(
            key: 'reminder_whatsapp_token',
            type: SettingDefinition::STRING,
            default: '',
            group: 'reminders',
            label: 'WhatsApp Cloud access token',
            rules: ['nullable', 'max:500'],
        ));

        $registry->define(new SettingDefinition(
            key: 'reminder_whatsapp_phone_id',
            type: SettingDefinition::STRING,
            default: '',
            group: 'reminders',
            label: 'WhatsApp phone number ID',
            rules: ['nullable', 'max:40'],
        ));
    }
}
