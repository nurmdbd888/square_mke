<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    private function attempt(string $login, string $password, string $ip = '203.0.113.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from('/login')
            ->post('/login', ['login' => $login, 'password' => $password]);
    }

    public function test_the_sixth_failed_attempt_within_a_minute_is_blocked(): void
    {
        $this->userWithRole('viewer', ['username' => 'rahim']);

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('rahim', 'wrong-'.$i)
                ->assertSessionHasErrors(['login' => 'The user name or password is not correct.']);
        }

        $this->attempt('rahim', 'wrong-6')
            ->assertSessionHasErrors(['login' => 'Too many sign-in attempts. Try again in 60 seconds.']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.lockout']);
    }

    public function test_blocked_user_cannot_sign_in_even_with_the_right_password(): void
    {
        $this->userWithRole('viewer', ['username' => 'rahim']);

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('rahim', 'wrong-'.$i);
        }

        $this->attempt('rahim', 'correct-horse-battery')
            ->assertSessionHasErrors(['login' => 'Too many sign-in attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_the_block_lifts_after_a_minute(): void
    {
        $this->userWithRole('viewer', ['username' => 'rahim']);

        for ($i = 1; $i <= 6; $i++) {
            $this->attempt('rahim', 'wrong-'.$i);
        }

        $this->travel(61)->seconds();

        $this->attempt('rahim', 'correct-horse-battery')->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_the_limit_is_per_user_name_and_ip(): void
    {
        $this->userWithRole('viewer', ['username' => 'rahim']);

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('rahim', 'wrong-'.$i);
        }

        $this->attempt('rahim', 'correct-horse-battery', '198.51.100.7')->assertRedirect(route('dashboard'));
    }

    public function test_one_ip_cannot_guess_many_accounts(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->attempt('user'.$i, 'wrong-password');
        }

        $this->attempt('user21', 'wrong-password')
            ->assertSessionHasErrors(['login' => 'Too many sign-in attempts. Try again in 60 seconds.']);
    }

    public function test_a_successful_sign_in_resets_the_counter(): void
    {
        $user = $this->userWithRole('viewer', ['username' => 'rahim']);

        for ($i = 1; $i <= 4; $i++) {
            $this->attempt('rahim', 'wrong-'.$i);
        }

        $this->attempt('rahim', 'correct-horse-battery');
        $this->actingAs($user)->post('/logout');

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('rahim', 'wrong-'.$i)
                ->assertSessionHasErrors(['login' => 'The user name or password is not correct.']);
        }
    }
}
