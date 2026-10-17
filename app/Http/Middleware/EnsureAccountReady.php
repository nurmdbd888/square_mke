<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users to their profile until they have changed a temporary password
 * and, if their role requires it, set up two-factor sign-in.
 */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $request->routeIs('profile', 'profile.*', 'logout')) {
            return $next($request);
        }

        if ($user->must_change_password) {
            return redirect()->route('profile')->with('warning', __('Please choose a new password before you continue.'));
        }

        if (! $user->hasTwoFactorEnabled() && in_array($user->roleName(), (array) setting('require_2fa_roles'), true)) {
            return redirect()->route('profile')->with('warning', __('Your role requires two-factor sign-in. Please set it up to continue.'));
        }

        return $next($request);
    }
}
