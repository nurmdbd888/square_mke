<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Language order: the signed-in user's choice, then the sdm_locale cookie
 * (works on the stateless Maintenance page too), then the default_locale
 * setting, then config.
 */
class SetLocale
{
    public const COOKIE = 'sdm_locale';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::resolve($request));

        return $next($request);
    }

    public static function resolve(Request $request): string
    {
        $supported = config('sdm.supported_locales');

        foreach ([self::userLocale($request), $request->cookies->get(self::COOKIE), setting('default_locale')] as $candidate) {
            if (is_string($candidate) && array_key_exists($candidate, $supported)) {
                return $candidate;
            }
        }

        return (string) config('app.locale');
    }

    private static function userLocale(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        try {
            $user = Auth::user();

            return $user instanceof User ? $user->locale : null;
        } catch (Throwable) {
            return null;
        }
    }
}
