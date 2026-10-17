<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel skips the CSRF check while running tests; this puts it back.
     */
    private function enforceCsrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn (Application $app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_forged_post_without_a_token_is_rejected(): void
    {
        $this->enforceCsrf();
        $this->userWithRole('viewer', ['username' => 'rahim']);

        $this->post('/login', ['login' => 'rahim', 'password' => 'correct-horse-battery'], ['Sec-Fetch-Site' => 'cross-site'])
            ->assertStatus(419);
        $this->assertGuest();
    }

    public function test_forged_post_by_a_signed_in_user_is_rejected(): void
    {
        $this->enforceCsrf();
        $owner = $this->owner();

        $this->actingAs($owner)->put('/settings', ['settings' => ['firm_name' => 'Hacked']], ['Sec-Fetch-Site' => 'cross-site'])
            ->assertStatus(419);
        $this->assertDatabaseMissing('settings', ['key' => 'firm_name']);
    }

    public function test_wrong_token_is_rejected(): void
    {
        $this->enforceCsrf();
        $this->actingAs($this->owner())
            ->withSession(['_token' => 'real-token'])
            ->post('/logout', ['_token' => 'guessed-token'])
            ->assertStatus(419);
    }

    public function test_form_token_is_accepted(): void
    {
        $this->enforceCsrf();
        $this->userWithRole('viewer', ['username' => 'rahim']);

        $this->withSession(['_token' => 'real-token'])
            ->post('/login', ['_token' => 'real-token', 'login' => 'rahim', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_htmx_header_token_is_accepted(): void
    {
        $this->enforceCsrf();
        $this->actingAs($this->owner())
            ->withSession(['_token' => 'real-token'])
            ->post('/logout', [], ['X-CSRF-TOKEN' => 'real-token', 'HX-Request' => 'true'])
            ->assertRedirect(route('login'));
    }

    public function test_every_page_form_has_a_csrf_field_and_the_layout_has_the_meta_tag(): void
    {
        $response = $this->actingAs($this->owner())->get('/users/create')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<meta name="csrf-token"', $html);
        preg_match_all('/<form\b[^>]*method="POST"[^>]*>(.*?)<\/form>/is', $html, $forms);
        $this->assertNotEmpty($forms[1]);

        foreach ($forms[1] as $body) {
            $this->assertStringContainsString('name="_token"', $body);
        }
    }

    public function test_app_js_sends_the_csrf_token_with_htmx_requests(): void
    {
        $js = file_get_contents(public_path('js/app.js'));

        $this->assertStringContainsString('htmx:configRequest', $js);
        $this->assertStringContainsString('X-CSRF-TOKEN', $js);
    }

    public function test_session_cookie_is_secure_http_only_and_same_site_lax(): void
    {
        config(['session.secure' => true]);

        $cookie = collect($this->get('/login')->headers->getCookies())
            ->first(fn (Cookie $c) => $c->getName() === 'sdm_session');

        $this->assertNotNull($cookie, 'session cookie is named sdm_session');
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_session_cookie_name_does_not_depend_on_app_name(): void
    {
        $this->assertSame('sdm_session', config('session.cookie'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue(config('session.http_only'));
    }

    public function test_pages_are_not_indexed(): void
    {
        $this->actingAs($this->owner())->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
