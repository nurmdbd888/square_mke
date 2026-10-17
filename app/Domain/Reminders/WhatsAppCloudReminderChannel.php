<?php

namespace App\Domain\Reminders;

use Illuminate\Support\Facades\Http;

final class WhatsAppCloudReminderChannel implements ReminderChannel
{
    public function send(string $phone, string $message, array $context = []): void
    {
        $token = (string) setting('reminder_whatsapp_token');
        $phoneId = (string) setting('reminder_whatsapp_phone_id');
        if ($token === '' || $phoneId === '') {
            throw new \RuntimeException('WhatsApp Cloud credentials are not configured.');
        }

        $to = preg_replace('/\D/', '', $phone) ?? '';
        Http::withToken($token)
            ->timeout(20)
            ->post("https://graph.facebook.com/v21.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $message],
            ])
            ->throw();
    }
}
