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

    public function test_guest_on_a_protected_non_admin_route_goes_home_while_login_route_does_not_exist(): void
    {
        $this->protectedRoute();

        // Cadangan F1-R2: hapus tes ini bersama cadangan di fase 5 (route `login` tersedia).
        if (! Route::has('login')) {
            $this->get('/_uji/terlindungi')->assertRedirect(route('home'));
        } else {
            $this->get('/_uji/terlindungi')->assertRedirect(route('login'));
        }
    }

    public function test_guest_on_a_protected_non_admin_route_goes_to_login_once_that_route_exists(): void
    {
        $this->protectedRoute();
        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/_uji/terlindungi')->assertRedirect(route('login'));
    }

    public function test_staff_on_the_guest_only_admin_login_page_goes_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_patient_on_the_guest_only_admin_login_page_goes_home_while_patient_dashboard_does_not_exist(): void
    {
        // Cadangan F1-R2: hapus cabang ini di fase 5.
        $expected = Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');

        $this->actingAs(User::factory()->patient()->create())
            ->get('/admin/login')
            ->assertRedirect($expected);
    }

    public function test_patient_on_a_guest_only_page_goes_to_the_patient_dashboard_once_it_exists(): void
    {
        Route::middleware('web')->get('/_uji/pasien', fn () => 'pasien')->name('patient.dashboard');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs(User::factory()->patient()->create())
            ->get('/admin/login')
            ->assertRedirect(route('patient.dashboard'));
    }
}
