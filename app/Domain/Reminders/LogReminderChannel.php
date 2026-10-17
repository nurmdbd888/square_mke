<?php

namespace App\Domain\Reminders;

use Illuminate\Support\Facades\Log;

final class LogReminderChannel implements ReminderChannel
{
    public function send(string $phone, string $message, array $context = []): void
    {
        Log::info('reminder.sent', [
            'phone' => $phone,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
