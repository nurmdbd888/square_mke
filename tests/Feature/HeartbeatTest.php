<?php

namespace Tests\Feature;

use App\Models\SmokeHeartbeat;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_writes_a_heartbeat_row(): void
    {
        $this->travelTo(now()->startOfMinute());

        $this->artisan('sdm:heartbeat')->assertSuccessful();

        $beat = SmokeHeartbeat::sole();
        $this->assertTrue($beat->ran_at->equalTo(now()));
        $this->assertSame('cron', $beat->source);
        $this->assertSame(PHP_VERSION, $beat->php_version);
        $this->assertTrue($beat->isFresh());
    }

    public function test_command_prunes_rows_older_than_a_week(): void
    {
        SmokeHeartbeat::create(['ran_at' => now()->subDays(8), 'php_version' => '8.4.0']);
        SmokeHeartbeat::create(['ran_at' => now()->subDays(2), 'php_version' => '8.4.0']);

        $this->artisan('sdm:heartbeat')->assertSuccessful();

        $this->assertSame(2, SmokeHeartbeat::count());
        $this->assertFalse(SmokeHeartbeat::where('ran_at', '<', now()->subDays(7))->exists());
    }

    public function test_command_fails_cleanly_before_migrations(): void
    {
        Schema::drop('smoke_heartbeats');

        $this->artisan('sdm:heartbeat')->assertFailed();
    }

    public function test_heartbeat_is_scheduled_every_minute_in_process(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => $event->description === 'sdm:heartbeat');

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertInstanceOf(CallbackEvent::class, $event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_schedule_run_records_a_heartbeat(): void
    {
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(1, SmokeHeartbeat::count());
    }

    public function test_latest_heartbeat_is_null_before_migrations(): void
    {
        Schema::drop('smoke_heartbeats');

        $this->assertNull(SmokeHeartbeat::latestOrNull());
    }

    public function test_heartbeat_older_than_three_minutes_is_not_fresh(): void
    {
        $beat = SmokeHeartbeat::create(['ran_at' => now()->subMinutes(4), 'php_version' => PHP_VERSION]);

        $this->assertFalse($beat->isFresh());
        $this->assertSame($beat->id, SmokeHeartbeat::latestOrNull()?->id);
    }
}
