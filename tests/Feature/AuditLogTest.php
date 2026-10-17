<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_logged_with_old_and_new_values(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $user = $this->userWithRole('viewer', ['name' => 'Old Name', 'phone' => '0170']);

        $user->update(['name' => 'New Name', 'phone' => '0170']);

        $log = AuditLog::query()->where('event', 'updated')->where('auditable_id', $user->id)->sole();
        $this->assertSame('user', $log->auditable_type);
        $this->assertSame(['name' => 'Old Name'], $log->old_values);
        $this->assertSame(['name' => 'New Name'], $log->new_values);
        $this->assertSame($owner->id, $log->user_id);
    }

    public function test_audit_rows_are_append_only_records_of_who_and_where(): void
    {
        $owner = $this->owner(['username' => 'boss']);

        $this->actingAs($owner)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'TestBrowser/1.0'])
            ->put('/settings', ['settings' => ['firm_name' => 'Changed', 'distributor_code' => '4831', 'default_locale' => 'en', 'fiscal_year_start_month' => 7]]);

        $log = AuditLog::query()->where('event', 'settings.updated')->sole();
        $this->assertSame('boss', $log->user_name);
        $this->assertSame('203.0.113.9', $log->ip_address);
        $this->assertSame('TestBrowser/1.0', $log->user_agent);
        $this->assertSame(AuditLogger::SOURCE_WEB, $log->source);
    }

    public function test_viewer_lists_filters_and_paginates(): void
    {
        $owner = $this->owner(['username' => 'boss']);
        $other = $this->userWithRole('accountant', ['username' => 'acc']);

        for ($i = 0; $i < 55; $i++) {
            AuditLogger::log('auth.failed', null, null, ['login' => 'guess'.$i], subjectType: 'user');
        }
        AuditLogger::log('settings.updated', null, ['firm_name' => 'A'], ['firm_name' => 'B'], $other, subjectType: 'setting');
        AuditLog::query()->where('event', 'settings.updated')->update(['created_at' => '2026-01-15 10:00:00']);

        $this->actingAs($owner)->get('/audit')->assertOk()->assertSee('?page=2', false);

        $this->actingAs($owner)->get('/audit?event=settings.updated')
            ->assertSee('data-event="settings.updated"', false)
            ->assertDontSee('data-event="auth.failed"', false)
            ->assertSee('firm_name: A → B');

        $this->actingAs($owner)->get('/audit?event=auth.*')
            ->assertSee('data-event="auth.failed"', false)
            ->assertDontSee('data-event="settings.updated"', false);

        $this->actingAs($owner)->get("/audit?user={$other->id}")
            ->assertSee('data-event="settings.updated"', false)
            ->assertDontSee('data-event="auth.failed"', false);

        $this->actingAs($owner)->get('/audit?type=setting')
            ->assertSee('data-event="settings.updated"', false)
            ->assertDontSee('data-event="auth.failed"', false);

        $this->actingAs($owner)->get('/audit?from=2026-01-01&to=2026-01-31')
            ->assertSee('data-event="settings.updated"', false)
            ->assertDontSee('data-event="auth.failed"', false);

        $this->actingAs($owner)->get('/audit?source=maintenance')
            ->assertSee('No entries match.');

        $this->actingAs($owner)->get('/audit?from=17-10-2026')->assertSessionHasErrors('from');
    }

    public function test_htmx_filter_returns_only_the_table(): void
    {
        $this->actingAs($this->owner())->get('/audit?event=auth.*', ['HX-Request' => 'true'])
            ->assertOk()
            ->assertSee('data-testid="audit-table"', false)
            ->assertDontSee('<html', false);
    }

    public function test_detail_page_shows_the_before_and_after_table(): void
    {
        $owner = $this->owner();
        $log = AuditLogger::log('settings.updated', null, ['firm_name' => 'Old Firm'], ['firm_name' => 'New Firm'], $owner, subjectType: 'setting');

        $this->actingAs($owner)->get("/audit/{$log->id}")
            ->assertOk()
            ->assertSee('data-testid="audit-changes"', false)
            ->assertSeeInOrder(['firm_name', 'Old Firm', 'New Firm']);
    }

    public function test_bangla_labels(): void
    {
        $owner = $this->owner(['locale' => 'bn']);
        AuditLogger::log('settings.updated', null, ['firm_name' => 'A'], ['firm_name' => 'B'], $owner, subjectType: 'setting');

        $this->actingAs($owner)->get('/audit')->assertOk()->assertSee('অডিট লগ')->assertSee(trans('audit.events.settings.updated', [], 'bn'));
    }

    public function test_logger_never_throws(): void
    {
        Log::spy();
        AuditLog::creating(fn () => throw new RuntimeException('disk full'));

        $this->assertNull(AuditLogger::log('auth.failed'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_console_source(): void
    {
        $log = AuditLogger::log('maintenance.migrate');

        $this->assertSame(AuditLogger::SOURCE_CONSOLE, $log->source);
    }
}
