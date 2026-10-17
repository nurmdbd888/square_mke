<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Access\PermissionRegistry;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(PermissionRegistry::ROLES)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
        ]);

        $status = $filters['status'] ?? 'active';

        $users = User::query()
            ->with('roles')
            ->when($status === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('username', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted') ? 'users._table' : 'users.index';

        return view($view, ['users' => $users, 'filters' => $filters + ['status' => $status]]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User, 'role' => 'viewer']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normaliseUsername($request);
        $data = $request->validate($this->rules(null) + [
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $this->onlyOwnersTouchOwners($request, null, $data['role']);

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                ...collect($data)->except(['role', 'password_confirmation'])->all(),
                'must_change_password' => true,
            ]);
            $user->assignRole($data['role']);
            AuditLogger::log('user.role_changed', $user, null, ['role' => $data['role']]);

            return $user;
        });

        return redirect()->route('users.index')->with('status', __('User :name was created. They must choose a new password when they first sign in.', ['name' => $user->username]));
    }

    public function edit(Request $request, User $user): View
    {
        $this->onlyOwnersTouchOwners($request, $user);

        return view('users.form', ['user' => $user, 'role' => $user->roleName()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->normaliseUsername($request);
        $data = $request->validate($this->rules($user));
        $this->onlyOwnersTouchOwners($request, $user, $data['role']);
        $oldRole = $user->roleName();

        if ($oldRole === PermissionRegistry::OWNER && $data['role'] !== PermissionRegistry::OWNER && $this->ownerCount() <= 1) {
            return back()->withInput()->withErrors(['role' => __('This is the only owner. Make another user an owner first.')]);
        }

        DB::transaction(function () use ($user, $data, $oldRole) {
            $user->update(collect($data)->except('role')->all());

            if ($oldRole !== $data['role']) {
                $user->syncRoles([$data['role']]);
                AuditLogger::log('user.role_changed', $user, ['role' => $oldRole], ['role' => $data['role']]);
            }
        });

        return redirect()->route('users.index')->with('status', __('Saved.'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->onlyOwnersTouchOwners($request, $user);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->saveQuietly();

        AuditLogger::log('user.password_reset_by_admin', $user);

        return redirect()->route('users.edit', $user)->with('status', __('Password reset. Give the new password to :name; they must change it when they sign in.', ['name' => $user->username]));
    }

    public function resetTwoFactor(Request $request, User $user, TwoFactor $twoFactor): RedirectResponse
    {
        $this->onlyOwnersTouchOwners($request, $user);
        $twoFactor->disable($user);
        AuditLogger::log('user.two_factor_reset', $user);

        return redirect()->route('users.edit', $user)->with('status', __('Two-factor sign-in was turned off for :name.', ['name' => $user->username]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->onlyOwnersTouchOwners($request, $user);

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => __('You cannot deactivate yourself.')]);
        }

        if ($user->isOwner() && $this->ownerCount() <= 1) {
            return back()->withErrors(['user' => __('This is the only owner. Make another user an owner first.')]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', __('User :name was deactivated and can no longer sign in.', ['name' => $user->username]));
    }

    public function restore(Request $request, User $user): RedirectResponse
    {
        $this->onlyOwnersTouchOwners($request, $user);
        $user->restore();

        return redirect()->route('users.index')->with('status', __('User :name can sign in again.', ['name' => $user->username]));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(?User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user?->getKey())],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->getKey())],
            'phone' => ['nullable', 'string', 'max:20'],
            'locale' => ['nullable', Rule::in(array_keys(config('sdm.supported_locales')))],
            'role' => ['required', Rule::in(PermissionRegistry::ROLES)],
        ];
    }

    /**
     * users.manage can be given to other roles; it must not let them create,
     * edit or lock out an owner, or make themselves one.
     */
    private function onlyOwnersTouchOwners(Request $request, ?User $target, ?string $newRole = null): void
    {
        /** @var User $actor */
        $actor = $request->user();

        if (! $actor->isOwner() && ($target?->isOwner() || $newRole === PermissionRegistry::OWNER)) {
            abort(403, __('Only an owner can change owner accounts.'));
        }
    }

    private function normaliseUsername(Request $request): void
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => mb_strtolower(trim($request->input('username')))]);
        }
    }

    private function ownerCount(): int
    {
        return User::query()->role(PermissionRegistry::OWNER)->count();
    }
}
