<?php

namespace Tests\Feature;

use App\Support\Maintenance\MaintenanceGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceAccessTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-maintenance-token-0123456789';

    public function test_page_is_not_found_when_disabled(): void
    {
        config(['sdm.maintenance.enabled' => false]);

        $this->get('/maintenance')->assertNotFound();
        $this->post('/maintenance/login', ['token' => self::TOKEN])->assertNotFound();
    }

    public function test_page_is_locked_until_a_long_enough_token_is_configured(): void
    {
        config(['sdm.maintenance.token' => 'short']);

        $this->get('/maintenance')
            ->assertForbidden()
            ->assertSee('The Maintenance page is locked');

        $this->post('/maintenance/login', ['token' => 'short'])->assertForbidden();
    }

    public function test_guest_sees_the_login_form_and_no_checks(): void
    {
        $this->get('/maintenance')
            ->assertOk()
            ->assertSee('Secret phrase')
            ->assertDontSee('data-check=', false);
    }

    public function test_wrong_token_is_rejected(): void
    {
        $this->post('/maintenance/login', ['token' => 'wrong-token-wrong-token-wrong'])
            ->assertStatus(422)
            ->assertSee('That secret phrase is not correct.')
            ->assertCookieMissing(MaintenanceGate::COOKIE);
    }

    public function test_correct_token_sets_the_access_cookie(): void
    {
        $response = $this->post('/maintenance/login', ['token' => self::TOKEN])
            ->assertRedirect(route('maintenance'));

        $cookie = $response->getCookie(MaintenanceGate::COOKIE, false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());

        $this->withUnencryptedCookie(MaintenanceGate::COOKIE, $cookie->getValue())
            ->get('/maintenance')
            ->assertOk()
            ->assertSee('data-check="php_version"', false);
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/maintenance/login', ['token' => 'wrong-token-wrong-token-'.$i])->assertStatus(422);
        }

        $this->post('/maintenance/login', ['token' => self::TOKEN])
            ->assertStatus(429)
            ->assertCookieMissing(MaintenanceGate::COOKIE);
    }

    public function test_tampered_cookie_is_rejected(): void
    {
        $this->withUnencryptedCookie(MaintenanceGate::COOKIE, (time() + 3600).'.'.str_repeat('a', 64))
            ->get('/maintenance')
            ->assertOk()
            ->assertSee('Secret phrase');
    }

    public function test_expired_cookie_is_rejected(): void
    {
        $expiry = time() - 10;
        $value = $expiry.'.'.hash_hmac('sha256', 'access|'.$expiry, self::TOKEN);

        $this->withUnencryptedCookie(MaintenanceGate::COOKIE, $value)
            ->get('/maintenance')
            ->assertSee('Secret phrase');
    }

    public function test_changing_the_token_logs_everybody_out(): void
    {
        $cookie = $this->maintenanceCookie();
        config(['sdm.maintenance.token' => 'a-completely-different-token-value']);

        $this->withUnencryptedCookie(MaintenanceGate::COOKIE, $cookie)
            ->get('/maintenance')
            ->assertSee('Secret phrase');
    }

    public function test_actions_need_the_access_cookie(): void
    {
        $this->post('/maintenance/migrate')->assertRedirect(route('maintenance'));
        $this->post('/maintenance/generate-key')->assertRedirect(route('maintenance'));
        $this->post('/maintenance/clear-caches')->assertRedirect(route('maintenance'));
    }

    public function test_actions_need_the_form_token(): void
    {
        $this->asMaintenanceUser()
            ->post('/maintenance/migrate', ['_maintenance_token' => 'forged'])
            ->assertStatus(419);
    }

    public function test_logout_clears_the_cookie(): void
    {
        $this->asMaintenanceUser()
            ->post('/maintenance/logout')
            ->assertRedirect(route('maintenance'))
            ->assertCookieExpired(MaintenanceGate::COOKIE);
    }

    public function test_page_works_without_an_app_key(): void
    {
        config(['app.key' => '']);
        $this->app->forgetInstance('encrypter');

        $this->get('/maintenance')->assertOk()->assertSee('Secret phrase');

        $this->asMaintenanceUser()
            ->get('/maintenance')
            ->assertOk()
            ->assertSee('data-check="app_key" data-status="fail"', false);
    }
}
