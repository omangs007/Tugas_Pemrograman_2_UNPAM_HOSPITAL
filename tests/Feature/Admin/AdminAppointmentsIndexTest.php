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
use App\Support\NavigationMenu;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #38, daftar pendaftaran (F7A-R17, 05 §409): bawaan hari ini, filter tanggal/poli/dokter/status/kanal/kata kunci,
 * rentang terbalik, query liar (VAL-07), escape nama pasien (SEK-15), akses per peran (AKS). Waktu dibekukan pada
 * Selasa 2026-10-06 07.00.
 */
class AdminAppointmentsIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Clinic $clinic;

    private Doctor $doctor;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 07:00:00');
        $this->seed(SettingsSeeder::class);

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $this->clinic);
        $this->officer = $this->createStaff('registration-officer');
    }

    // --- pembantu ----------------------------------------------------------------------------

    /** @param  array<string, mixed>  $overrides */
    private function sessionOn(string $date, array $overrides = [], ?Doctor $doctor = null): PracticeSession
    {
        $doctor ??= $this->doctor;

        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'doctor_schedule_id' => null,
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function appointmentOn(PracticeSession $session, string $name, AppointmentStatus $status = AppointmentStatus::Confirmed, array $overrides = [], array $profile = []): Appointment
    {
        $owner = PatientProfile::factory()->create(['full_name' => $name] + $profile);

        return $this->bookAppointment($session, $status, array_merge(['patient_profile_id' => $owner->id], $overrides));
    }

    private function index(array $query = []): TestResponse
    {
        return $this->actingAs($this->officer)->get(route('admin.appointments.index', $query));
    }

    // --- akses -------------------------------------------------------------------------------

    /** @return array<string, array{string, int}> */
    public static function roles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 200],
            'admin konten' => ['content-admin', 403],
        ];
    }

    #[DataProvider('roles')]
    public function test_access_per_staff_role(string $role, int $status): void
    {
        $this->actingAs($this->createStaff($role))->get(route('admin.appointments.index'))->assertStatus($status);
    }

    public function test_a_patient_gets_403_and_a_guest_is_sent_to_the_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())->get(route('admin.appointments.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.appointments.index'))->assertRedirect(route('admin.login'));
    }

    public function test_the_url_uses_the_indonesian_segment(): void
    {
        $this->assertSame(url('/admin/pendaftaran'), route('admin.appointments.index'));
    }

    // --- bawaan dan filter -------------------------------------------------------------------

    public function test_the_default_range_is_today_only(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-05'), 'Pasien Kemarin');
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');
        $this->appointmentOn($this->sessionOn('2026-10-07'), 'Pasien Besok');

        $this->index()
            ->assertOk()
            ->assertSee('Pasien Hari Ini')
            ->assertDontSee('Pasien Kemarin')
            ->assertDontSee('Pasien Besok')
            ->assertSee('id="filter-dari"', false)
            ->assertSee('value="2026-10-06"', false);
    }

    public function test_dari_and_sampai_widen_the_range_inclusively(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-05'), 'Pasien Lima');
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Enam');
        $this->appointmentOn($this->sessionOn('2026-10-07'), 'Pasien Tujuh');
        $this->appointmentOn($this->sessionOn('2026-10-08'), 'Pasien Delapan');

        $this->index(['dari' => '2026-10-06', 'sampai' => '2026-10-07'])
            ->assertOk()
            ->assertDontSee('Pasien Lima')
            ->assertSee('Pasien Enam')
            ->assertSee('Pasien Tujuh')
            ->assertDontSee('Pasien Delapan');

        $this->index(['dari' => '2026-10-07'])
            ->assertSee('Pasien Tujuh')
            ->assertSee('Pasien Delapan')
            ->assertDontSee('Pasien Enam');

        $this->index(['sampai' => '2026-10-05'])
            ->assertSee('Pasien Lima')
            ->assertDontSee('Pasien Enam');
    }

    public function test_a_reversed_range_ignores_sampai_keeps_dari_and_says_so(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Enam');
        $this->appointmentOn($this->sessionOn('2026-10-08'), 'Pasien Delapan');

        $this->index(['dari' => '2026-10-08', 'sampai' => '2026-10-06'])
            ->assertOk()
            ->assertSee('Tanggal sampai tidak boleh sebelum tanggal dari: batas sampai diabaikan, tanggal dari tetap berlaku.')
            ->assertSee('Pasien Delapan')
            ->assertDontSee('Pasien Enam');
    }

    public function test_the_filters_narrow_the_list(): void
    {
        $other = $this->createDoctor(['name' => 'Budi Hartono'], $this->createClinic(['name' => 'Poli Bedah', 'code' => 'BDH']));
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Anak Online');
        $this->appointmentOn($this->sessionOn('2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']), 'Pasien Anak Loket', AppointmentStatus::Pending, ['channel' => BookingChannel::Loket]);
        $this->appointmentOn($this->sessionOn('2026-10-06', [], $other), 'Pasien Bedah', AppointmentStatus::Completed);

        $this->index(['poli' => $this->clinic->slug])
            ->assertSee('Pasien Anak Online')->assertSee('Pasien Anak Loket')->assertDontSee('Pasien Bedah');
        $this->index(['dokter' => $other->slug])
            ->assertSee('Pasien Bedah')->assertDontSee('Pasien Anak Online');
        $this->index(['status' => 'pending'])
            ->assertSee('Pasien Anak Loket')->assertDontSee('Pasien Anak Online')->assertDontSee('Pasien Bedah');
        $this->index(['kanal' => 'loket'])
            ->assertSee('Pasien Anak Loket')->assertDontSee('Pasien Anak Online');
        $this->index(['kanal' => 'online', 'status' => 'completed'])
            ->assertSee('Pasien Bedah')->assertDontSee('Pasien Anak Loket');
    }

    public function test_the_keyword_matches_the_booking_code_the_patient_number_or_the_name(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Siti Rahmawati', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345'], ['patient_number' => 'P000123']);
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Budi Santoso', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-WXYZ-6789'], ['patient_number' => 'P000456']);

        $this->index(['q' => 'uph-abcd-2345'])->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');
        $this->index(['q' => 'uph abcd 2345'])->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');
        $this->index(['q' => 'UPHABCD2345'])->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');
        $this->index(['q' => 'P000456'])->assertSee('Budi Santoso')->assertDontSee('Siti Rahmawati');
        $this->index(['q' => 'rahma'])->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');
        $this->index(['q' => 'tidak ada orang ini'])->assertOk()->assertDontSee('Siti Rahmawati')->assertDontSee('Budi Santoso');
    }

    public function test_the_percent_and_underscore_in_the_keyword_are_literal(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Siti Rahmawati');

        $this->index(['q' => '%'])->assertOk()->assertDontSee('Siti Rahmawati');
        $this->index(['q' => '_____'])->assertOk()->assertDontSee('Siti Rahmawati');
    }

    public function test_the_list_is_ordered_by_date_then_session_start_then_queue_number(): void
    {
        $morning = $this->sessionOn('2026-10-06');
        $afternoon = $this->sessionOn('2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $this->appointmentOn($afternoon, 'Pasien Siang Satu');
        $this->appointmentOn($morning, 'Pasien Pagi Satu');
        $this->appointmentOn($morning, 'Pasien Pagi Dua');
        $this->appointmentOn($this->sessionOn('2026-10-07'), 'Pasien Besok');

        $html = $this->index(['dari' => '2026-10-06', 'sampai' => '2026-10-07'])->assertOk()->getContent();

        $positions = array_map(fn (string $name): int => (int) strpos($html, $name), ['Pasien Pagi Satu', 'Pasien Pagi Dua', 'Pasien Siang Satu', 'Pasien Besok']);
        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions);
        $this->assertNotContains(0, $positions);
    }

    public function test_the_table_has_the_agreed_columns_and_a_link_to_the_detail(): void
    {
        $appointment = $this->appointmentOn($this->sessionOn('2026-10-06'), 'Siti Rahmawati', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);

        $this->index()
            ->assertOk()
            ->assertSee('data-label="Kode booking"', false)
            ->assertSee('data-label="Kunjungan"', false)
            ->assertSee('data-label="Poliklinik"', false)
            ->assertSee('data-label="Dokter"', false)
            ->assertSee('data-label="Antrean"', false)
            ->assertSee('data-label="Pasien"', false)
            ->assertSee('data-label="Pembayaran"', false)
            ->assertSee('data-label="Status"', false)
            ->assertSee('data-label="Kanal"', false)
            ->assertSee('UPH-ABCD-2345')
            ->assertSee('ANK-001')
            ->assertSee('Poli Anak')
            ->assertSee('dr. Sinta Maharani, Sp.A')
            ->assertSee('09.00–12.00')
            ->assertSee('Dikonfirmasi')
            ->assertSee('href="'.route('admin.appointments.show', ['appointment' => $appointment->booking_code]).'"', false)
            ->assertDontSee('Ekspor');
    }

    public function test_the_page_has_the_module_tabs_and_an_actions_slot(): void
    {
        $this->index()
            ->assertOk()
            ->assertSee('aria-label="Tab modul"', false)
            ->assertSee('aria-current="page">Pendaftaran</a>', false);

        $labels = array_column(NavigationMenu::items('modules.appointments', $this->officer), 'label');
        $this->assertContains('Pendaftaran', $labels);
        $this->assertSame([], NavigationMenu::items('modules.appointments', $this->createStaff('content-admin')));
    }

    public function test_the_sidebar_shows_the_registration_items_to_the_officer_only(): void
    {
        $officer = [];

        foreach (NavigationMenu::items('admin', $this->officer) as $entry) {
            foreach ($entry['items'] ?? [] as $item) {
                $officer[] = $item['label'];
            }
        }

        $content = [];

        foreach (NavigationMenu::items('admin', $this->createStaff('content-admin')) as $entry) {
            foreach ($entry['items'] ?? [] as $item) {
                $content[] = $item['label'];
            }
        }

        $this->assertContains('Pendaftaran', $officer);
        $this->assertNotContains('Pendaftaran', $content);
    }

    // --- VAL-07, SEK-15, mode ketat ----------------------------------------------------------

    public function test_hostile_query_values_are_ignored_without_a_server_error(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');

        $this->actingAs($this->officer)
            ->get('/admin/pendaftaran?status[]=x&kanal[a]=b&q[]=1&poli[]=1&dokter=tidak-ada&dari=2026-13-40&sampai=abc&per_page=999&page=-3&kembali=//evil.test&urut=-x')
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Pasien Hari Ini');
    }

    /** @return array<string, array{string}> */
    public static function badValues(): array
    {
        return [
            'status liar' => ['status=xyz'],
            'kanal liar' => ['kanal=zzz'],
            'tanggal tidak ada' => ['dari=2026-02-30'],
            'tanggal huruf' => ['sampai=kemarin'],
            'poli larik' => ['poli[]=1'],
            'dokter tidak ada' => ['dokter=tidak-ada'],
            'q terlalu panjang' => ['q='.'a'.'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            'halaman nol' => ['page=0'],
            'per halaman liar' => ['per_page=7'],
        ];
    }

    #[DataProvider('badValues')]
    public function test_each_bad_value_is_ignored_with_the_filter_warning(string $query): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');

        $this->actingAs($this->officer)->get('/admin/pendaftaran?'.$query)
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Pasien Hari Ini');
    }

    public function test_patient_names_are_escaped(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), '<script>alert(1)</script>');

        $this->index()
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_many_rows_render_in_strict_mode_without_lazy_loading(): void
    {
        $session = $this->sessionOn('2026-10-06');

        foreach (range(1, 6) as $number) {
            $this->appointmentOn($session, 'Pasien Nomor '.$number);
        }

        $this->appointmentOn($this->sessionOn('2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']), 'Pasien Siang', AppointmentStatus::Cancelled);

        $html = $this->index()->assertOk()->getContent();

        $this->assertSame(7, substr_count($html, 'data-label="Kode booking"'));
    }

    public function test_an_empty_result_shows_the_empty_state(): void
    {
        $this->index()
            ->assertOk()
            ->assertSee('Tidak ada pendaftaran')
            ->assertSee('Tidak ada pendaftaran pada tanggal dan filter yang dipilih.');
    }
}
