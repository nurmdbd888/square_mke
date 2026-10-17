<?php

namespace App\Actions;

use App\Models\User;
use App\Support\Access\PermissionRegistry;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Creates a user with the owner role. Used by the Maintenance page (no
 * terminal on the host) and by `php artisan sdm:create-admin`.
 */
class CreateOwner
{
    /**
     * @param  array<string, mixed>  $input  name, username, email, password, password_confirmation
     *
     * @throws ValidationException
     */
    public function __invoke(array $input, string $source, ?User $by = null): User
    {
        $input['username'] = mb_strtolower(trim((string) ($input['username'] ?? '')));
        $input['email'] = trim((string) ($input['email'] ?? '')) ?: null;

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ])->validate();

        app(PermissionRegistry::class)->sync();

        return DB::transaction(function () use ($data, $source, $by) {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
            ]);

            $user->assignRole(PermissionRegistry::OWNER);
            AuditLogger::log('maintenance.owner_created', $user, null, ['username' => $user->username, 'role' => PermissionRegistry::OWNER], $by, $source);

            return $user;
        });
    }
}
