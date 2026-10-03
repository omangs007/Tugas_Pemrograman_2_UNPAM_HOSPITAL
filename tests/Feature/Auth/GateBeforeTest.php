<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class GateBeforeTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private function registerProbeRoutes(): void
    {
        Route::get('/_uji/izin-pipa', fn () => 'ok')->middleware('permission:doctors.manage|settings.manage');
        Route::get('/_uji/peran-pipa', fn () => 'ok')->middleware('role:super-admin|content-admin');
        Route::get('/_uji/peran-atau-izin', fn () => 'ok')->middleware('role_or_permission:super-admin|appointments.manage');
    }

    public function test_super_admin_passes_every_ability(): void
    {
        $superAdmin = $this->createStaff('super-admin');
        Gate::define('uji.selalu-tolak', fn (User $user) => false);

        $fresh = User::query()->findOrFail($superAdmin->id);

        $this->assertTrue(Gate::forUser($fresh)->allows('uji.selalu-tolak'));
        $this->assertTrue(Gate::forUser($fresh)->allows('kemampuan.yang.tidak.terdaftar'));
        $this->assertTrue($fresh->can('settings.manage'));
        $this->assertTrue($fresh->can('apa.saja'));
        $this->assertFalse($fresh->cannot('uji.selalu-tolak'));
    }

    public function test_other_staff_only_get_the_permissions_of_their_role(): void
    {
        $content = User::query()->findOrFail($this->createStaff('content-admin')->id);
        $officer = User::query()->findOrFail($this->createStaff('registration-officer')->id);

        $this->assertTrue($content->can('doctors.manage'));
        $this->assertTrue($content->can('content.manage'));
        $this->assertFalse($content->can('settings.manage'));
        $this->assertFalse($content->can('staff.manage'));
        $this->assertFalse($content->can('audit.view'));
        $this->assertFalse($content->can('appointments.view'));
        $this->assertFalse($content->can('apa.saja'));

        $this->assertTrue($officer->can('appointments.manage'));
        $this->assertTrue($officer->can('sessions.review'));
        $this->assertFalse($officer->can('doctors.manage'));
        $this->assertFalse($officer->can('content.manage'));
        $this->assertFalse($officer->can('settings.manage'));
    }

    public function test_user_without_role_and_guest_are_denied(): void
    {
        $this->seedRoles();
        Gate::define('uji.selalu-tolak', fn (User $user) => false);

        $patient = User::query()->findOrFail(User::factory()->create()->id);

        $this->assertFalse($patient->can('dashboard.view'));
        $this->assertFalse($patient->can('uji.selalu-tolak'));
        $this->assertFalse(Gate::allows('dashboard.view'));
        $this->assertFalse(Gate::allows('uji.selalu-tolak'));
    }

    public function test_gate_before_and_role_label_do_not_trigger_lazy_loading_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $ids = [
            $this->createStaff('super-admin')->id,
            $this->createStaff('content-admin')->id,
            $this->createStaff('registration-officer')->id,
            User::factory()->create()->id,
        ];

        // Hasil banyak baris: model ditandai "dilarang lazy loading" oleh mode ketat.
        $users = User::query()->whereIn('id', $ids)->orderBy('id')->get();

        $this->assertCount(4, $users);

        $labels = [];

        foreach ($users as $user) {
            $user->can('dashboard.view');
            $user->can('doctors.manage');
            $user->hasAnyPermission('appointments.view', 'audit.view');
            Gate::forUser($user)->allows('settings.manage');
            $labels[] = $user->roleLabel;
        }

        $this->assertSame(['Super Admin', 'Admin Konten', 'Petugas Pendaftaran', ''], $labels);
    }

    public function test_role_label_is_empty_without_a_role(): void
    {
        $this->assertSame('', User::factory()->create()->roleLabel);
    }

    /** @return array<string, array{0: string, 1: int, 2: int, 3: int}> */
    public static function probeMatrix(): array
    {
        // peran => status untuk /_uji/izin-pipa, /_uji/peran-pipa, /_uji/peran-atau-izin
        return [
            'super-admin' => ['super-admin', 200, 200, 200],
            'content-admin' => ['content-admin', 200, 200, 403],
            'registration-officer' => ['registration-officer', 403, 403, 200],
        ];
    }

    #[DataProvider('probeMatrix')]
    public function test_spatie_middleware_aliases_work(string $role, int $permissionPipe, int $rolePipe, int $roleOrPermission): void
    {
        $this->registerProbeRoutes();
        $staff = $this->createStaff($role);

        $this->actingAs($staff)->get('/_uji/izin-pipa')->assertStatus($permissionPipe);
        $this->actingAs($staff)->get('/_uji/peran-pipa')->assertStatus($rolePipe);
        $this->actingAs($staff)->get('/_uji/peran-atau-izin')->assertStatus($roleOrPermission);
    }

    public function test_spatie_middleware_rejects_guests_with_403(): void
    {
        $this->registerProbeRoutes();

        $this->get('/_uji/izin-pipa')->assertForbidden();
        $this->get('/_uji/peran-pipa')->assertForbidden();
        $this->get('/_uji/peran-atau-izin')->assertForbidden();
    }

    public function test_forbidden_response_does_not_reveal_permission_names(): void
    {
        $this->registerProbeRoutes();
        $officer = $this->createStaff('registration-officer');

        $response = $this->actingAs($officer)->get('/_uji/izin-pipa');

        $response->assertForbidden();
        $this->assertStringNotContainsString('doctors.manage', $response->getContent());
        $this->assertStringNotContainsString('settings.manage', $response->getContent());
    }

    public function test_factory_with_role_assigns_exactly_one_role_and_no_direct_permissions(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->assertSame(['content-admin'], User::query()->findOrFail($staff->id)->getRoleNames()->all());
        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertSame(1, DB::table('model_has_roles')->count());
    }

    public function test_factory_with_unknown_role_fails(): void
    {
        $this->seedRoles();

        $this->expectException(RoleDoesNotExist::class);

        User::factory()->staff()->withRole('peran-ngawur')->create();
    }

    public function test_create_staff_helper_creates_an_active_staff_and_seeds_roles_once(): void
    {
        $first = $this->createStaff('content-admin', ['name' => 'Staf Uji Satu']);
        $second = $this->createStaff('registration-officer');

        $this->assertSame('Staf Uji Satu', $first->name);
        $this->assertTrue($first->isStaff());
        $this->assertTrue($first->is_active);
        $this->assertTrue($second->isStaff());
        $this->assertSame(3, DB::table('roles')->count());
        $this->assertSame(19, Permission::query()->count());
    }
}
