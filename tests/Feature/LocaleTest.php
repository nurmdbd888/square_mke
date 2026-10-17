<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_preference_wins_over_the_cookie(): void
    {
        $user = $this->userWithRole('viewer', ['locale' => 'bn']);

        $this->actingAs($user)->withUnencryptedCookie(SetLocale::COOKIE, 'en')->get('/')
            ->assertSee('<html lang="bn">', false)
            ->assertSee('ড্যাশবোর্ড');
    }

    public function test_language_switch_saves_the_user_preference(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->from('/settings')->post('/profile/locale/bn')
            ->assertRedirect('/settings')
            ->assertCookie(SetLocale::COOKIE, 'bn', false);

        $this->assertSame('bn', $user->fresh()->locale);
        $this->actingAs($user->fresh())->get('/settings')->assertSee('<html lang="bn">', false);
        $this->actingAs($user)->post('/profile/locale/fr')->assertNotFound();
    }

    public function test_default_language_setting_applies_to_guests(): void
    {
        app(SettingsStore::class)->set(['default_locale' => 'bn']);

        $this->get('/login')->assertSee('<html lang="bn">', false);
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'en')->get('/login')->assertSee('<html lang="en">', false);
    }

    public function test_default_language_setting_applies_to_users_without_a_choice(): void
    {
        app(SettingsStore::class)->set(['default_locale' => 'bn']);

        $this->actingAs($this->userWithRole('viewer'))->get('/')->assertSee('<html lang="bn">', false);
    }

    public function test_bangla_view_uses_bangla_digits_roles_and_the_self_hosted_font(): void
    {
        $this->travelTo('2026-10-17');
        $user = $this->userWithRole('accountant', ['locale' => 'bn']);

        $this->actingAs($user)->get('/')
            ->assertSee('২০২৬-২৭')
            ->assertSee('হিসাবরক্ষক');

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('noto-sans-bengali-400.woff2', $css);
        $this->assertFileExists(public_path('fonts/noto-sans-bengali-400.woff2'));
        $this->assertFileExists(public_path('fonts/OFL.txt'));
    }

    public function test_validation_messages_are_translated(): void
    {
        $this->assertSame('x দিতে হবে।', trans('validation.required', ['attribute' => 'x'], 'bn'));
        $this->assertSame('The x field is required.', trans('validation.required', ['attribute' => 'x'], 'en'));
    }

    public function test_helpers_follow_the_current_locale(): void
    {
        app()->setLocale('bn');
        $this->assertSame('১২,৩৪,৫৬৭.৮৯', format_number('1234567.89'));
        $this->assertSame('৳ ১,০০০.০০', format_money('1000'));
        $this->assertSame('৪৮৩১', local_digits('4831'));

        app()->setLocale('en');
        $this->assertSame('12,34,567.89', format_number('1234567.89'));
    }
}
