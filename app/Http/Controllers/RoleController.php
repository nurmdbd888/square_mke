<?php

namespace App\Http\Controllers;

use App\Support\Access\PermissionRegistry;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Permission matrix: which role may do what. The owner column is fixed (the
 * owner always has every permission).
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()->with('permissions')->whereIn('name', PermissionRegistry::ROLES)->get()->keyBy('name');

        return view('roles.index', [
            'roles' => collect(PermissionRegistry::ROLES)->map(fn ($name) => $roles->get($name))->filter(),
            'groups' => PermissionRegistry::PERMISSIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $editable = array_values(array_diff(PermissionRegistry::ROLES, [PermissionRegistry::OWNER]));

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', Rule::in(PermissionRegistry::all())],
        ]);

        $changed = 0;

        DB::transaction(function () use ($editable, $data, &$changed) {
            foreach ($editable as $name) {
                $role = Role::findByName($name);
                $old = $role->permissions->pluck('name')->sort()->values()->all();
                $new = collect($data['permissions'][$name] ?? [])->unique()->sort()->values()->all();

                if ($old === $new) {
                    continue;
                }

                $role->syncPermissions($new);
                $changed++;

                AuditLogger::log('role.permissions_changed', $role, ['permissions' => $old], ['permissions' => $new]);
            }
        });

        return redirect()->route('roles.index')->with('status', $changed > 0 ? __('Permissions saved.') : __('Nothing changed.'));
    }
}
