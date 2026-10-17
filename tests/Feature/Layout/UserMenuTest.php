<?php

namespace Tests\Feature\Layout;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_renders_user_menu_closed_by_default(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-testid="user-menu"', false)
            ->assertSee('data-testid="user-menu-panel"', false)
            ->assertSee('userMenuOpen: false', false)
            ->assertSee('x-show="userMenuOpen"', false)
            ->assertSee('@click.outside="userMenuOpen = false"', false)
            ->assertSee('@keydown.escape.window="userMenuOpen = false"', false)
            ->assertSee('id="user-menu-panel"', false);
    }

    public function test_security_headers_allow_alpine_eval(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringContainsString("'unsafe-eval'", (string) $response->headers->get('Content-Security-Policy'));
    }
}
