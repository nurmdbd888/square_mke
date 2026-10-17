<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Support\Access\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_every_role_and_permission(): void
    {
        $this->assertEqualsCanonicalizing(PermissionRegistry::ROLES, Role::query()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(PermissionRegistry::all(), Permission::query()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            [
                'health.view', 'users.view', 'audit.view', 'settings.view',
                'products.view', 'retailers.view', 'masters.view',
                'purchases.view', 'purchases.manage', 'sales.view', 'sales.manage', 'stock.view', 'stock.manage', 'stock.compare',
                'returns.view', 'returns.manage', 'claims.view', 'claims.manage',
                'payments.view', 'payments.manage', 'accounts.view', 'accounts.manage',
                'reports.view', 'reports.manage', 'exports.view', 'google.manage',
            ],
            Role::findByName('accountant')->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [
                'products.view', 'retailers.view', 'sales.view', 'sales.manage',
                'payments.view', 'payments.manage', 'returns.view', 'returns.manage',
            ],
            Role::findByName('sr')->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [
                'products.view', 'products.manage', 'retailers.view', 'retailers.manage', 'masters.view', 'masters.manage',
                'purchases.view', 'purchases.manage', 'sales.view', 'sales.manage', 'stock.view', 'stock.manage', 'stock.compare',
                'returns.view', 'returns.manage', 'claims.view', 'claims.manage',
                'payments.view', 'payments.manage', 'accounts.view',
                'reports.view', 'exports.view',
            ],
            Role::findByName('data_entry')->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [
                'settings.view', 'products.view', 'retailers.view', 'purchases.view', 'sales.view', 'stock.view',
                'returns.view', 'claims.view', 'payments.view', 'accounts.view',
                'reports.view', 'exports.view',
            ],
            Role::findByName('viewer')->permissions->pluck('name')->all(),
        );
    }

    public function test_sync_is_idempotent_and_keeps_the_owners_changes(): void
    {
        Role::findByName('accountant')->revokePermissionTo('audit.view');

        app(PermissionRegistry::class)->sync();

        $this->assertFalse(Role::findByName('accountant')->fresh()->hasPermissionTo('audit.view'));
        $this->assertSame(count(PermissionRegistry::all()), Permission::query()->count());
    }

    public function test_matrix_page_lists_roles_and_permissions(): void
    {
        $this->actingAs($this->owner())->get('/roles')
            ->assertOk()
            ->assertSee('Accountant')
            ->assertSee('Data entry')
            ->assertSee('name="permissions[accountant][]"', false)
            ->assertDontSee('name="permissions[owner][]"', false);
    }

    public function test_owner_changes_the_matrix_and_it_is_audited(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put('/roles', [
            'permissions' => [
                'accountant' => [
                    'health.view', 'users.view', 'audit.view', 'settings.view',
                    'products.view', 'retailers.view', 'masters.view',
                    'purchases.view', 'purchases.manage', 'sales.view', 'sales.manage', 'stock.view', 'stock.manage', 'stock.compare',
                    'returns.view', 'returns.manage', 'claims.view', 'claims.manage',
                    'payments.view', 'payments.manage', 'accounts.view', 'accounts.manage',
                    'reports.view', 'reports.manage', 'exports.view', 'google.manage',
                ],
                'sr' => ['settings.view'],
                'data_entry' => [
                    'products.view', 'products.manage', 'retailers.view', 'retailers.manage', 'masters.view', 'masters.manage',
                    'purchases.view', 'purchases.manage', 'sales.view', 'sales.manage', 'stock.view', 'stock.manage',
                    'payments.view', 'payments.manage', 'accounts.view',
                    'returns.view', 'returns.manage', 'claims.view', 'claims.manage', 'stock.compare',
                    'reports.view', 'exports.view',
                ],
                'viewer' => [
                    'settings.view', 'products.view', 'retailers.view', 'purchases.view', 'sales.view', 'stock.view',
                    'payments.view', 'accounts.view', 'returns.view', 'claims.view',
                    'reports.view', 'exports.view',
                ],
            ],
        ])->assertRedirect(route('roles.index'))->assertSessionHas('status', 'Permissions saved.');

        $this->assertSame(['settings.view'], Role::findByName('sr')->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            [
                'products.view', 'products.manage', 'retailers.view', 'retailers.manage', 'masters.view', 'masters.manage',
                'purchases.view', 'purchases.manage', 'sales.view', 'sales.manage', 'stock.view', 'stock.manage',
                'payments.view', 'payments.manage', 'accounts.view',
                'returns.view', 'returns.manage', 'claims.view', 'claims.manage', 'stock.compare',
                'reports.view', 'exports.view',
            ],
            Role::findByName('data_entry')->permissions->pluck('name')->all(),
        );

        $srRole = Role::findByName('sr');
        $log = AuditLog::query()
            ->where('event', 'role.permissions_changed')
            ->where('auditable_type', 'role')
            ->where('auditable_id', $srRole->id)
            ->sole();
        $this->assertSame('role', $log->auditable_type);
        $this->assertEqualsCanonicalizing(
            ['products.view', 'retailers.view', 'sales.view', 'sales.manage', 'returns.view', 'returns.manage', 'payments.view', 'payments.manage'],
            $log->old_values['permissions'],
        );
        $this->assertSame(['permissions' => ['settings.view']], $log->new_values);
        $this->assertSame($owner->id, $log->user_id);
    }

    public function test_unchanged_matrix_writes_no_audit_row(): void
    {
        $this->actingAs($this->owner())->put('/roles', [
            'permissions' => [
                'accountant' => [
                    'health.view', 'users.view', 'audit.view', 'settings.view',
                    'products.view', 'retailers.view', 'masters.view',
                    'purchases.view', 'purchases.manage', 'sales.view', 'sales.manage', 'stock.view', 'stock.manage', 'stock.compare',
                    'returns.view', 'returns.manage', 'claims.view', 'claims.manage',
                    'payments.view', 'payments.manage', 'accounts.view', 'accounts.manage',
                    'reports.view', 'reports.manage', 'exports.view', 'google.manage',
                ],
                'sr' => ['products.view', 'retailers.view', 'sales.view', 'sales.manage', 'payments.view', 'payments.manage', 'returns.view', 'returns.manage'],
                'data_entry' => [
                    'products.view', 'products.manage', 'retailers.view', 'retailers.manage', 'masters.view', 'masters.manage',
                    'purchases.view', 'purchases.manage', 'sales.view', 'sales.manage', 'stock.view', 'stock.manage',
                    'payments.view', 'payments.manage', 'accounts.view',
                    'returns.view', 'returns.manage', 'claims.view', 'claims.manage', 'stock.compare',
                    'reports.view', 'exports.view',
                ],
                'viewer' => [
                    'settings.view', 'products.view', 'retailers.view', 'purchases.view', 'sales.view', 'stock.view',
                    'payments.view', 'accounts.view', 'returns.view', 'claims.view',
                    'reports.view', 'exports.view',
                ],
            ],
        ])->assertSessionHas('status', 'Nothing changed.');

        $this->assertDatabaseMissing('audit_logs', ['event' => 'role.permissions_changed']);
    }

    public function test_unknown_permission_is_refused(): void
    {
        $this->actingAs($this->owner())->put('/roles', ['permissions' => ['sr' => ['everything']]])
            ->assertSessionHasErrors();
    }

    public function test_owner_keeps_every_permission_whatever_the_matrix_says(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put('/roles', ['permissions' => []]);

        foreach (PermissionRegistry::all() as $permission) {
            $this->assertTrue($owner->can($permission), $permission);
        }
        $this->actingAs($owner)->get('/audit')->assertOk();
    }
}
