<?php

namespace App\Http\Middleware;

use App\Support\Maintenance\AppKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without APP_KEY every page in the "web" group crashes (cookies are
 * encrypted). Answer before routing so no web middleware is ever built, and
 * send the owner to the Maintenance page, which works without a key.
 */
class EnsureAppKey
{
    public function __construct(private readonly AppKey $appKey) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->appKey->isValid() || $request->is('maintenance', 'maintenance/*', 'locale/*', 'up')) {
            return $next($request);
        }

        return response()->view('setup-needed', [], 503);
    }
}
