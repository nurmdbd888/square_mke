<?php

namespace App\Jobs;

use App\Domain\Reminders\DueReminderService;
use App\Domain\Reminders\ReminderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDueRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(DueReminderService $due, ReminderManager $manager): void
    {
        if (! (bool) setting('reminder_enabled', false)) {
            return;
        }

        $channel = $manager->channel();
        $dueDays = (int) setting('reminder_due_days', 30);

        foreach ($due->retailersPastDue($dueDays) as $row) {
            $message = $due->buildMessage($row['retailer'], $row['balance'], $row['oldest_days']);
            $channel->send($row['phone'], $message, [
                'name' => $row['retailer']->name,
                'phone' => $row['phone'],
                'balance' => $row['balance'],
                'oldest_days' => $row['oldest_days'],
            ]);
        }
    }
}
