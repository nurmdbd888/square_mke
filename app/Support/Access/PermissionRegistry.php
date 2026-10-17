<?php

namespace App\Support\Access;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The fixed list of roles and the permissions each milestone adds.
 *
 * sync() is idempotent and runs from a migration, so "Run migrations" on the
 * Maintenance page is all an update needs. A permission is granted to its
 * default roles only when it is first created: later edits the owner makes
 * in the permission matrix are never overwritten.
 */
class PermissionRegistry
{
    public const OWNER = 'owner';

    public const ROLES = ['owner', 'accountant', 'sr', 'data_entry', 'viewer'];

    /**
     * group => [permission => roles that get it by default (owner always has all)].
     *
     * @var array<string, array<string, list<string>>>
     */
    public const PERMISSIONS = [
        'general' => [
            'health.view' => ['accountant'],
        ],
        'users' => [
            'users.view' => ['accountant'],
            'users.manage' => [],
            'roles.manage' => [],
        ],
        'settings' => [
            'settings.view' => ['accountant', 'viewer'],
            'settings.manage' => [],
        ],
        'audit' => [
            'audit.view' => ['accountant'],
        ],
        'master' => [
            'products.view' => ['accountant', 'sr', 'data_entry', 'viewer'],
            'products.manage' => ['data_entry'],
            'retailers.view' => ['accountant', 'sr', 'data_entry', 'viewer'],
            'retailers.manage' => ['data_entry'],
            'masters.view' => ['accountant', 'data_entry'],
            'masters.manage' => ['data_entry'],
        ],
        'documents' => [
            'purchases.view' => ['accountant', 'data_entry', 'viewer'],
            'purchases.manage' => ['accountant', 'data_entry'],
            'sales.view' => ['accountant', 'sr', 'data_entry', 'viewer'],
            'sales.manage' => ['accountant', 'sr', 'data_entry'],
            'stock.view' => ['accountant', 'data_entry', 'viewer'],
            'stock.manage' => ['accountant', 'data_entry'],
            'returns.view' => ['accountant', 'sr', 'data_entry', 'viewer'],
            'returns.manage' => ['accountant', 'sr', 'data_entry'],
            'claims.view' => ['accountant', 'data_entry', 'viewer'],
            'claims.manage' => ['accountant', 'data_entry'],
            'stock.compare' => ['accountant', 'data_entry'],
        ],
        'accounts' => [
            'payments.view' => ['accountant', 'sr', 'data_entry', 'viewer'],
            'payments.manage' => ['accountant', 'sr', 'data_entry'],
            'accounts.view' => ['accountant', 'data_entry', 'viewer'],
            'accounts.manage' => ['accountant'],
        ],
        'reports' => [
            'reports.view' => ['accountant', 'data_entry', 'viewer'],
            'reports.manage' => ['accountant'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(...array_map('array_keys', array_values(self::PERMISSIONS)))));
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public function sync(): array
    {
        $created = ['roles' => [], 'permissions' => []];
        $guard = config('auth.defaults.guard');

        DB::transaction(function () use (&$created, $guard) {
            $roles = [];

            foreach (self::ROLES as $name) {
                $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);
                $roles[$name] = $role;

                if ($role->wasRecentlyCreated) {
                    $created['roles'][] = $name;
                }
            }

            foreach (self::PERMISSIONS as $permissions) {
                foreach ($permissions as $name => $defaultRoles) {
                    $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);

                    if (! $permission->wasRecentlyCreated) {
                        continue;
                    }

                    $created['permissions'][] = $name;

                    foreach ([self::OWNER, ...$defaultRoles] as $roleName) {
                        $roles[$roleName]->givePermissionTo($permission);
                    }
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
