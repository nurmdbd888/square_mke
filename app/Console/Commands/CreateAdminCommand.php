<?php

namespace App\Console\Commands;

use App\Actions\CreateOwner;
use App\Support\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateAdminCommand extends Command
{
    protected $signature = 'sdm:create-admin
        {--name= : Full name}
        {--username= : Sign-in name (letters, digits, dot, dash, underscore)}
        {--email= : E-mail address (optional)}';

    protected $description = 'Create an owner account (same as "Create owner account" on the Maintenance page)';

    public function handle(CreateOwner $createOwner): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $username = $this->option('username') ?: $this->ask('User name');
        $email = $this->option('email') ?? $this->ask('E-mail (optional)', '');
        $password = (string) $this->secret('Password (at least 10 characters)');
        $confirmation = (string) $this->secret('Password again');

        try {
            $user = $createOwner([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], AuditLogger::SOURCE_CONSOLE);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info("Owner {$user->username} created.");

        return self::SUCCESS;
    }
}
