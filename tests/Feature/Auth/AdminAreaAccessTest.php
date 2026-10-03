<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminAreaAccessTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'account:staff', 'active'])->get('/_uji/area-staf', fn () => 'area staf')->name('uji.area-staf');
        Route::middleware(['web', 'auth', 'account:patient', 'active'])->get('/_uji/area-pasien', fn () => 'area pasien')->name('uji.area-pasien');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_staff_account_passes_the_staff_gate_and_patient_account_passes_the_patient_gate(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/_uji/area-staf')->assertOk()->assertSee('area staf');
        $this->actingAs(User::factory()->patient()->create())->get('/_uji/area-pasien')->assertOk()->assertSee('area pasien');
    }

    public function test_patient_on_a_staff_area_is_forbidden(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get('/_uji/area-staf')
            ->assertForbidden();
    }

    public function test_staff_on_a_patient_area_is_redirected_to_the_admin_dashboard_or_forbidden_for_json(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get('/_uji/area-pasien')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson('/_uji/area-pasien')->assertForbidden();
    }

    public function test_staff_deactivated_during_a_running_session_is_logged_out_with_a_flash_message(): void
    {
        $staff = $this->createStaff('content-admin');
        $this->actingAs($staff)->get('/_uji/area-staf')->assertOk();

        $staff->forceFill(['is_active' => false])->save();

        $this->get('/_uji/area-staf')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
        $this->assertGuest();
    }

    public function test_patient_deactivated_during_a_running_session_is_logged_out_and_sent_to_login(): void
    {
        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::getRoutes()->refreshNameLookups();
        $patient = User::factory()->patient()->create();
        $this->actingAs($patient)->get('/_uji/area-pasien')->assertOk();

        $patient->forceFill(['is_active' => false])->save();

        $this->get('/_uji/area-pasien')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
        $this->assertGuest();
    }

    // --- dasbor admin: tamu, pasien, izin ----------------------------------------------------

    public function test_guest_on_the_dashboard_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_patient_gets_the_indonesian_403_page_without_any_admin_content(): void
    {
        $response = $this->actingAs(User::factory()->patient()->create())->get('/admin');

        $response->assertForbidden()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Akses ditolak')
            ->assertSee('Anda tidak memiliki izin untuk mengakses halaman ini.')
            ->assertDontSee('Selamat datang')
            ->assertDontSee('Menu admin')
            ->assertDontSee('Lihat Situs');
    }

    public function test_patient_mutation_requests_to_the_admin_area_are_forbidden(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->post(route('admin.logout'))
            ->assertForbidden();
    }

    public function test_patient_json_request_to_the_admin_area_gets_a_json_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->getJson('/admin')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk mengakses halaman ini.']);
    }

    public function test_staff_without_the_dashboard_permission_gets_403_without_the_permission_library_message(): void
    {
        $this->seedRoles();
        Role::findByName('registration-officer')->revokePermissionTo('dashboard.view');
        $staff = $this->createStaff('registration-officer');

        $this->actingAs($staff)->get('/admin')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertDontSee('right permissions')
            ->assertDontSee('dashboard.view');
    }

    public function test_staff_with_the_dashboard_permission_can_open_it(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/admin')->assertOk();
    }
}
