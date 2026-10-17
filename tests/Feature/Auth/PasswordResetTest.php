<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_smtp_the_page_says_to_ask_the_owner_and_sends_nothing(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $this->userWithRole('viewer', ['email' => 'rahim@example.com']);

        $this->get('/forgot-password')->assertOk()->assertSee('Ask the owner');

        $this->post('/forgot-password', ['email' => 'rahim@example.com'])->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_with_smtp_a_link_is_mailed_and_resets_the_password(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $user = $this->userWithRole('viewer', ['email' => 'rahim@example.com', 'locale' => 'bn', 'must_change_password' => true]);

        $this->post('/forgot-password', ['email' => 'Rahim@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return $n->locale === 'bn';
        });

        $this->get("/reset-password/{$token}?email=rahim@example.com")->assertOk();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'rahim@example.com',
            'password' => 'my-new-password-1',
            'password_confirmation' => 'my-new-password-1',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('my-new-password-1', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_reset', 'auditable_id' => $user->id]);
    }

    public function test_unknown_address_gets_the_same_answer(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', 'If that e-mail address belongs to a user, a reset link is on its way. No e-mail? Ask the owner to reset your password.');
        Notification::assertNothingSent();
    }

    public function test_bad_token_is_refused(): void
    {
        $user = $this->userWithRole('viewer', ['email' => 'rahim@example.com']);

        $this->from('/reset-password/bad')->post('/reset-password', [
            'token' => 'bad',
            'email' => 'rahim@example.com',
            'password' => 'my-new-password-1',
            'password_confirmation' => 'my-new-password-1',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('correct-horse-battery', $user->fresh()->password));
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'x@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'x@example.com'])->assertStatus(429);
    }
}
