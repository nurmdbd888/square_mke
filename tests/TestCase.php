<?php

namespace Tests;

use App\Support\Maintenance\MaintenanceGate;
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
}
