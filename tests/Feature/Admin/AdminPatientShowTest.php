<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Detail pasien admin (#37): PAS-06 (BPJS tersamar, nomor lengkap tidak ada di HTML), akun terhubung atau "Tanpa akun",
 * riwayat pendaftaran terbaru dengan paginasi (halaman `riwayat`), 404 untuk id tak ada atau non-numerik, akses, SEK-15,
 * dan kontrak jangkar `<x-slot:actions>` (C.1c).
 */
class AdminPatientShowTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Clinic $clinic;

    private Doctor $doctor;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $this->clinic);
    }

    // --- pembantu ----------------------------------------------------------------------------

    /** Kode booking valid (alfabet Crockford) dan berurutan: UPH-ABCD-0001, UPH-ABCD-0002, … */
    private function code(): string
    {
        return sprintf('UPH-ABCD-%04d', ++$this->sequence);
    }

    private function sessionOn(string $date): PracticeSession
    {
        return $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'doctor_schedule_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(PatientProfile $profile, string $date, AppointmentStatus $status = AppointmentStatus::Confirmed, array $overrides = []): Appointment
    {
        return $this->bookAppointment($this->sessionOn($date), $status, array_merge([
            'booking_code' => $this->code(),
            'patient_profile_id' => $profile->id,
        ], $overrides));
    }

    private function officer(): User
    {
        return $this->createStaff('registration-officer');
    }

    // --- PAS-06: profil ----------------------------------------------------------------------

    public function test_the_profile_is_shown_with_a_masked_bpjs_number_and_no_full_number_pas_06(): void
    {
        $profile = PatientProfile::factory()->withBpjs('0009900000011')->create([
            'full_name' => 'Siti Rahma',
            'patient_number' => 'P000123',
            'gender' => 'P',
            'birth_date' => '1990-03-12',
            'phone' => '081200000001',
            'email' => null,
            'address' => 'Jl. Merpati No. 5, Pamulang',
        ]);

        $response = $this->actingAs($this->officer())
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('Siti Rahma')
            ->assertSee('P000123')
            ->assertSee('Perempuan')
            ->assertSee('12/03/1990')
            ->assertSee('081200000001')
            ->assertSee('Jl. Merpati No. 5, Pamulang')
            ->assertSee('•••••••••0011')
            ->assertSee('Loket')
            ->assertSee('Aktif')
            ->assertSee('Tanpa akun')
            ->assertSee('<h1 class="mb-1">Siti Rahma</h1>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Siti Rahma (P000123)</li>', false);

        $this->assertStringNotContainsString('0009900000011', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), '<h1'), 'Satu h1 per halaman.');
    }

    public function test_a_profile_without_bpjs_says_so(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Tanpa Bpjs']);

        $this->actingAs($this->officer())
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('Tidak ada nomor BPJS tersimpan');
    }

    public function test_an_inactive_profile_is_labelled_inactive(): void
    {
        $profile = PatientProfile::factory()->inactive()->create(['full_name' => 'Pasien Nonaktif']);

        $this->actingAs($this->officer())
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('Nonaktif');
    }

    public function test_a_linked_profile_shows_the_account_email_and_a_walk_in_shows_tanpa_akun(): void
    {
        $linked = PatientProfile::factory()->withAccount()->create(['full_name' => 'Pasien Berakun']);
        $walkIn = PatientProfile::factory()->create(['full_name' => 'Pasien Loket']);

        $this->actingAs($this->officer());

        $this->get(route('admin.patients.show', $linked))
            ->assertOk()
            ->assertSee('Terhubung ke akun')
            ->assertSee($linked->email)
            ->assertDontSee('Tanpa akun');

        $this->get(route('admin.patients.show', $walkIn))
            ->assertOk()
            ->assertSee('Tanpa akun')
            ->assertDontSee('Terhubung ke akun');
    }

    // --- riwayat pendaftaran -----------------------------------------------------------------

    public function test_the_appointment_history_lists_only_this_patient_newest_first(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Siti Rahma']);
        $other = PatientProfile::factory()->create(['full_name' => 'Pasien Lain']);

        $oldest = $this->book($profile, '2026-10-13', AppointmentStatus::Completed);
        $middle = $this->book($profile, '2026-10-14', AppointmentStatus::Cancelled, ['channel' => BookingChannel::Loket]);
        $newest = $this->book($profile, '2026-10-15', AppointmentStatus::Confirmed);
        $foreign = $this->book($other, '2026-10-16');

        $this->actingAs($this->officer())
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('<th scope="col">Kode</th>', false)
            ->assertSee('<th scope="col">Poliklinik</th>', false)
            ->assertSeeInOrder([$newest->booking_code, $middle->booking_code, $oldest->booking_code])
            ->assertDontSee($foreign->booking_code)
            ->assertSee('dr. Sinta Maharani, Sp.A')
            ->assertSee('Poli Anak')
            ->assertSee('ANK-001')
            ->assertSee('09.00–12.00')
            ->assertSee('Dikonfirmasi')
            ->assertSee('Selesai')
            ->assertSee('Dibatalkan')
            ->assertSee('data-label="Kanal"', false);
    }

    public function test_the_appointment_history_is_paginated_with_its_own_page_name(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Siti Rahma']);
        $codes = [];

        foreach (range(1, 12) as $day) {
            $codes[$day] = $this->book($profile, sprintf('2026-11-%02d', $day))->booking_code;
        }

        $this->actingAs($this->officer());

        $first = $this->get(route('admin.patients.show', $profile))->assertOk();

        foreach (range(3, 12) as $day) {
            $first->assertSee($codes[$day]);
        }

        $first->assertDontSee($codes[1])->assertDontSee($codes[2])->assertSee('riwayat=2', false);

        $this->get(route('admin.patients.show', ['patient' => $profile, 'riwayat' => 2]))
            ->assertOk()
            ->assertSee($codes[1])
            ->assertSee($codes[2])
            ->assertDontSee($codes[12]);
    }

    public function test_a_profile_without_appointments_shows_the_empty_state(): void
    {
        $profile = PatientProfile::factory()->create();

        $this->actingAs($this->officer())
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('Belum ada pendaftaran')
            ->assertSee('Pasien ini belum pernah mendaftar kunjungan.');
    }

    public function test_a_page_beyond_the_history_range_says_so_without_an_error(): void
    {
        $profile = PatientProfile::factory()->create();
        $this->book($profile, '2026-10-13');

        $this->actingAs($this->officer())
            ->get(route('admin.patients.show', ['patient' => $profile, 'riwayat' => 9]))
            ->assertOk()
            ->assertSee('Halaman riwayat yang diminta tidak berisi data.');
    }

    // --- 404 ---------------------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function missingUrls(): array
    {
        return [
            'id tidak ada' => ['/admin/pasien/999999'],
            'non-numerik' => ['/admin/pasien/abc'],
            'angka dengan huruf' => ['/admin/pasien/1abc'],
            'negatif' => ['/admin/pasien/-1'],
            'desimal' => ['/admin/pasien/1.5'],
        ];
    }

    #[DataProvider('missingUrls')]
    public function test_unknown_or_malformed_patient_ids_answer_404(string $url): void
    {
        $this->actingAs($this->officer())->get($url)->assertNotFound();
    }

    // --- akses -------------------------------------------------------------------------------

    public function test_super_admin_can_open_a_profile(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Siti Rahma']);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('Siti Rahma');
    }

    public function test_admin_konten_patient_and_guest_cannot_open_a_profile_pas_07(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Siti Rahma', 'patient_number' => 'P000123']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.patients.show', $profile))
            ->assertForbidden()
            ->assertDontSee('Siti Rahma')
            ->assertDontSee('P000123');

        $this->actingAs($this->createPatient())
            ->get(route('admin.patients.show', $profile))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.patients.show', $profile))->assertRedirect(route('admin.login'));
    }

    // --- SEK-15 dan kontrak jangkar -----------------------------------------------------------

    public function test_html_in_the_profile_and_in_the_history_is_escaped_sek_15(): void
    {
        $profile = PatientProfile::factory()->create([
            'full_name' => '<script>alert(1)</script>',
            'address' => '<img src=x onerror=alert(1)>',
            'patient_number' => 'P000250',
        ]);
        $this->book($profile, '2026-10-13');

        $response = $this->actingAs($this->officer())
            ->get(route('admin.patients.show', $profile))
            ->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_the_view_keeps_exactly_one_actions_slot_and_one_layout_closing_tag_c1c(): void
    {
        $source = (string) file_get_contents(resource_path('views/admin/patients/show.blade.php'));

        $this->assertSame(1, substr_count($source, '    <x-slot:actions>'));
        $this->assertSame(1, substr_count($source, '    </x-slot:actions>'));
        $this->assertSame(1, substr_count($source, '</x-layouts.admin>'));
        $this->assertStringNotContainsString('{!!', $source);
    }
}
