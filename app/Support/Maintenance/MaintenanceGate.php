<?php

namespace App\Support\Maintenance;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

/**
 * Stateless access control for /maintenance.
 *
 * The page must work before APP_KEY and the database exist, so it cannot use
 * Laravel sessions or encrypted cookies. Instead the access cookie is an
 * expiry timestamp signed with MAINTENANCE_TOKEN; changing the token in .env
 * logs everybody out.
 */
class MaintenanceGate
{
    public const COOKIE = 'sdm_maintenance';

    public function enabled(): bool
    {
        return (bool) config('sdm.maintenance.enabled');
    }

    public function tokenConfigured(): bool
    {
        return mb_strlen($this->token()) >= (int) config('sdm.maintenance.token_min_length');
    }

    public function tokenMatches(string $given): bool
    {
        return $this->tokenConfigured() && hash_equals($this->token(), $given);
    }

    public function isAuthenticated(Request $request): bool
    {
        return $this->validExpiry($request) !== null;
    }

    public function issueCookie(Request $request): Cookie
    {
        $minutes = (int) config('sdm.maintenance.session_minutes');
        $expiry = time() + $minutes * 60;

        return cookie(self::COOKIE, $expiry.'.'.$this->sign('access|'.$expiry), $minutes, secure: $request->isSecure(), httpOnly: true, sameSite: 'strict');
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    /**
     * Form token for POST actions, bound to the current access cookie.
     */
    public function formToken(Request $request): ?string
    {
        $expiry = $this->validExpiry($request);

        return $expiry === null ? null : $this->sign('form|'.$expiry);
    }

    public function formTokenValid(Request $request): bool
    {
        $expected = $this->formToken($request);
        $given = $request->input('_maintenance_token');

        return $expected !== null && is_string($given) && hash_equals($expected, $given);
    }

    public function tooManyAttempts(Request $request): bool
    {
        return $this->limiter(fn (RateLimiter $limiter, string $key) => $limiter->tooManyAttempts($key, (int) config('sdm.maintenance.max_attempts')), false);
    }

    public function recordFailedAttempt(Request $request): void
    {
        $this->limiter(fn (RateLimiter $limiter, string $key) => $limiter->hit($key, (int) config('sdm.maintenance.decay_seconds')), null);
    }

    public function clearAttempts(Request $request): void
    {
        $this->limiter(fn (RateLimiter $limiter, string $key) => $limiter->clear($key), null);
    }

    private function limiter(callable $callback, mixed $fallback): mixed
    {
        $key = 'maintenance-login:'.sha1((string) request()->ip());

        try {
            return $callback(new RateLimiter(Cache::store(config('sdm.maintenance.throttle_store'))), $key);
        } catch (Throwable $e) {
            // An unwritable cache folder must not lock the owner out of the
            // page that tells him the folder is unwritable.
            Log::warning('Maintenance login throttle unavailable: '.$e->getMessage());

            return $fallback;
        }
    }

    private function validExpiry(Request $request): ?int
    {
        if (! $this->tokenConfigured()) {
            return null;
        }

        $value = $request->cookies->get(self::COOKIE);

        if (! is_string($value) || ! preg_match('/^(\d+)\.([a-f0-9]{64})$/', $value, $m)) {
            return null;
        }

        $expiry = (int) $m[1];

        if ($expiry < time() || ! hash_equals($this->sign('access|'.$expiry), $m[2])) {
            return null;
        }

        return $expiry;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->token());
    }

    private function token(): string
    {
        return (string) config('sdm.maintenance.token');
    }
}
