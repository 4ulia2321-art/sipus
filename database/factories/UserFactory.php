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
    /**
     * Password default yang dipakai factory.
     */
    protected static ?string $password;

    /**
     * Definisi data akun sesuai struktur tabel t_user.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName() . '@sipus.sch.id',
            'name'     => fake()->name(),
            'password' => static::$password ??= Hash::make('password'),
            'role'     => 'Siswa',
        ];
    }

    /**
     * Buat akun dengan role Admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Admin',
        ]);
    }

    /**
     * Buat akun dengan role Pustakawan.
     */
    public function pustakawan(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Pustakawan',
        ]);
    }
}
