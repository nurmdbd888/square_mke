<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Permission / schema helpers that must never crash a page render.
 */
final class SafeAccess
{
    public static function can(?User $user, ?string $permission): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        if ($user === null) {
            return false;
        }

        try {
            return $user->can($permission);
        } catch (Throwable) {
            return false;
        }
    }

    public static function routeExists(string $name): bool
    {
        try {
            return Route::has($name);
        } catch (Throwable) {
            return false;
        }
    }

    public static function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
