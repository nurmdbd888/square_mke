<?php

namespace Tests;

use App\Models\User;
use App\Support\Maintenance\MaintenanceGate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    protected function maintenanceCookie(): string
    {
        return app(MaintenanceGate::class)->issueCookie(Request::create('/'))->getValue();
    }

    protected function asMaintenanceUser(): static
    {
        return $this->withUnencryptedCookie(MaintenanceGate::COOKIE, $this->maintenanceCookie());
    }

    protected function maintenanceFormToken(): string
    {
        $request = Request::create('/', cookies: [MaintenanceGate::COOKIE => $this->maintenanceCookie()]);

        return (string) app(MaintenanceGate::class)->formToken($request);
    }

    /**
     * Starts a fresh session when the test switches to another user, as a new
     * browser would. Otherwise the "auth.session" middleware sees the previous
     * user's password hash in the session and signs the new one out.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->app['auth']->guard($guard)->user()?->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function owner(array $attributes = []): User
    {
        return $this->userWithRole('owner', $attributes);
    }
}
