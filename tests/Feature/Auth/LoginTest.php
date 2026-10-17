<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_signs_in_with_user_name_and_is_audited(): void
    {
        $user = $this->userWithRole('accountant', ['username' => 'rahim']);

        $this->post('/login', ['login' => 'Rahim ', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'user_id' => $user->id, 'source' => 'web']);
    }

    public function test_user_signs_in_with_e_mail(): void
    {
        $user = $this->userWithRole('viewer', ['email' => 'karim@example.com']);

        $this->post('/login', ['login' => 'KARIM@example.com', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_refused_and_audited(): void
    {
        $user = $this->userWithRole('viewer', ['username' => 'rahim']);

        $this->from('/login')->post('/login', ['login' => 'rahim', 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login' => 'The user name or password is not correct.']);

        $this->assertGuest();
        $log = AuditLog::query()->where('event', 'auth.failed')->sole();
        $this->assertSame($user->id, $log->auditable_id);
        $this->assertSame('rahim', $log->new_values['login']);
    }

    public function test_unknown_user_gets_the_same_message(): void
    {
        $this->from('/login')->post('/login', ['login' => 'nobody', 'password' => 'whatever-123'])
            ->assertSessionHasErrors(['login' => 'The user name or password is not correct.']);

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_sign_in(): void
    {
        $user = $this->userWithRole('viewer', ['username' => 'gone']);
        $user->delete();

        $this->post('/login', ['login' => 'gone', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_session_id_changes_on_sign_in(): void
    {
        $this->userWithRole('viewer', ['username' => 'rahim']);
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['login' => 'rahim', 'password' => 'correct-horse-battery']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_signed_in_user_is_sent_away_from_the_login_page(): void
    {
        $this->actingAs($this->userWithRole('viewer'))
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_logout_ends_the_session_and_is_audited(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'user_id' => $user->id]);
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_logout_needs_post(): void
    {
        $this->actingAs($this->userWithRole('viewer'))->get('/logout')->assertStatus(405);
    }

    public function test_temporary_password_must_be_changed_first(): void
    {
        $user = $this->userWithRole('accountant', ['must_change_password' => true]);

        $this->actingAs($user)->get('/users')->assertRedirect(route('profile'));
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Please choose a new password before you continue.');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'a-brand-new-secret',
            'password_confirmation' => 'a-brand-new-secret',
        ])->assertRedirect(route('profile'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get('/users')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_changed', 'auditable_id' => $user->id]);
    }

    public function test_password_change_rules(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->from('/profile')->put('/profile/password', [
            'current_password' => 'not-my-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['current_password', 'password']);
    }

    public function test_password_change_does_not_store_the_hash_in_the_audit_log(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'a-brand-new-secret',
            'password_confirmation' => 'a-brand-new-secret',
        ]);

        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('$2y$', json_encode([$log->old_values, $log->new_values]));
        }
    }
}
