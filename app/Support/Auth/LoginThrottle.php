<?php

namespace App\Support\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

/**
 * 5 failed sign-ins per minute for one user name from one IP, plus 20 per
 * minute per IP across all user names (stops guessing many accounts).
 */
class LoginThrottle
{
    public const PER_LOGIN = 5;

    public const PER_IP = 20;

    public const DECAY_SECONDS = 60;

    public function __construct(private readonly RateLimiter $limiter) {}

    /**
     * Seconds until the next attempt is allowed, or 0.
     */
    public function blockedFor(Request $request, string $login, string $scope = 'login'): int
    {
        foreach ([[$this->loginKey($request, $login, $scope), self::PER_LOGIN], [$this->ipKey($request, $scope), self::PER_IP]] as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                event(new Lockout($request));

                return max(1, $this->limiter->availableIn($key));
            }
        }

        return 0;
    }

    public function hit(Request $request, string $login, string $scope = 'login'): void
    {
        $this->limiter->hit($this->loginKey($request, $login, $scope), self::DECAY_SECONDS);
        $this->limiter->hit($this->ipKey($request, $scope), self::DECAY_SECONDS);
    }

    public function clear(Request $request, string $login, string $scope = 'login'): void
    {
        $this->limiter->clear($this->loginKey($request, $login, $scope));
    }

    private function loginKey(Request $request, string $login, string $scope): string
    {
        return $scope.':'.sha1(mb_strtolower($login).'|'.$request->ip());
    }

    private function ipKey(Request $request, string $scope): string
    {
        return $scope.'-ip:'.sha1((string) $request->ip());
    }
}
