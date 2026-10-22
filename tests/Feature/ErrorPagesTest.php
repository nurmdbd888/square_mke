<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_uses_branded_error_page(): void
    {
        $this->get('/this-route-definitely-does-not-exist-'.uniqid())
            ->assertNotFound()
            ->assertSee('data-testid="error-page"', false)
            ->assertSee('data-error-code="404"', false)
            ->assertSee(__('Page not found'), false)
            ->assertSee(__('Square Distributor Manager'), false)
            ->assertSee(__('Back to dashboard'), false)
            ->assertDontSee('Oops! An Error Occurred', false);
    }

    public function test_403_uses_branded_error_page(): void
    {
        Route::middleware('web')->get('/_test-forbidden', fn () => abort(403));

        $this->get('/_test-forbidden')
            ->assertForbidden()
            ->assertSee('data-testid="error-page"', false)
            ->assertSee('data-error-code="403"', false)
            ->assertSee(__('Not allowed'), false);
    }

    public function test_405_uses_branded_error_page(): void
    {
        Route::middleware('web')->get('/_test-method-only-get', fn () => 'ok');

        $this->post('/_test-method-only-get')
            ->assertStatus(405)
            ->assertSee('data-testid="error-page"', false)
            ->assertSee('data-error-code="405"', false)
            ->assertSee(__('Method not allowed'), false)
            ->assertDontSee('Oops! An Error Occurred', false);
    }

    public function test_sales_index_is_mobile_friendly_markup(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('sales.index'))
            ->assertOk()
            ->assertSee('w-full sm:w-auto', false)
            ->assertSee('flex-col gap-3 sm:flex-row', false);
    }
}
