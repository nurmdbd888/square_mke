<?php

namespace Tests\Feature;

use App\Models\SmokeHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_page_reports_database_migrations_cron_and_queue(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => 'x', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);

        $this->actingAs($this->owner())->get('/health')
            ->assertOk()
            ->assertSee('data-health="database" data-status="ok"', false)
            ->assertSee('data-health="migrations" data-status="ok"', false)
            ->assertSee('data-health="cron" data-status="warn"', false)
            ->assertSee('data-health="queue" data-status="warn"', false)
            ->assertSee('0 waiting, 1 failed')
            ->assertSee(PHP_VERSION);
    }

    public function test_fresh_heartbeat_is_green(): void
    {
        SmokeHeartbeat::create(['ran_at' => now()->subMinute(), 'php_version' => PHP_VERSION]);

        $this->actingAs($this->userWithRole('accountant'))->get('/health')
            ->assertSee('data-health="cron" data-status="ok"', false);
    }

    public function test_dashboard_shows_the_health_summary_only_with_permission(): void
    {
        $this->actingAs($this->owner())->get('/')
            ->assertSeeInOrder(['data-testid="database"', 'Connected'], false)
            ->assertSeeInOrder(['data-testid="cron"', 'No heartbeat yet'], false);

        $this->actingAs($this->userWithRole('sr'))->get('/')
            ->assertOk()
            ->assertDontSee('data-testid="database"', false);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/health')->assertRedirect(route('login'));
    }
}
