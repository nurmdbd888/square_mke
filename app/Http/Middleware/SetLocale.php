<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const COOKIE = 'sdm_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookies->get(self::COOKIE);

        if (! is_string($locale) || ! array_key_exists($locale, config('sdm.supported_locales'))) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
