<?php

namespace App\Domain\Backup;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

final class RestoreRunner
{
    public function __construct(private readonly BackupCatalog $catalog) {}

    public function run(string $basename): void
    {
        @set_time_limit(600);

        $zipPath = $this->catalog->pathFor($basename);
        if (! is_file($zipPath)) {
            throw new RuntimeException(__('Backup file not found.'));
        }

        $tmpdir = $this->catalog->directory().'/restore-'.uniqid('', true);
        mkdir($tmpdir, 0755, true);

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Could not open backup zip.');
            }
            $zip->extractTo($tmpdir);
            $zip->close();

            $sqlGz = $tmpdir.'/database.sql.gz';
            if (! is_file($sqlGz)) {
                throw new RuntimeException('Backup zip has no database.sql.gz.');
            }

            $sql = gzdecode((string) file_get_contents($sqlGz));
            if ($sql === false) {
                throw new RuntimeException('Could not read database dump.');
            }

            $this->importSql($sql);
            $this->restoreUploads($tmpdir.'/uploads.zip');
        } finally {
            $this->deleteTree($tmpdir);
        }
    }

    private function importSql(string $sql): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $path = config("database.connections.{$connection}.database");
            DB::disconnect($connection);
            file_put_contents($path, $sql);

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
        foreach ($this->splitSqlStatements($sql) as $statement) {
            $trim = trim($statement);
            if ($trim === '') {
                continue;
            }
            DB::unprepared($trim);
        }
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        return preg_split('/;\s*\n/', $sql) ?: [];
    }

    private function restoreUploads(string $uploadsZip): void
    {
        if (! is_file($uploadsZip)) {
            return;
        }

        $destRoot = storage_path('app');
        $zip = new ZipArchive;
        if ($zip->open($uploadsZip) !== true) {
            return;
        }
        $zip->extractTo($destRoot);
        $zip->close();
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
