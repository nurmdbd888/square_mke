<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupRunner;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'sdm:backup {--no-drive : Skip Google Drive upload}';

    protected $description = 'Create a gzipped database dump and uploads zip; prune old backups.';

    public function handle(BackupRunner $runner): int
    {
        $result = $runner->run(uploadDrive: ! $this->option('no-drive'));

        $this->line(__('Backup saved as :name', ['name' => $result['basename']]));
        if ($result['drive_file_id']) {
            $this->line(__('Uploaded to Google Drive.'));
        }
        if ($result['pruned_local'] > 0 || $result['pruned_drive'] > 0) {
            $this->line(__('Pruned :local local and :drive Drive file(s).', [
                'local' => $result['pruned_local'],
                'drive' => $result['pruned_drive'],
            ]));
        }

        return self::SUCCESS;
    }
}
