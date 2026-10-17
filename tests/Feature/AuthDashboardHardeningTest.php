<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthDashboardHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dashboard_loads_even_if_collection_columns_missing(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Dashboard'), false);
    }

    public function test_login_redirects_to_dashboard_not_collections(): void
    {
        $collector = $this->userWithRole('collector', [
            'username' => 'fieldman',
            'password' => 'correct-horse-battery',
        ]);

        $this->post('/login', [
            'login' => 'fieldman',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($collector)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_collections_page_does_not_500_for_collector(): void
    {
        $collector = $this->userWithRole('collector');

        $this->actingAs($collector)
            ->get(route('collector.index'))
            ->assertOk();
    }
}
