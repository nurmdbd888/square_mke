<?php

namespace App\Console\Commands;

use App\Jobs\SendDueRemindersJob;
use Illuminate\Console\Command;

class SendDueRemindersCommand extends Command
{
    protected $signature = 'sdm:send-due-reminders';

    protected $description = 'Queue payment due reminders for retailers past the threshold';

    public function handle(): int
    {
        SendDueRemindersJob::dispatchSync();

        $this->info('Due reminders processed.');

        return self::SUCCESS;
    }
}
