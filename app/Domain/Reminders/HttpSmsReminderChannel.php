<?php

namespace App\Domain\Reminders;

use Illuminate\Support\Facades\Http;

final class HttpSmsReminderChannel implements ReminderChannel
{
    public function send(string $phone, string $message, array $context = []): void
    {
        $url = (string) setting('reminder_http_sms_url');
        if ($url === '') {
            throw new \RuntimeException('reminder_http_sms_url is not configured.');
        }

        Http::timeout(15)->post($url, [
            'phone' => $phone,
            'message' => $message,
            'retailer' => $context['name'] ?? '',
            'balance' => $context['balance'] ?? '',
        ])->throw();
    }
}
