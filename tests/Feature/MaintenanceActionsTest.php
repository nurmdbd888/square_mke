<?php

namespace Tests\Feature;

use App\Models\SmokeHeartbeat;
use App\Models\User;
use App\Support\Access\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Starts from an empty database on purpose: these tests cover the very first
 * visit to a fresh server, before anything has been migrated.
 */
class MaintenanceActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:wipe', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        Artisan::call('db:wipe', ['--force' => true]);
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_dashboard_on_a_fresh_database_asks_for_migrations(): void
    {
        $this->asMaintenanceUser()
            ->get('/maintenance')
            ->assertOk()
            ->assertSee('data-check="db_connection" data-status="ok"', false)
            ->assertSee('data-check="migrations" data-status="warn"', false)
            ->assertSee('pending: press &quot;Run migrations&quot;', false)
            ->assertSee('data-check="heartbeat" data-status="warn"', false)
            ->assertSee('run migrations first');
    }

    public function test_run_migrations_button_creates_the_heartbeat_table(): void
    {
        $this->assertFalse(Schema::hasTable('smoke_heartbeats'));

        $this->asMaintenanceUser()
            ->post('/maintenance/migrate', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('data-testid="action-result"', false)
            ->assertSee('Finished successfully.')
            ->assertSee('create_smoke_heartbeats_table')
            ->assertSee('data-check="migrations" data-status="ok"', false);

        $this->assertTrue(Schema::hasTable('smoke_heartbeats'));
    }

    public function test_running_migrations_twice_is_harmless(): void
    {
        $this->artisan('migrate', ['--force' => true]);

        $this->asMaintenanceUser()
            ->post('/maintenance/migrate', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('Finished successfully.')
            ->assertSee('Nothing to migrate');
    }

    public function test_dashboard_shows_the_last_heartbeat_and_cron_command(): void
    {
        $this->artisan('migrate', ['--force' => true]);
        $this->artisan('sdm:heartbeat')->assertSuccessful();

        $this->asMaintenanceUser()
            ->get('/maintenance')
            ->assertOk()
            ->assertSee('data-check="heartbeat" data-status="ok"', false)
            ->assertSee('data-check="cron_php" data-status="ok"', false)
            ->assertSee(base_path('artisan').' schedule:run &gt;&gt; /dev/null 2&gt;&amp;1', false);
    }

    public function test_stale_heartbeat_is_flagged(): void
    {
        $this->artisan('migrate', ['--force' => true]);
        SmokeHeartbeat::create(['ran_at' => now()->subMinutes(30), 'php_version' => PHP_VERSION]);

        $this->asMaintenanceUser()
            ->get('/maintenance')
            ->assertSee('data-check="heartbeat" data-status="warn"', false)
            ->assertSee('cron has stopped or is failing');
    }

    public function test_create_owner_before_migrations_asks_for_them(): void
    {
        $this->asMaintenanceUser()
            ->get('/maintenance')
            ->assertDontSee('data-testid="owner-section"', false);

        $this->asMaintenanceUser()
            ->post('/maintenance/create-owner', ['_maintenance_token' => $this->maintenanceFormToken(), 'name' => 'X'])
            ->assertOk()
            ->assertSee('Press &quot;Run migrations&quot; first.', false);
    }

    public function test_after_migrations_the_owner_can_be_created(): void
    {
        $this->asMaintenanceUser()
            ->post('/maintenance/migrate', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertSee('Finished successfully.')
            ->assertSee('data-testid="owner-section"', false);

        $this->asMaintenanceUser()->post('/maintenance/create-owner', [
            '_maintenance_token' => $this->maintenanceFormToken(),
            'name' => 'Khaza Owner',
            'username' => 'khaza',
            'password' => 'owner-password-1',
            'password_confirmation' => 'owner-password-1',
        ])->assertSee('Owner khaza was created.');

        $this->assertTrue(User::query()->where('username', 'khaza')->sole()->isOwner());
        $this->assertSame(count(PermissionRegistry::ROLES), Role::query()->count());
    }

    public function test_clear_caches_button_runs(): void
    {
        $this->asMaintenanceUser()
            ->post('/maintenance/clear-caches', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('Clear caches')
            ->assertSee('Finished successfully.');
    }
}
