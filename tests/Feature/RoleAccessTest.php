<?php

namespace Tests\Feature;

use App\Support\Access\PermissionRegistry;
use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Each role sees only the pages its default permissions allow.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Page => roles that may open it (default permission matrix).
     */
    private const PAGES = [
        '/' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        '/profile' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        '/health' => ['owner', 'accountant'],
        '/users' => ['owner', 'accountant'],
        '/users/create' => ['owner'],
        '/roles' => ['owner'],
        '/settings' => ['owner', 'accountant', 'viewer'],
        '/audit' => ['owner', 'accountant'],
        '/products' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        '/products/create' => ['owner', 'data_entry'],
        '/retailers' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        '/retailers/create' => ['owner', 'data_entry'],
        '/masters' => ['owner', 'accountant', 'data_entry'],
        '/masters/suppliers' => ['owner', 'accountant', 'data_entry'],
        '/masters/suppliers/create' => ['owner', 'data_entry'],
        '/masters/price-lists' => ['owner', 'accountant', 'data_entry'],
        '/masters/price-lists/create' => ['owner', 'data_entry'],
        '/masters/opening-balances' => ['owner', 'accountant', 'data_entry'],
        '/masters/custom-fields' => ['owner', 'accountant', 'data_entry'],
        '/purchases' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/purchases/create' => ['owner', 'accountant', 'data_entry'],
        '/sales' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        '/sales/create' => ['owner', 'accountant', 'sr', 'data_entry'],
        '/stock' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/returns' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        '/returns/create' => ['owner', 'accountant', 'sr', 'data_entry'],
        '/claims' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/claims/create' => ['owner', 'accountant', 'data_entry'],
        '/stock/comparison' => ['owner', 'accountant', 'data_entry'],
        '/payments' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        '/payments/create?kind=receipt' => ['owner', 'accountant', 'sr', 'data_entry'],
        '/accounts/receivables' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/accounts/payables' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/accounts/cash-book' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/accounts/bank-book' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/accounts/day-book' => ['owner', 'accountant', 'data_entry', 'viewer'],
        '/accounts/closings' => ['owner', 'accountant', 'data_entry', 'viewer'],
    ];

    /**
     * Navigation entry => roles that see it.
     */
    private const NAV = [
        'dashboard' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        'products.index' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        'retailers.index' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        'masters.index' => ['owner', 'accountant', 'data_entry'],
        'purchases.index' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'sales.index' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        'stock.index' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'returns.index' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        'claims.index' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'returns.index' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        'claims.index' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'payments.index' => ['owner', 'accountant', 'sr', 'data_entry', 'viewer'],
        'accounts.receivables' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'accounts.payables' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'accounts.cash-book' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'accounts.bank-book' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'accounts.day-book' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'accounts.closings' => ['owner', 'accountant', 'data_entry', 'viewer'],
        'users.index' => ['owner', 'accountant'],
        'roles.index' => ['owner'],
        'settings.index' => ['owner', 'accountant', 'viewer'],
        'audit.index' => ['owner', 'accountant'],
        'health' => ['owner', 'accountant'],
    ];

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function pageMatrix(): array
    {
        $cases = [];

        foreach (self::PAGES as $page => $allowed) {
            foreach (PermissionRegistry::ROLES as $role) {
                $cases["{$role} {$page}"] = [$role, $page, in_array($role, $allowed, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('pageMatrix')]
    public function test_role_page_access(string $role, string $page, bool $allowed): void
    {
        $response = $this->actingAs($this->userWithRole($role))->get($page);

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return array_combine(PermissionRegistry::ROLES, array_map(fn ($r) => [$r], PermissionRegistry::ROLES));
    }

    #[DataProvider('roles')]
    public function test_navigation_shows_only_permitted_pages(string $role): void
    {
        $response = $this->actingAs($this->userWithRole($role))->get('/')->assertOk();

        foreach (self::NAV as $route => $allowed) {
            in_array($role, $allowed, true)
                ? $response->assertSee('data-nav="'.$route.'"', false)
                : $response->assertDontSee('data-nav="'.$route.'"', false);
        }

        $role === 'owner'
            ? $response->assertSee(route('maintenance'), false)
            : $response->assertDontSee(route('maintenance'), false);
    }

    public function test_write_actions_are_refused_without_permission(): void
    {
        $accountant = $this->userWithRole('accountant');
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($accountant)->post('/users', [])->assertForbidden();
        $this->actingAs($accountant)->put('/roles', [])->assertForbidden();
        $this->actingAs($accountant)->put('/settings', [])->assertForbidden();
        $this->actingAs($accountant)->post('/products', [])->assertForbidden();
        $this->actingAs($this->userWithRole('sr'))->post('/retailers', [])->assertForbidden();
        $this->actingAs($accountant)->post('/masters/suppliers', [])->assertForbidden();
        $this->actingAs($viewer)->post('/purchases', [])->assertForbidden();
        $this->actingAs($viewer)->post('/sales', [])->assertForbidden();
        $this->actingAs($this->userWithRole('sr'))->get('/purchases')->assertForbidden();
        $this->actingAs($viewer)->post('/payments', [])->assertForbidden();
        $this->actingAs($this->userWithRole('sr'))->get('/accounts/receivables')->assertForbidden();
        $this->actingAs($this->userWithRole('data_entry'))->post('/accounts/closings', [])->assertForbidden();
        $log = AuditLogger::log('auth.failed');
        $this->actingAs($viewer)->get("/audit/{$log->id}")->assertForbidden();
    }

    public function test_viewer_sees_settings_read_only(): void
    {
        $this->actingAs($this->userWithRole('viewer'))
            ->get('/settings')
            ->assertOk()
            ->assertSee('<fieldset disabled', false)
            ->assertDontSee('Save settings');
    }

    public function test_granting_a_permission_opens_the_page(): void
    {
        $sr = $this->userWithRole('sr');
        $this->actingAs($sr)->get('/audit')->assertForbidden();

        Role::findByName('sr')->givePermissionTo('audit.view');

        $this->actingAs($sr->fresh())->get('/audit')->assertOk();
    }

    public function test_dashboard_shows_the_fiscal_year(): void
    {
        $this->travelTo('2026-10-17 10:00:00');

        $this->actingAs($this->userWithRole('viewer'))->get('/')
            ->assertSeeInOrder(['data-testid="fiscal-year"', '2026-27'], false);
    }
}
