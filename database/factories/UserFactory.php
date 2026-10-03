<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'name' => fake()->name(),
            'remember_token' => \Illuminate\Support\Str::random(10),
        ];
    }

    /** Pengguna baru yang baru daftar: profil masih kosong. */
    public function bare(): static
    {
        return $this->state(fn() => ['name' => null]);
    }
}
