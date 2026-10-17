<?php

namespace App\Console\Commands;

use App\Models\SmokeHeartbeat;
use Illuminate\Console\Command;
use Throwable;

class RecordHeartbeat extends Command
{
    protected $signature = 'sdm:heartbeat {--source=cron : Who triggered the heartbeat}';

    protected $description = 'Write a cron heartbeat row and prune old ones';

    public function handle(): int
    {
        try {
            SmokeHeartbeat::create([
                'ran_at' => now(),
                'source' => substr((string) $this->option('source'), 0, 20),
                'php_version' => PHP_VERSION,
                'php_binary' => substr(PHP_BINARY, 0, 191) ?: null,
                'hostname' => substr((string) gethostname(), 0, 191) ?: null,
            ]);

            SmokeHeartbeat::query()
                ->where('ran_at', '<', now()->subDays((int) config('sdm.heartbeat.keep_days')))
                ->delete();
        } catch (Throwable $e) {
            report($e);
            $this->error('Heartbeat failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Heartbeat recorded.');

        return self::SUCCESS;
    }
}
