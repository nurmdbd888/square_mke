<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\SmokeHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_the_firm_name_version_and_status(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('M/s Khaza Enterprise')
            ->assertSee('Square Dealer Manager')
            ->assertSee('4831')
            ->assertSeeInOrder(['data-testid="version"', 'dev'], false)
            ->assertSeeInOrder(['data-testid="database"', 'Connected'], false)
            ->assertSeeInOrder(['data-testid="cron"', 'No heartbeat yet'], false);
    }

    public function test_firm_name_comes_from_configuration(): void
    {
        config(['sdm.firm_name' => 'Test Traders']);

        $this->get('/')->assertOk()->assertSee('Test Traders');
    }

    public function test_home_page_shows_a_fresh_cron_heartbeat(): void
    {
        SmokeHeartbeat::create(['ran_at' => now()->subMinute(), 'php_version' => PHP_VERSION]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('No heartbeat yet');
    }

    public function test_bangla_is_used_when_the_locale_cookie_says_bn(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'bn')
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="bn">', false)
            ->assertSee('স্কয়ার ডিলার ম্যানেজার');
    }

    public function test_unknown_locale_cookie_falls_back_to_english(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'xx')
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="en">', false);
    }

    public function test_locale_switch_sets_a_cookie_and_redirects_back(): void
    {
        $this->get('/locale/bn', ['referer' => url('/maintenance')])
            ->assertRedirect(url('/maintenance'))
            ->assertCookie(SetLocale::COOKIE, 'bn', false);
    }

    public function test_locale_switch_ignores_foreign_referers(): void
    {
        $this->get('/locale/en', ['referer' => 'https://evil.example/'])
            ->assertRedirect(url('/'));
    }

    public function test_unsupported_locale_is_not_found(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }

    public function test_missing_app_key_shows_the_setup_page_instead_of_an_error(): void
    {
        config(['app.key' => '']);
        $this->app->forgetInstance('encrypter');

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Setup is not finished yet')
            ->assertSee(route('maintenance'), false);
    }
}
