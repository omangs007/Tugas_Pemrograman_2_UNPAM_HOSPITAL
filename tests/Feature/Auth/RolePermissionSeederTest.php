<?php

namespace Tests\Feature\Auth;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use DateInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** 00-keputusan-inti.md G: 19 izin baku, urutan tabel. */
    private const ALL_PERMISSIONS = [
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

    /** 00 G: kolom content-admin (9 izin). */
    private const CONTENT_ADMIN = [
        'dashboard.view',
        'doctors.view',
        'doctors.manage',
        'clinics.manage',
        'schedules.view',
        'schedules.manage',
        'facilities.manage',
        'rooms.update-availability',
        'content.manage',
    ];

    /** 00 G: kolom registration-officer (11 izin). */
    private const REGISTRATION_OFFICER = [
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
    ];

    /** @return array<string, list<string>> nama peran => nama izin */
    private function rolePermissionMap(): array
    {
        $map = [];

        foreach (Role::query()->with('permissions')->get() as $role) {
            $names = $role->permissions->pluck('name')->all();
            sort($names);
            $map[$role->name] = $names;
        }

        ksort($map);

        return $map;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    public function test_permission_catalog_has_the_19_blueprint_permissions_in_order(): void
    {
        $this->assertCount(19, self::ALL_PERMISSIONS);
        $this->assertSame(self::ALL_PERMISSIONS, RolePermissionSeeder::PERMISSIONS);
        $this->assertCount(19, array_unique(RolePermissionSeeder::PERMISSIONS));
    }

    public function test_roles_constant_matches_the_blueprint_matrix(): void
    {
        $this->assertSame(['super-admin', 'content-admin', 'registration-officer'], array_keys(RolePermissionSeeder::ROLES));

        $this->assertSame(RolePermissionSeeder::PERMISSIONS, RolePermissionSeeder::ROLES['super-admin']);

        $this->assertCount(9, RolePermissionSeeder::ROLES['content-admin']);
        $this->assertSame($this->sorted(self::CONTENT_ADMIN), $this->sorted(RolePermissionSeeder::ROLES['content-admin']));

        $this->assertCount(11, RolePermissionSeeder::ROLES['registration-officer']);
        $this->assertSame($this->sorted(self::REGISTRATION_OFFICER), $this->sorted(RolePermissionSeeder::ROLES['registration-officer']));
    }

    public function test_permission_tables_and_published_files_exist(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('model_has_roles'));
        $this->assertTrue(Schema::hasTable('model_has_permissions'));
        $this->assertTrue(Schema::hasTable('role_has_permissions'));

        $this->assertFileExists(config_path('permission.php'));
        $this->assertFileExists(database_path('migrations/2026_10_02_000020_create_permission_tables.php'));
        $this->assertCount(1, glob(database_path('migrations/*_create_permission_tables.php')));
    }

    public function test_seeder_creates_19_permissions_and_3_roles_for_the_web_guard(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(19, Permission::query()->count());
        $this->assertSame(3, Role::query()->count());
        $this->assertSame(self::ALL_PERMISSIONS, Permission::query()->orderBy('id')->pluck('name')->all());
        $this->assertSame(0, Permission::query()->where('guard_name', '!=', 'web')->count());
        $this->assertSame(0, Role::query()->where('guard_name', '!=', 'web')->count());
    }

    public function test_each_role_gets_exactly_the_blueprint_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame([
            'content-admin' => $this->sorted(self::CONTENT_ADMIN),
            'registration-officer' => $this->sorted(self::REGISTRATION_OFFICER),
            'super-admin' => $this->sorted(self::ALL_PERMISSIONS),
        ], $this->rolePermissionMap());
    }

    public function test_no_direct_user_permissions_or_roles_exist_after_seeding(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(19, Permission::query()->count());
        $this->assertSame(3, Role::query()->count());
        $this->assertSame(19 + 9 + 11, DB::table('role_has_permissions')->count());
    }

    public function test_seeder_resyncs_permissions_that_drifted_from_the_matrix(): void
    {
        $this->seed(RolePermissionSeeder::class);

        Role::findByName('content-admin')->givePermissionTo('audit.view');
        Role::findByName('registration-officer')->revokePermissionTo('reports.export');

        $this->seed(RolePermissionSeeder::class);

        $map = $this->rolePermissionMap();

        $this->assertSame($this->sorted(self::CONTENT_ADMIN), $map['content-admin']);
        $this->assertSame($this->sorted(self::REGISTRATION_OFFICER), $map['registration-officer']);
    }

    public function test_seeder_resets_the_permission_cache(): void
    {
        $this->seed(RolePermissionSeeder::class);

        app(PermissionRegistrar::class)->getPermissions();
        $this->assertTrue(Cache::has(config('permission.cache.key')));

        $this->seed(RolePermissionSeeder::class);

        $this->assertFalse(Cache::has(config('permission.cache.key')));
    }

    public function test_permission_config_follows_the_blueprint(): void
    {
        $this->assertFalse(config('permission.teams'));
        $this->assertTrue(config('permission.register_permission_check_method'));
        $this->assertFalse(config('permission.enable_wildcard_permission'));
        $this->assertFalse(config('permission.display_permission_in_exception'));
        $this->assertFalse(config('permission.display_role_in_exception'));
        $this->assertSame('default', config('permission.cache.store'));
        $this->assertSame(Permission::class, config('permission.models.permission'));
        $this->assertSame(Role::class, config('permission.models.role'));

        $interval = config('permission.cache.expiration_time');

        $this->assertInstanceOf(DateInterval::class, $interval);
        $this->assertSame(24, $interval->h + ($interval->d * 24));

        $this->assertSame([
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ], config('permission.table_names'));
    }

    public function test_role_labels_are_indonesian(): void
    {
        $this->assertSame('Super Admin', __('roles.super-admin'));
        $this->assertSame('Admin Konten', __('roles.content-admin'));
        $this->assertSame('Petugas Pendaftaran', __('roles.registration-officer'));
    }

    public function test_database_seeder_runs_the_role_permission_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Role::query()->count());
        $this->assertSame(19, Permission::query()->count());
    }
}
