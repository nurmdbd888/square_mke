<?php

namespace Tests\Feature;

use App\Support\AppVersion;
use App\Support\Maintenance\CheckResult;
use App\Support\Maintenance\DatabaseProbe;
use App\Support\Maintenance\EnvironmentCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, CheckResult>
     */
    private function results(): array
    {
        return collect(app(EnvironmentCheck::class)->run()['results'])->keyBy('key')->all();
    }

    public function test_healthy_environment_has_no_failures(): void
    {
        $results = $this->results();

        $this->assertSame(CheckResult::OK, $results['php_version']->status);
        $this->assertSame(CheckResult::OK, $results['ext_pdo_mysql']->status);
        $this->assertSame(CheckResult::OK, $results['ext_bcmath']->status);
        $this->assertSame(CheckResult::OK, $results['app_key']->status);
        $this->assertSame(CheckResult::OK, $results['db_connection']->status);
        $this->assertSame(CheckResult::OK, $results['migrations']->status);
        $this->assertSame(CheckResult::OK, $results['dir_storage']->status);
        $this->assertSame(CheckResult::OK, $results['dir_bootstrap_cache']->status);
        $this->assertSame('SQLite '.$this->app['db']->selectOne('select sqlite_version() as v')->v, $results['db_server']->detail);
    }

    public function test_old_php_is_a_failure(): void
    {
        config(['sdm.min_php' => '99.0.0']);

        $this->assertSame(CheckResult::FAIL, $this->results()['php_version']->status);
    }

    public function test_missing_extension_is_a_failure(): void
    {
        config(['sdm.required_extensions' => ['pdo', 'sdm_no_such_extension']]);
        $results = $this->results();

        $this->assertSame(CheckResult::OK, $results['ext_pdo']->status);
        $this->assertSame(CheckResult::FAIL, $results['ext_sdm_no_such_extension']->status);
    }

    public function test_missing_app_key_is_a_failure(): void
    {
        config(['app.key' => '']);

        $this->assertSame(CheckResult::FAIL, $this->results()['app_key']->status);
    }

    public function test_debug_mode_and_http_url_are_warnings(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://mke.rockproviders.com']);
        $results = $this->results();

        $this->assertSame(CheckResult::WARN, $results['app_env']->status);
        $this->assertSame(CheckResult::WARN, $results['app_url']->status);
    }

    public function test_cron_command_points_at_this_apps_artisan(): void
    {
        $cron = app(EnvironmentCheck::class)->run()['cron'];

        $this->assertStringEndsWith(base_path('artisan').' schedule:run >> /dev/null 2>&1', $cron['command']);
        $this->assertStringStartsWith('* * * * * ', $cron['line']);
    }

    public function test_summary_counts_statuses(): void
    {
        $summary = EnvironmentCheck::summary([
            new CheckResult('g', 'a', 'A', CheckResult::OK),
            new CheckResult('g', 'b', 'B', CheckResult::FAIL),
            new CheckResult('g', 'c', 'C', CheckResult::FAIL),
        ]);

        $this->assertSame(['ok' => 1, 'warn' => 0, 'fail' => 2, 'info' => 0], $summary);
    }

    public function test_sdm_check_command_prints_the_table(): void
    {
        $this->artisan('sdm:check')
            ->expectsOutputToContain('PHP version')
            ->expectsOutputToContain('Cron command:');
    }

    public function test_version_is_read_from_version_json(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sdm');
        file_put_contents($file, json_encode(['version' => 'v0.0.1', 'commit' => 'abcdef1234567', 'built_at' => '2026-10-17T00:00:00Z']));

        $this->assertSame('v0.0.1', AppVersion::info($file)['version']);
        $this->assertSame('dev', AppVersion::info('/nonexistent/version.json')['version']);

        unlink($file);
    }

    public function test_configured_database_server_type_matches_ci_expectation(): void
    {
        $expected = getenv('SDM_EXPECT_DB_SERVER');

        if (! $expected) {
            $this->markTestSkipped('Set SDM_EXPECT_DB_SERVER (MySQL or MariaDB) to check a real server.');
        }

        $this->assertSame($expected, app(DatabaseProbe::class)->probe()['server_type']);
    }
}
