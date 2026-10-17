<?php

namespace App\Support\Maintenance;

use App\Models\User;
use App\Support\Access\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Second lock on the Maintenance page: once an owner account exists, the
 * secret phrase alone is not enough, the visitor must also be signed in to
 * the app as an owner. If the database cannot be read the phrase alone
 * works, so the owner can still see what is wrong.
 *
 * Owner recovery (MAINTENANCE_OWNER_RECOVERY=true in .env, which only
 * someone with cPanel File Manager access can set) lifts the second lock
 * and allows resetting an owner's password.
 */
class OwnerAccess
{
    private ?int $owners = null;

    private bool $counted = false;

    public function recoveryMode(): bool
    {
        return (bool) config('sdm.maintenance.owner_recovery');
    }

    /**
     * Number of active owners, or null when the users table cannot be read.
     */
    public function ownerCount(): ?int
    {
        if ($this->counted) {
            return $this->owners;
        }

        $this->counted = true;

        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('model_has_roles')) {
                return $this->owners = 0;
            }

            return $this->owners = User::query()->role(PermissionRegistry::OWNER)->count();
        } catch (Throwable) {
            return $this->owners = null;
        }
    }

    public function usersTableReady(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('roles');
        } catch (Throwable) {
            return false;
        }
    }

    public function ownerLoginRequired(): bool
    {
        return ! $this->recoveryMode() && ($this->ownerCount() ?? 0) > 0;
    }

    public function signedInOwner(Request $request): ?User
    {
        if (! $request->hasSession()) {
            return null;
        }

        try {
            $user = Auth::user();

            return $user instanceof User && $user->isOwner() ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function allows(Request $request): bool
    {
        return ! $this->ownerLoginRequired() || $this->signedInOwner($request) !== null;
    }

    public function forget(): void
    {
        $this->counted = false;
        $this->owners = null;
    }
}
