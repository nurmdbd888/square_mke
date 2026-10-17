<?php

namespace App\Http\Middleware;

use App\Support\Maintenance\AppKey;
use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Starts the normal web session on the Maintenance page only when that is
 * safe (valid APP_KEY, session storage readable), so the page can see which
 * user is signed in once an owner exists, and still works on a fresh server.
 */
class MaintenanceSession
{
    public function __construct(private readonly AppKey $appKey) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->appKey->isValid() || ! $this->sessionStorageUsable()) {
            return $next($request);
        }

        return (new Pipeline(app()))
            ->send($request)
            ->through([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class])
            ->then($next);
    }

    private function sessionStorageUsable(): bool
    {
        try {
            app('session')->driver()->getHandler()->read(str_repeat('0', 40));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
