<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
}
