<?php

namespace Tests\Feature\Patient;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Dashboard pasien (#27, F5-R22): EMP-04, SEK-15, F5-R13, ERR-02 (sisi pasien). Tombol kunjungan mengikuti
 * keberadaan route fase 6 (`patient.appointments.create`).
 */
class PatientDashboardTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** Isi `<section aria-labelledby="{id}">` (header dan sidebar juga memuat teks "Daftar Kunjungan"). */
    private function section(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('#<section[^>]*aria-labelledby="'.preg_quote($id, '#').'"[^>]*>(.*?)</section>#s', $html, $matches),
            "Bagian {$id} tidak ditemukan.",
        );

        return $matches[1];
    }

    public function test_guest_is_redirected_to_the_patient_login(): void
    {
        $this->get(route('patient.dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_greets_the_patient_and_shows_the_patient_number_and_contact_summary(): void
    {
        $patient = $this->createPatient(['email' => 'siti@contoh.test'], ['full_name' => 'Siti Rahma', 'phone' => '081355550001']);
        $number = $patient->patientProfile->patient_number;

        $response = $this->actingAs($patient)->get(route('patient.dashboard'));

        $response->assertOk()
            ->assertSee('<title>Dashboard Pasien — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('Halo, Siti Rahma')
            ->assertSee('No. pasien: '.$number);
        $this->assertMatchesRegularExpression('/^P\d{6,}$/', $number);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $profile = $this->section($response->getContent(), 'profil-judul');
        $this->assertStringContainsString('Profil Anda', $response->getContent());
        $this->assertStringContainsString('siti@contoh.test', $profile);
        $this->assertStringContainsString('081355550001', $profile);
    }

    /** EMP-04: kartu kosong; tombol "Daftar Kunjungan" belum ada karena route fase 6 belum ada. */
    public function test_empty_state_offers_the_doctor_schedule_while_the_booking_route_does_not_exist(): void
    {
        $this->assertFalse(Route::has('patient.appointments.create'));

        $response = $this->actingAs($this->createPatient())->get(route('patient.dashboard'))->assertOk();

        $visits = $this->section($response->getContent(), 'kunjungan-judul');
        $this->assertStringContainsString('Kunjungan Mendatang', $response->getContent());
        $this->assertStringContainsString('Belum ada pendaftaran', $visits);
        $this->assertStringContainsString('Pendaftaran kunjungan Anda akan tampil di sini.', $visits);
        $this->assertStringContainsString('<a class="btn btn-outline-primary" href="'.route('schedules.index').'">Lihat Jadwal Dokter</a>', $visits);
        $this->assertStringNotContainsString('Daftar Kunjungan', $visits);
    }

    /** EMP-04: tombol "Daftar Kunjungan" muncul begitu route fase 6 terdaftar (route palsu di tes ini). */
    public function test_empty_state_offers_the_booking_button_once_the_booking_route_exists(): void
    {
        Route::middleware('web')->get('/_uji/pasien/pendaftaran/baru', fn () => 'x')->name('patient.appointments.create');
        Route::getRoutes()->refreshNameLookups();

        $response = $this->actingAs($this->createPatient())->get(route('patient.dashboard'))->assertOk();

        $visits = $this->section($response->getContent(), 'kunjungan-judul');
        $this->assertStringContainsString('Belum ada pendaftaran', $visits);
        $this->assertStringContainsString('<a class="btn btn-primary" href="'.route('patient.appointments.create').'">Daftar Kunjungan</a>', $visits);
        $this->assertStringNotContainsString('Lihat Jadwal Dokter', $visits);
    }

    /** SEK-15: nama pasien berisi tag HTML tampil sebagai teks. */
    public function test_a_script_tag_in_the_patient_name_is_escaped(): void
    {
        $patient = $this->createPatient([], ['full_name' => '<script>alert(1)</script>']);

        $this->actingAs($patient)->get(route('patient.dashboard'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Halo, &lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /** F5-R13: akun pasien tanpa profil (hanya mungkin pada data rusak) mendapat 404. */
    public function test_a_patient_account_without_a_profile_gets_a_404(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('patient.dashboard'))
            ->assertNotFound();
    }

    public function test_staff_cannot_open_the_patient_dashboard(): void
    {
        $staff = $this->createStaff('registration-officer');

        $this->actingAs($staff)->get(route('patient.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson(route('patient.dashboard'))->assertForbidden();
    }

    public function test_portal_menu_and_logout_form_are_rendered_for_the_patient(): void
    {
        $response = $this->actingAs($this->createPatient())->get(route('patient.dashboard'))->assertOk();

        $response->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Akun Saya')
            ->assertDontSee('>Masuk<', false);
    }

    /** ERR-02 (sisi pasien): 403 di URL admin menautkan kembali ke dashboard pasien. */
    public function test_patient_403_page_links_back_to_the_patient_dashboard(): void
    {
        $this->actingAs($this->createPatient())->get('/admin')
            ->assertForbidden()
            ->assertSee('Ke Dasbor')
            ->assertSee('href="'.route('patient.dashboard').'"', false);
    }
}
