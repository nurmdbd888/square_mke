<?php

namespace App\Domain\Payments;

use App\Domain\Reminders\WhatsAppCloudReminderChannel;
use App\Models\DailyClosing;

final class DailyClosingWhatsAppReport
{
    public function __construct(private readonly WhatsAppCloudReminderChannel $whatsapp) {}

    public function send(DailyClosing $closing): bool
    {
        $recipient = trim((string) setting('day_end_whatsapp_recipient'));
        if ($recipient === '') {
            return false;
        }

        $date = $closing->closed_on->format('d-m-Y');
        $message = implode("\n", [
            "Day End Report - {$date}",
            'Cash: Opening '.number_format((float) $closing->cash_opening, 2).', In '.number_format((float) $closing->cash_in, 2).', Out '.number_format((float) $closing->cash_out, 2).', Closing '.number_format((float) $closing->cash_closing, 2),
            'Bank: Opening '.number_format((float) $closing->bank_opening, 2).', In '.number_format((float) $closing->bank_in, 2).', Out '.number_format((float) $closing->bank_out, 2).', Closing '.number_format((float) $closing->bank_closing, 2),
        ]);

        $this->whatsapp->send($recipient, $message);

        return true;
    }
}
