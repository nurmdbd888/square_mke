<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google2fa = new Google2FA;
    }

    private function userWithTwoFactor(array $attributes = []): User
    {
        $user = $this->userWithRole('accountant', ['username' => 'rahim'] + $attributes);
        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(32),
            'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->fresh();
    }

    private function passwordStep(): void
    {
        $this->post('/login', ['login' => 'rahim', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    public function test_user_sets_up_two_factor_from_the_profile(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->post('/profile/two-factor')->assertRedirect(route('profile').'#two-factor');
        $secret = session('two_factor.setup_secret');
        $this->assertIsString($secret);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('<svg', false)->assertSee(trim(chunk_split($secret, 4, ' ')));

        $this->actingAs($user)->post('/profile/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->post('/profile/two-factor/confirm', ['code' => $this->google2fa->getCurrentOtp($secret)])
            ->assertSessionHas('two_factor.recovery_codes');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->assertNotSame($secret, $user->getRawOriginal('two_factor_secret'), 'secret is stored encrypted');
        $this->assertDatabaseHas('audit_logs', ['event' => 'two_factor.enabled', 'auditable_id' => $user->id]);
    }

    public function test_sign_in_with_an_authenticator_code(): void
    {
        $user = $this->userWithTwoFactor();
        $this->passwordStep();

        $this->get('/two-factor-challenge')->assertOk()->assertSee('Two-factor sign-in');

        $this->post('/two-factor-challenge', ['code' => $this->google2fa->getCurrentOtp($user->two_factor_secret)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_code_is_refused_and_audited(): void
    {
        $this->userWithTwoFactor();
        $this->passwordStep();

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'two_factor.failed']);
    }

    public function test_a_used_code_cannot_be_replayed(): void
    {
        $user = $this->userWithTwoFactor();
        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);

        $this->passwordStep();
        $this->post('/two-factor-challenge', ['code' => $code]);
        $this->assertAuthenticated();
        $this->post('/logout');

        $this->passwordStep();
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        $user = $this->userWithTwoFactor();

        $this->passwordStep();
        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['CCCCC-DDDDD'], $user->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['event' => 'two_factor.recovery_used']);
        $this->post('/logout');

        $this->passwordStep();
        $this->post('/two-factor-challenge', ['recovery_code' => 'AAAAA-BBBBB'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_challenge_needs_the_password_step_first(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_challenge_expires_after_five_minutes(): void
    {
        $user = $this->userWithTwoFactor();
        $this->passwordStep();

        $this->travel(6)->minutes();

        $this->post('/two-factor-challenge', ['code' => $this->google2fa->getCurrentOtp($user->two_factor_secret)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_challenge_is_rate_limited(): void
    {
        $user = $this->userWithTwoFactor();
        $this->passwordStep();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000']);
        }

        $this->post('/two-factor-challenge', ['code' => $this->google2fa->getCurrentOtp($user->two_factor_secret)])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_turning_two_factor_off_needs_the_password(): void
    {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)->delete('/profile/two-factor', ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->delete('/profile/two-factor', ['current_password' => 'correct-horse-battery']);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['event' => 'two_factor.disabled']);
    }

    public function test_new_recovery_codes_replace_the_old_ones(): void
    {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)->post('/profile/two-factor/recovery-codes', ['current_password' => 'correct-horse-battery'])
            ->assertSessionHas('two_factor.recovery_codes');

        $codes = $user->fresh()->two_factor_recovery_codes;
        $this->assertCount(8, $codes);
        $this->assertNotContains('AAAAA-BBBBB', $codes);
    }

    public function test_role_that_requires_two_factor_is_sent_to_set_it_up(): void
    {
        app(SettingsStore::class)->set(['require_2fa_roles' => ['accountant']]);
        $user = $this->userWithRole('accountant');

        $this->actingAs($user)->get('/')->assertRedirect(route('profile'));

        $withTwoFactor = $this->userWithTwoFactor(['username' => 'karim']);
        $this->actingAs($withTwoFactor)->get('/')->assertOk();
    }

    public function test_two_factor_secrets_never_reach_the_audit_log(): void
    {
        $user = $this->userWithTwoFactor();
        $user->update(['name' => 'Changed']);

        foreach (AuditLog::all() as $log) {
            $json = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString($user->two_factor_secret, $json);
            $this->assertStringNotContainsString('AAAAA-BBBBB', $json);
        }
    }
}
