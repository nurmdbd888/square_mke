<?php

namespace App\Support\Maintenance;

use App\Models\SmokeHeartbeat;
use App\Support\AppVersion;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Everything the owner needs to confirm the server is ready, as a list of
 * green / amber / red rows. Each check is defensive: none of them may throw,
 * because the page has to render on a half-configured server.
 */
class EnvironmentCheck
{
    public function __construct(
        private readonly DatabaseProbe $database,
        private readonly MigrationStatus $migrations,
        private readonly AppKey $appKey,
        private readonly CronCommand $cron,
    ) {}

    /**
     * @return array{results: list<CheckResult>, database: array<string, mixed>, migrations: array<string, mixed>, heartbeat: SmokeHeartbeat|null, cron: array{php: string, command: string, line: string}, version: array<string, string|null>, paths: array<string, string>}
     */
    public function run(): array
    {
        $database = $this->database->probe();
        $migrations = $database['connected'] ? $this->migrations->status() : ['ok' => false, 'ran' => [], 'pending' => [], 'error' => 'Database not connected'];
        $heartbeat = $database['connected'] ? SmokeHeartbeat::latestOrNull() : null;

        $results = array_merge(
            $this->php(),
            $this->extensions(),
            $this->configuration(),
            $this->folders(),
            $this->databaseResults($database, $migrations),
            $this->cronResults($database['connected'], $migrations, $heartbeat),
            $this->server(),
        );

        return [
            'results' => $results,
            'database' => $database,
            'migrations' => $migrations,
            'heartbeat' => $heartbeat,
            'cron' => [
                'php' => $this->cron->phpPath(),
                'command' => $this->cron->command(),
                'line' => $this->cron->cronLine(),
            ],
            'version' => AppVersion::info(),
            'paths' => [
                'app' => base_path(),
                'public' => public_path(),
                'env' => app()->environmentFilePath(),
                'storage' => storage_path(),
            ],
        ];
    }

    /**
     * @param  list<CheckResult>  $results
     * @return array<string, int>
     */
    public static function summary(array $results): array
    {
        $counts = [CheckResult::OK => 0, CheckResult::WARN => 0, CheckResult::FAIL => 0, CheckResult::INFO => 0];

        foreach ($results as $result) {
            $counts[$result->status]++;
        }

        return $counts;
    }

    /**
     * @return list<CheckResult>
     */
    public function php(): array
    {
        $min = (string) config('sdm.min_php');
        $ok = version_compare(PHP_VERSION, $min, '>=');

        return [
            new CheckResult('php', 'php_version', 'PHP version', $ok ? CheckResult::OK : CheckResult::FAIL,
                PHP_VERSION.($ok ? '' : " (needs {$min} or newer: cPanel > Select PHP Version / MultiPHP Manager)")),
        ];
    }

    /**
     * @return list<CheckResult>
     */
    public function extensions(): array
    {
        $results = [];

        foreach (config('sdm.required_extensions') as $extension) {
            $loaded = extension_loaded($extension);
            $results[] = new CheckResult('extensions', 'ext_'.$extension, 'Extension '.$extension,
                $loaded ? CheckResult::OK : CheckResult::FAIL,
                $loaded ? 'loaded' : 'missing: enable it in cPanel > Select PHP Version > Extensions');
        }

        return $results;
    }

    /**
     * @return list<CheckResult>
     */
    public function configuration(): array
    {
        $envFile = app()->environmentFilePath();
        $sharedInUse = str_ends_with(dirname($envFile), '/shared');
        $envLoaded = is_file($envFile);
        $url = (string) config('app.url');
        $production = app()->environment('production');
        $debug = (bool) config('app.debug');
        $tokenLength = mb_strlen((string) config('sdm.maintenance.token'));

        return [
            new CheckResult('config', 'shared_folder', 'Shared folder (settings and storage)',
                $sharedInUse ? CheckResult::OK : CheckResult::WARN,
                $sharedInUse ? dirname($envFile) : 'not found next to the app folder; using '.base_path().' (fine on a developer computer, not on the server)'),
            new CheckResult('config', 'env_loaded', 'Settings file (.env) loaded',
                $envLoaded ? CheckResult::OK : CheckResult::FAIL,
                $envLoaded ? $envFile : 'not found at '.$envFile),
            new CheckResult('config', 'app_key', 'APP_KEY',
                $this->appKey->isValid() ? CheckResult::OK : CheckResult::FAIL,
                $this->appKey->isValid() ? 'set' : 'missing or invalid: press "Generate APP_KEY"'),
            new CheckResult('config', 'app_env', 'APP_ENV / APP_DEBUG',
                $production && ! $debug ? CheckResult::OK : CheckResult::WARN,
                'APP_ENV='.app()->environment().', APP_DEBUG='.($debug ? 'true' : 'false').($production && ! $debug ? '' : ' (live site should use production / false)')),
            new CheckResult('config', 'app_url', 'APP_URL',
                str_starts_with($url, 'https://') ? CheckResult::OK : CheckResult::WARN,
                $url.(str_starts_with($url, 'https://') ? '' : ' (should start with https://)')),
            new CheckResult('config', 'maintenance_token', 'MAINTENANCE_TOKEN length',
                CheckResult::INFO, $tokenLength.' characters'),
            new CheckResult('config', 'timezone', 'Time zone',
                CheckResult::INFO, config('app.timezone').', now '.now()->format('Y-m-d H:i:s')),
        ];
    }

    /**
     * @return list<CheckResult>
     */
    public function folders(): array
    {
        $folders = [
            'storage' => storage_path(),
            'storage/app/private' => storage_path('app/private'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'storage/backups' => storage_path('backups'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $results = [];

        foreach ($folders as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $results[] = new CheckResult('folders', 'dir_'.str_replace('/', '_', $name), 'Writable: '.$name,
                $writable ? CheckResult::OK : CheckResult::FAIL,
                $writable ? $path : ($exists ? 'not writable: '.$path.' (File Manager > Permissions 755)' : 'missing: '.$path));
        }

        $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');

        if ($docRoot === '') {
            $results[] = new CheckResult('folders', 'docroot', 'Web document root', CheckResult::INFO, 'not a web request');
        } else {
            $matches = realpath($docRoot) === realpath(public_path());
            $results[] = new CheckResult('folders', 'docroot', 'Web document root is the public folder',
                $matches ? CheckResult::OK : CheckResult::WARN,
                $matches ? $docRoot : $docRoot.' (expected '.public_path().')');
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>  $migrations
     * @return list<CheckResult>
     */
    private function databaseResults(array $database, array $migrations): array
    {
        $results = [
            new CheckResult('database', 'db_connection', 'Database connection',
                $database['connected'] ? CheckResult::OK : CheckResult::FAIL,
                $database['connected']
                    ? $database['connection'].' / '.$database['database']
                    : (string) $database['error'].' (check DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env)'),
        ];

        if ($database['connected']) {
            $results[] = new CheckResult('database', 'db_server', 'Database server',
                CheckResult::INFO, $database['server_type'].' '.$database['server_version']);

            $pending = count($migrations['pending']);
            $results[] = new CheckResult('database', 'migrations', 'Migrations',
                ! $migrations['ok'] ? CheckResult::FAIL : ($pending === 0 ? CheckResult::OK : CheckResult::WARN),
                ! $migrations['ok'] ? (string) $migrations['error'] : ($pending === 0
                    ? count($migrations['ran']).' run, none pending'
                    : $pending.' pending: press "Run migrations"'));
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $migrations
     * @return list<CheckResult>
     */
    private function cronResults(bool $connected, array $migrations, ?SmokeHeartbeat $heartbeat): array
    {
        if (! $connected || ! $migrations['ok'] || ! $this->heartbeatTableExists()) {
            return [new CheckResult('cron', 'heartbeat', 'Last cron heartbeat', CheckResult::WARN, 'run migrations first')];
        }

        if ($heartbeat === null) {
            return [new CheckResult('cron', 'heartbeat', 'Last cron heartbeat', CheckResult::WARN,
                'none yet: add the cron command below in cPanel > Cron Jobs, then wait two minutes')];
        }

        $results = [
            new CheckResult('cron', 'heartbeat', 'Last cron heartbeat',
                $heartbeat->isFresh() ? CheckResult::OK : CheckResult::WARN,
                $heartbeat->ran_at->format('Y-m-d H:i:s').' ('.$heartbeat->ran_at->diffForHumans().')'
                    .($heartbeat->isFresh() ? '' : ': cron has stopped or is failing')),
        ];

        $webMinor = implode('.', array_slice(explode('.', PHP_VERSION), 0, 2));
        $cronMinor = implode('.', array_slice(explode('.', (string) $heartbeat->php_version), 0, 2));

        $results[] = new CheckResult('cron', 'cron_php', 'Cron PHP version',
            $webMinor === $cronMinor ? CheckResult::OK : CheckResult::WARN,
            $heartbeat->php_version.' at '.$heartbeat->php_binary.($webMinor === $cronMinor ? '' : " (website uses {$webMinor})"));

        return $results;
    }

    private function heartbeatTableExists(): bool
    {
        try {
            return Schema::hasTable((new SmokeHeartbeat)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<CheckResult>
     */
    public function server(): array
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return [
            new CheckResult('server', 'software', 'Web server', CheckResult::INFO,
                ($_SERVER['SERVER_SOFTWARE'] ?? 'n/a').' / PHP SAPI '.PHP_SAPI),
            new CheckResult('server', 'php_binary', 'PHP binary (web)', CheckResult::INFO, PHP_BINARY ?: 'n/a'),
            new CheckResult('server', 'limits', 'PHP limits', CheckResult::INFO, sprintf(
                'memory_limit %s, max_execution_time %ss, upload_max_filesize %s, post_max_size %s',
                ini_get('memory_limit'), ini_get('max_execution_time'), ini_get('upload_max_filesize'), ini_get('post_max_size'),
            )),
            new CheckResult('server', 'disabled_functions', 'Disabled PHP functions', CheckResult::INFO,
                $disabled === [] ? 'none' : implode(', ', array_slice($disabled, 0, 30))),
        ];
    }
}
