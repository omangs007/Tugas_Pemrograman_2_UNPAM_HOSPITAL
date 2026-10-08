<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class GuestRedirectTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private function protectedRoute(): void
    {
        Route::middleware(['web', 'auth'])->get('/_uji/terlindungi', fn () => 'terlindungi')->name('uji.terlindungi');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_guest_on_the_admin_area_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_guest_on_a_protected_non_admin_route_goes_to_the_patient_login(): void
    {
        $this->protectedRoute();

        $this->get('/_uji/terlindungi')->assertRedirect(route('login'));
    }

    public function test_guest_on_the_patient_area_goes_to_the_patient_login(): void
    {
        $this->get('/pasien')->assertRedirect(route('login'));
    }

    public function test_staff_on_the_guest_only_admin_login_page_goes_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_patient_on_the_guest_only_admin_login_page_goes_to_the_patient_dashboard(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get('/admin/login')
            ->assertRedirect(route('patient.dashboard'));
    }

    public function test_patient_on_the_guest_only_patient_login_page_goes_to_the_patient_dashboard(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get('/masuk')
            ->assertRedirect(route('patient.dashboard'));
    }

    public function test_staff_on_the_guest_only_patient_login_page_goes_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/masuk')
            ->assertRedirect(route('admin.dashboard'));
    }
}
