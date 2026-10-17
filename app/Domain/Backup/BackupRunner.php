<?php

namespace App\Domain\Backup;

use App\Domain\Google\GoogleDriveService;
use Druidfi\Mysqldump\Mysqldump;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupRunner
{
    public function __construct(
        private readonly BackupCatalog $catalog,
        private readonly GoogleDriveService $drive,
    ) {}

    /**
     * @return array{basename: string, path: string, drive_file_id: string|null, pruned_local: int, pruned_drive: int}
     */
    public function run(bool $uploadDrive = true): array
    {
        @set_time_limit(600);

        $stamp = $this->catalog->stamp();
        $basename = BackupCatalog::PREFIX.$stamp.'.zip';
        $zipPath = $this->catalog->directory().'/'.$basename;
        $tmpdir = $this->catalog->directory().'/tmp-'.$stamp;
        mkdir($tmpdir, 0755, true);

        try {
            $sqlPath = $tmpdir.'/database.sql.gz';
            $this->dumpDatabase($sqlPath);
            $this->addPrivateUploads($tmpdir);

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create backup zip.');
            }
            foreach (glob($tmpdir.'/*') ?: [] as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            $driveId = null;
            if ($uploadDrive) {
                try {
                    $driveId = $this->drive->saveFile($zipPath, $basename, 'application/zip');
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $retention = (int) setting('backup_retention_days');
            $prunedLocal = $this->pruneLocal($retention, $basename);
            $prunedDrive = 0;
            if ($uploadDrive) {
                try {
                    $prunedDrive = $this->drive->pruneBackupFiles($retention);
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $manifest = [
                'at' => now()->toIso8601String(),
                'basename' => $basename,
                'path' => $zipPath,
                'size_bytes' => filesize($zipPath) ?: 0,
                'drive_file_id' => $driveId,
                'pruned_local' => $prunedLocal,
                'pruned_drive' => $prunedDrive,
            ];
            $this->catalog->writeManifest($manifest);

            return [
                'basename' => $basename,
                'path' => $zipPath,
                'drive_file_id' => $driveId,
                'pruned_local' => $prunedLocal,
                'pruned_drive' => $prunedDrive,
            ];
        } finally {
            foreach (glob($tmpdir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tmpdir);
        }
    }

    private function dumpDatabase(string $gzPath): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? '') === 'sqlite') {
            $source = (string) ($config['database'] ?? '');
            if ($source === ':memory:') {
                throw new RuntimeException('In-memory SQLite cannot be backed up; use a file database on the server.');
            }
            if (! is_file($source)) {
                throw new RuntimeException('SQLite database file missing.');
            }
            $raw = file_get_contents($source);
            file_put_contents($gzPath, gzencode($raw, 9));

            return;
        }

        if (! in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Unsupported database driver for backup.');
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $user = $config['username'] ?? '';
        $pass = $config['password'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $tmpSql = $gzPath.'.tmp.sql';
        $dump = new Mysqldump($dsn, $user, $pass, ['add-drop-table' => true]);
        $dump->start($tmpSql);
        file_put_contents($gzPath, gzencode((string) file_get_contents($tmpSql), 9));
        @unlink($tmpSql);
    }

    private function addPrivateUploads(string $tmpdir): void
    {
        $private = storage_path('app/private');
        if (! is_dir($private)) {
            return;
        }

        $uploadsZip = $tmpdir.'/uploads.zip';
        $zip = new ZipArchive;
        if ($zip->open($uploadsZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($private, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $local = 'private/'.substr($path, strlen($private) + 1);
            $zip->addFile($path, $local);
        }
        $zip->close();
    }

    private function pruneLocal(int $retentionDays, string $keepBasename): int
    {
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $removed = 0;
        foreach ($this->catalog->listLocal() as $basename) {
            if ($basename === $keepBasename) {
                continue;
            }
            $path = $this->catalog->pathFor($basename);
            if (filemtime($path) >= $cutoff) {
                continue;
            }
            if (@unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }
}
