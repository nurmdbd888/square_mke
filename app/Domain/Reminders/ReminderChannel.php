<?php

namespace App\Domain\Reminders;

interface ReminderChannel
{
    /**
     * @param  array{name: string, phone: string, balance: string, oldest_days: int}  $context
     */
    public function send(string $phone, string $message, array $context = []): void;
}
