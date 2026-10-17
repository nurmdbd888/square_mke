<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_shows_the_product_firm_code_and_version(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Square Distributor Manager')
            ->assertSee('M/s Khaza Enterprise')
            ->assertSee('4831')
            ->assertSee('Version');
    }

    public function test_login_page_tells_the_owner_to_create_an_account_first(): void
    {
        $this->get('/login')
            ->assertSeeInOrder(['data-testid="setup-notice"', 'No owner account exists yet'], false);

        $this->owner();

        $this->get('/login')->assertDontSee('data-testid="setup-notice"', false);
    }

    public function test_firm_name_falls_back_to_configuration(): void
    {
        config(['sdm.firm_name' => 'Test Traders']);

        $this->get('/login')->assertOk()->assertSee('Test Traders');
    }

    public function test_bangla_is_used_when_the_locale_cookie_says_bn(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'bn')
            ->get('/login')
            ->assertOk()
            ->assertSee('<html lang="bn">', false)
            ->assertSee('সাইন ইন')
            ->assertSee('৪৮৩১');
    }

    public function test_product_name_stays_in_english_in_the_bangla_interface(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'bn')
            ->get('/login')
            ->assertSee('Square Distributor Manager');

        $this->assertSame('Square Distributor Manager', trans('Square Distributor Manager', [], 'bn'));
    }

    public function test_unknown_locale_cookie_falls_back_to_english(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'xx')
            ->get('/login')
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

        $this->get('/login')
            ->assertStatus(503)
            ->assertSee('Setup is not finished yet')
            ->assertSee(route('maintenance'), false);
    }
}
