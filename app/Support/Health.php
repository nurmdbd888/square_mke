<?php

namespace App\Support;

use App\Domain\Backup\BackupCatalog;
use App\Models\SmokeHeartbeat;
use App\Support\Maintenance\DatabaseProbe;
use App\Support\Maintenance\MigrationStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Figures for the /health page and the dashboard status cards. Like the
 * environment check, nothing here may throw.
 */
class Health
{
    public function __construct(
        private readonly DatabaseProbe $database,
        private readonly MigrationStatus $migrations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $database = $this->database->probe();
        $heartbeat = $database['connected'] ? SmokeHeartbeat::latestOrNull() : null;

        return [
            'database' => $database,
            'migrations' => $database['connected'] ? $this->migrations->status() : null,
            'heartbeat' => $heartbeat,
            'cron_ok' => $heartbeat?->isFresh() ?? false,
            'queue' => $database['connected'] ? $this->queue() : null,
            'disk' => $this->disk(),
            'version' => AppVersion::info(),
            'php' => PHP_VERSION,
            'backup' => $this->backup(),
        ];
    }

    /**
     * @return array{at: string|null, basename: string|null, count: int, stale: bool, age_hours: int|null}|null
     */
    public function backup(int $staleAfterHours = 36): ?array
    {
        try {
            $catalog = app(BackupCatalog::class);
            $manifest = $catalog->lastManifest();
            $list = $catalog->listLocal();
            $at = isset($manifest['at']) ? (string) $manifest['at'] : null;
            $ageHours = null;
            $stale = true;
            if ($at !== null && $at !== '') {
                try {
                    $ageHours = (int) max(0, now()->diffInHours(Carbon::parse($at), absolute: true));
                    $stale = $ageHours >= $staleAfterHours;
                } catch (Throwable) {
                    $stale = true;
                }
            }

            return [
                'at' => $at,
                'basename' => isset($manifest['basename']) ? (string) $manifest['basename'] : ($list[0] ?? null),
                'count' => count($list),
                'stale' => $stale,
                'age_hours' => $ageHours,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{pending: int, oldest_seconds: int|null, failed: int}|null
     */
    private function queue(): ?array
    {
        try {
            if (! Schema::hasTable('jobs')) {
                return null;
            }

            $oldest = DB::table('jobs')->min('available_at');

            return [
                'pending' => DB::table('jobs')->count(),
                'oldest_seconds' => $oldest === null ? null : max(0, now()->getTimestamp() - (int) $oldest),
                'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{free: string, total: string|null}|null
     */
    private function disk(): ?array
    {
        if (! function_exists('disk_free_space')) {
            return null;
        }

        $free = @disk_free_space(storage_path());
        $total = function_exists('disk_total_space') ? @disk_total_space(storage_path()) : false;

        if ($free === false) {
            return null;
        }

        return ['free' => self::bytes($free), 'total' => $total === false ? null : self::bytes($total)];
    }

    private static function bytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
