<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder inti (13 §2.4): 19 izin baku dan 3 peran (00 G). Idempoten: aman dijalankan ulang
 * (produksi dan pengembangan); izin peran selalu disinkronkan ke matriks di bawah.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var list<string> Urutan = tabel 00 G. */
    public const PERMISSIONS = [
        'dashboard.view',
        'doctors.view',
        'doctors.manage',
        'clinics.manage',
        'schedules.view',
        'schedules.manage',
        'sessions.review',
        'facilities.manage',
        'rooms.update-availability',
        'content.manage',
        'patients.view',
        'patients.manage',
        'appointments.view',
        'appointments.manage',
        'reports.export',
        'messages.manage',
        'settings.manage',
        'staff.manage',
        'audit.view',
    ];

    /**
     * Matriks peran ⇒ izin (00 G). `super-admin` memperoleh semua izin untuk tampilan UI;
     * pemeriksaan sebenarnya tetap lewat Gate::before.
     *
     * @var array<string, list<string>>
     */
    public const ROLES = [
        'super-admin' => self::PERMISSIONS,
        'content-admin' => [
            'dashboard.view',
            'doctors.view',
            'doctors.manage',
            'clinics.manage',
            'schedules.view',
            'schedules.manage',
            'facilities.manage',
            'rooms.update-availability',
            'content.manage',
        ],
        'registration-officer' => [
            'dashboard.view',
            'doctors.view',
            'schedules.view',
            'sessions.review',
            'rooms.update-availability',
            'patients.view',
            'patients.manage',
            'appointments.view',
            'appointments.manage',
            'reports.export',
            'messages.manage',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
