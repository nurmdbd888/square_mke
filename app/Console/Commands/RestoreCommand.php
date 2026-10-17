<?php

namespace App\Console\Commands;

use App\Domain\Backup\RestoreRunner;
use Illuminate\Console\Command;

class RestoreCommand extends Command
{
    protected $signature = 'sdm:restore {basename : Backup zip basename, e.g. mke-backup-2026-10-17-020000.zip} {--force : Required confirmation to overwrite the live database}';

    protected $description = 'Restore database and private uploads from a local backup zip.';

    public function handle(RestoreRunner $runner): int
    {
        if (! $this->option('force')) {
            $this->error(__('Add --force to confirm you want to overwrite the current database.'));

            return self::FAILURE;
        }

        $runner->run((string) $this->argument('basename'));
        $this->info(__('Restore finished.'));

        return self::SUCCESS;
    }
}
