<?php

namespace App\Domain\Backup;

use Illuminate\Support\Carbon;

/**
 * Local backup files under storage/backups.
 */
final class BackupCatalog
{
    public const PREFIX = 'mke-backup-';

    public function directory(): string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * @return list<string> basenames newest first
     */
    public function listLocal(): array
    {
        $files = glob($this->directory().'/'.self::PREFIX.'*.zip') ?: [];
        rsort($files);

        return array_values(array_map('basename', $files));
    }

    public function pathFor(string $basename): string
    {
        $basename = basename($basename);
        if (! str_starts_with($basename, self::PREFIX) || ! str_ends_with($basename, '.zip')) {
            throw new \InvalidArgumentException('Invalid backup filename.');
        }

        return $this->directory().'/'.$basename;
    }

    public function stamp(): string
    {
        return Carbon::now()->format('Y-m-d-His');
    }

    public function writeManifest(array $payload): void
    {
        file_put_contents(
            $this->directory().'/last-backup.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastManifest(): ?array
    {
        $path = $this->directory().'/last-backup.json';
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }
}
