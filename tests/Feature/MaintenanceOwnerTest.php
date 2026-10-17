<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceOwnerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function ownerForm(array $overrides = []): array
    {
        return $overrides + [
            '_maintenance_token' => $this->maintenanceFormToken(),
            'name' => 'Khaza Owner',
            'username' => 'Khaza',
            'email' => 'owner@example.com',
            'password' => 'owner-password-1',
            'password_confirmation' => 'owner-password-1',
        ];
    }

    public function test_without_an_owner_the_phrase_alone_opens_the_page_with_the_create_form(): void
    {
        $this->asMaintenanceUser()->get('/maintenance')
            ->assertOk()
            ->assertSee('data-testid="owner-section"', false)
            ->assertSee('Create the owner account')
            ->assertSee(route('maintenance.create-owner'), false);
    }

    public function test_create_the_first_owner_without_a_terminal(): void
    {
        $this->asMaintenanceUser()->post('/maintenance/create-owner', $this->ownerForm())
            ->assertOk()
            ->assertSee('Owner khaza was created.');

        $owner = User::query()->where('username', 'khaza')->sole();
        $this->assertTrue($owner->isOwner());
        $this->assertTrue(Hash::check('owner-password-1', $owner->password));
        $this->assertFalse($owner->must_change_password);

        $log = AuditLog::query()->where('event', 'maintenance.owner_created')->sole();
        $this->assertSame(AuditLogger::SOURCE_MAINTENANCE, $log->source);
        $this->assertSame($owner->id, $log->auditable_id);
        $this->assertNull($log->user_id);

        $this->post('/login', ['login' => 'khaza', 'password' => 'owner-password-1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_create_owner_shows_validation_errors_on_the_page(): void
    {
        $this->asMaintenanceUser()->post('/maintenance/create-owner', $this->ownerForm([
            'username' => 'a b',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]))
            ->assertOk()
            ->assertSee('data-testid="owner-errors"', false)
            ->assertSee('value="Khaza Owner"', false);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_create_owner_needs_the_form_token(): void
    {
        $this->asMaintenanceUser()->post('/maintenance/create-owner', $this->ownerForm(['_maintenance_token' => 'forged']))
            ->assertStatus(419);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_create_owner_needs_the_secret_phrase(): void
    {
        $this->post('/maintenance/create-owner', $this->ownerForm())->assertRedirect(route('maintenance'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_once_an_owner_exists_the_phrase_alone_is_not_enough(): void
    {
        $this->owner();

        $this->asMaintenanceUser()->get('/maintenance')
            ->assertForbidden()
            ->assertSee('data-testid="owner-login-needed"', false);

        $this->asMaintenanceUser()->post('/maintenance/migrate', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertForbidden();
        $this->asMaintenanceUser()->post('/maintenance/create-owner', $this->ownerForm(['username' => 'intruder']))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'intruder']);
    }

    public function test_non_owner_sign_in_is_not_enough(): void
    {
        $this->owner();

        $this->actingAs($this->userWithRole('accountant'))
            ->asMaintenanceUser()
            ->get('/maintenance')
            ->assertForbidden();
    }

    public function test_owner_signs_in_and_returns_to_the_maintenance_page(): void
    {
        $this->owner(['username' => 'boss']);

        $this->asMaintenanceUser()->get('/maintenance')->assertForbidden();
        $this->post('/login', ['login' => 'boss', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('maintenance'));

        $this->asMaintenanceUser()->get('/maintenance')
            ->assertOk()
            ->assertSee('You are signed in as boss.');
    }

    public function test_signed_in_owner_still_needs_the_phrase(): void
    {
        $this->actingAs($this->owner())->get('/maintenance')
            ->assertOk()
            ->assertSee('name="token"', false)
            ->assertDontSee('data-testid="owner-section"', false);
    }

    public function test_signed_in_owner_runs_actions_and_adds_another_owner(): void
    {
        $owner = $this->owner(['username' => 'boss']);

        $this->actingAs($owner)->asMaintenanceUser()
            ->post('/maintenance/clear-caches', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('Finished successfully.');
        $this->assertDatabaseHas('audit_logs', ['event' => 'maintenance.clear_caches', 'user_id' => $owner->id, 'source' => 'maintenance']);

        $this->actingAs($owner)->asMaintenanceUser()
            ->post('/maintenance/create-owner', $this->ownerForm(['username' => 'second', 'email' => '']))
            ->assertOk()
            ->assertSee('Owner second was created.');

        $this->assertTrue(User::query()->where('username', 'second')->sole()->isOwner());
        $this->assertDatabaseHas('audit_logs', ['event' => 'maintenance.owner_created', 'user_id' => $owner->id]);
    }

    public function test_owner_recovery_resets_a_forgotten_password(): void
    {
        $owner = $this->owner(['username' => 'boss']);
        $owner->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now()])->save();
        config(['sdm.maintenance.owner_recovery' => true]);

        $this->asMaintenanceUser()->get('/maintenance')
            ->assertOk()
            ->assertSee('data-testid="recovery-mode"', false);

        $this->asMaintenanceUser()->post('/maintenance/recover-owner', [
            '_maintenance_token' => $this->maintenanceFormToken(),
            'username' => 'Boss',
            'password' => 'recovered-pass-1',
            'password_confirmation' => 'recovered-pass-1',
        ])->assertOk()->assertSee('MAINTENANCE_OWNER_RECOVERY=false');

        $owner->refresh();
        $this->assertTrue(Hash::check('recovered-pass-1', $owner->password));
        $this->assertFalse($owner->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['event' => 'maintenance.owner_recovered', 'auditable_id' => $owner->id, 'source' => 'maintenance']);
    }

    public function test_recovery_only_resets_owners(): void
    {
        $this->owner();
        $sr = $this->userWithRole('sr', ['username' => 'salesrep']);
        config(['sdm.maintenance.owner_recovery' => true]);

        $this->asMaintenanceUser()->post('/maintenance/recover-owner', [
            '_maintenance_token' => $this->maintenanceFormToken(),
            'username' => 'salesrep',
            'password' => 'recovered-pass-1',
            'password_confirmation' => 'recovered-pass-1',
        ])->assertOk()->assertSee('No owner with that user name.');

        $this->assertTrue(Hash::check('correct-horse-battery', $sr->fresh()->password));
    }

    public function test_recovery_is_off_by_default(): void
    {
        $owner = $this->owner(['username' => 'boss']);

        $this->actingAs($owner)->asMaintenanceUser()->post('/maintenance/recover-owner', [
            '_maintenance_token' => $this->maintenanceFormToken(),
            'username' => 'boss',
            'password' => 'recovered-pass-1',
            'password_confirmation' => 'recovered-pass-1',
        ])->assertNotFound();
    }

    public function test_owner_menu_links_to_the_maintenance_page(): void
    {
        $this->actingAs($this->owner())->get('/')->assertSee(route('maintenance'), false);
    }
}
