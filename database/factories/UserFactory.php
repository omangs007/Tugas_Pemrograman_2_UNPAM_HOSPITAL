<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: pasien aktif, tanpa kewajiban ganti password.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => AccountType::Patient,
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Akun staf. Peran diberikan lewat withRole() (Task 3). */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Staff,
        ]);
    }

    /** Akun pasien (default factory; berguna untuk menimpa staff()). */
    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Patient,
        ]);
    }

    /** Akun nonaktif: tidak boleh login. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /** Akun dengan password sementara: wajib ganti password saat login berikutnya. */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    /**
     * Memberi satu peran Spatie setelah akun dibuat. Peran harus sudah ada
     * (jalankan RolePermissionSeeder lebih dulu, mis. lewat InteractsWithStaff::seedRoles()).
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole($role);
        });
    }
}
