<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName().Str::lower(Str::random(3)),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= 'correct-horse-battery',
            'locale' => null,
            'must_change_password' => false,
        ];
    }

    public function role(string $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role));
    }

    public function owner(): static
    {
        return $this->role('owner');
    }
}
