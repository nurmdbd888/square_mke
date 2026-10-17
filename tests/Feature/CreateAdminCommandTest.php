<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_owner(): void
    {
        $this->artisan('sdm:create-admin', ['--name' => 'Khaza Owner', '--username' => 'Khaza', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password (at least 10 characters)', 'owner-password-1')
            ->expectsQuestion('Password again', 'owner-password-1')
            ->expectsOutput('Owner khaza created.')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('username', 'khaza')->sole()->isOwner());
        $this->assertDatabaseHas('audit_logs', ['event' => 'maintenance.owner_created', 'source' => 'console']);
    }

    public function test_reports_validation_errors(): void
    {
        $this->owner(['username' => 'khaza']);

        $this->artisan('sdm:create-admin', ['--name' => 'X', '--username' => 'khaza', '--email' => ''])
            ->expectsQuestion('Password (at least 10 characters)', 'short')
            ->expectsQuestion('Password again', 'short')
            ->expectsOutputToContain('has already been taken')
            ->assertFailed();
    }
}
