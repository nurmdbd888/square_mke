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
            ['health.view', 'users.view', 'audit.view', 'settings.view', 'products.view', 'retailers.view', 'masters.view'],
            Role::findByName('accountant')->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(['products.view', 'retailers.view'], Role::findByName('sr')->permissions->pluck('name')->all());
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
                'accountant' => ['health.view', 'users.view', 'audit.view', 'settings.view', 'products.view', 'retailers.view', 'masters.view'],
                'sr' => ['settings.view'],
                'data_entry' => ['products.view', 'products.manage', 'retailers.view', 'retailers.manage', 'masters.view', 'masters.manage'],
                'viewer' => ['settings.view', 'products.view', 'retailers.view'],
            ],
        ])->assertRedirect(route('roles.index'))->assertSessionHas('status', 'Permissions saved.');

        $this->assertSame(['settings.view'], Role::findByName('sr')->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            ['products.view', 'products.manage', 'retailers.view', 'retailers.manage', 'masters.view', 'masters.manage'],
            Role::findByName('data_entry')->permissions->pluck('name')->all(),
        );

        $log = AuditLog::query()->where('event', 'role.permissions_changed')->sole();
        $this->assertSame('role', $log->auditable_type);
        $this->assertSame(['permissions' => ['products.view', 'retailers.view']], $log->old_values);
        $this->assertSame(['permissions' => ['settings.view']], $log->new_values);
        $this->assertSame($owner->id, $log->user_id);
    }

    public function test_unchanged_matrix_writes_no_audit_row(): void
    {
        $this->actingAs($this->owner())->put('/roles', [
            'permissions' => [
                'accountant' => ['health.view', 'users.view', 'audit.view', 'settings.view', 'products.view', 'retailers.view', 'masters.view'],
                'sr' => ['products.view', 'retailers.view'],
                'data_entry' => ['products.view', 'products.manage', 'retailers.view', 'retailers.manage', 'masters.view', 'masters.manage'],
                'viewer' => ['settings.view', 'products.view', 'retailers.view'],
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
