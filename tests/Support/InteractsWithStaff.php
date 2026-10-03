<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

/**
 * Pembantu tes untuk akun staf berperan. Dipakai bersama RefreshDatabase
 * (tabel `roles` kosong di awal setiap tes).
 */
trait InteractsWithStaff
{
    /** Menjalankan RolePermissionSeeder bila tabel roles masih kosong. */
    protected function seedRoles(): void
    {
        if (Role::query()->doesntExist()) {
            $this->seed(RolePermissionSeeder::class);
        }
    }

    /**
     * Membuat akun staf aktif dengan satu peran.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createStaff(string $role, array $attributes = []): User
    {
        $this->seedRoles();

        return User::factory()->staff()->withRole($role)->create($attributes);
    }
}
