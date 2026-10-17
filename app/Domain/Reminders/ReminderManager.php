<?php

namespace App\Domain\Reminders;

class ReminderManager
{
    public function channel(): ReminderChannel
    {
        return match ((string) setting('reminder_channel', 'log')) {
            'http_sms' => app(HttpSmsReminderChannel::class),
            'whatsapp' => app(WhatsAppCloudReminderChannel::class),
            default => app(LogReminderChannel::class),
        };
    }
}
