<?php

namespace App\Support\Maintenance;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Connects to the configured database and reports what it found, without
 * ever throwing.
 */
class DatabaseProbe
{
    /**
     * @return array{connected: bool, connection: string, driver: string, database: string|null, server_type: string|null, server_version: string|null, error: string|null}
     */
    public function probe(?string $connection = null): array
    {
        $connection ??= (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver", 'unknown');

        $result = [
            'connected' => false,
            'connection' => $connection,
            'driver' => $driver,
            'database' => config("database.connections.{$connection}.database"),
            'server_type' => null,
            'server_version' => null,
            'error' => null,
        ];

        try {
            $db = DB::connection($connection);
            $db->getPdo();

            $version = match ($driver) {
                'sqlite' => (string) $db->selectOne('select sqlite_version() as v')->v,
                'pgsql' => (string) $db->selectOne('show server_version')->server_version,
                default => (string) $db->selectOne('select version() as v')->v,
            };

            $result['connected'] = true;
            $result['server_version'] = $version;
            $result['server_type'] = self::serverType($driver, $version);
        } catch (Throwable $e) {
            $result['error'] = self::cleanError($e->getMessage());
        }

        return $result;
    }

    public static function serverType(string $driver, string $version): string
    {
        return match (true) {
            $driver === 'sqlite' => 'SQLite',
            $driver === 'pgsql' => 'PostgreSQL',
            stripos($version, 'mariadb') !== false => 'MariaDB',
            default => 'MySQL',
        };
    }

    private static function cleanError(string $message): string
    {
        // Laravel appends connection details and the SQL; the driver message
        // before them is what the owner needs to see.
        $message = preg_replace('/\s*\(Connection: .*$/s', '', $message) ?? $message;

        return mb_substr($message, 0, 300);
    }
}
