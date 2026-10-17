<?php

namespace Tests\Feature;

use App\Support\Maintenance\CheckResult;
use App\Support\Maintenance\EnvironmentCheck;
use PDO;
use Tests\TestCase;

/**
 * A wrong password or a missing database must never turn into an error page:
 * the owner needs these pages to find out what is wrong.
 */
class DatabaseDownTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.unreachable' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 1,
                'database' => 'nope',
                'username' => 'nope',
                'password' => 'pw-should-not-leak',
                'options' => [PDO::ATTR_TIMEOUT => 2],
            ],
            'database.default' => 'unreachable',
        ]);
    }

    public function test_environment_check_reports_the_failure_without_throwing(): void
    {
        $report = app(EnvironmentCheck::class)->run();
        $results = collect($report['results'])->keyBy('key');

        $this->assertFalse($report['database']['connected']);
        $this->assertSame(CheckResult::FAIL, $results['db_connection']->status);
        $this->assertStringContainsString('check DB_DATABASE', $results['db_connection']->detail);
        $this->assertStringNotContainsString('pw-should-not-leak', $results['db_connection']->detail);
        $this->assertSame(CheckResult::WARN, $results['heartbeat']->status);
        $this->assertFalse($results->has('migrations'));
    }

    public function test_login_page_still_renders_and_points_to_maintenance(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('M/s Khaza Enterprise')
            ->assertSeeInOrder(['data-testid="setup-notice"', 'The database is not ready.', route('maintenance')], false)
            ->assertDontSee('pw-should-not-leak');
    }

    public function test_sign_in_attempt_explains_instead_of_crashing(): void
    {
        $this->from('/login')->post('/login', ['login' => 'owner', 'password' => 'whatever-123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login' => 'The database is not ready. Open the Maintenance page to check the settings and run migrations.']);
    }

    public function test_maintenance_page_opens_with_the_phrase_alone(): void
    {
        $this->asMaintenanceUser()
            ->get('/maintenance')
            ->assertOk()
            ->assertDontSee('data-testid="owner-section"', false);
    }

    public function test_maintenance_dashboard_still_renders(): void
    {
        $this->asMaintenanceUser()
            ->get('/maintenance')
            ->assertOk()
            ->assertSee('data-check="db_connection" data-status="fail"', false);
    }

    public function test_run_migrations_shows_the_error_instead_of_crashing(): void
    {
        $this->asMaintenanceUser()
            ->post('/maintenance/migrate', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('Failed.');
    }
}
