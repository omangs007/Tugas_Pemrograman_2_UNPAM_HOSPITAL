<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Notifications\AppointmentStatusChangedNotification;
use App\Services\Scheduling\PracticeSessionGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Form walk-in W-20 (F7A-R21): pencarian pasien, pemilihan sesi loket, simpan lewat BookingService (kanal loket, status
 * `confirmed`). WLK-03/04, BKG-07/08 sisi loket, SEK-11, NTF-03, VAL-07, Review Focus E2/E3/E4/E5. Waktu dibekukan pada
 * Selasa 2026-10-06 07.00; sesi uji 09.00-12.00, kuota 20, check-in dibuka 08.00, batas online 60 menit.
 */
class AdminWalkInTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    private Clinic $clinic;

    private Doctor $doctor;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 07:00:00');
        $this->seed(SettingsSeeder::class);
        Cache::put(PracticeSessionGenerator::markerKey(CarbonImmutable::today()), true, CarbonImmutable::today()->endOfDay());

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $this->clinic);
        $this->officer = $this->createStaff('registration-officer');
    }

    // --- pembantu ----------------------------------------------------------------------------

    /** @param  array<string, mixed>  $overrides */
    private function sessionOn(string $date = '2026-10-06', array $overrides = [], ?Doctor $doctor = null): PracticeSession
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

    /** @param  array<string, mixed>  $attributes */
    private function profile(array $attributes = []): PatientProfile
    {
        return PatientProfile::factory()->create($attributes + ['full_name' => 'Siti Rahmawati']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(PatientProfile $profile, PracticeSession $session, array $overrides = []): array
    {
        return $overrides + [
            'patient_profile_id' => (string) $profile->id,
            'practice_session_id' => (string) $session->id,
            'payment_type' => 'umum',
            'complaint' => 'Batuk berdahak sejak tiga hari.',
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function store(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->from(route('admin.appointments.create'))
            ->post(route('admin.appointments.store'), $data);
    }

    private function create(array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)->get(route('admin.appointments.create', $query));
    }

    private function detailUrl(Appointment $appointment): string
    {
        return route('admin.appointments.show', ['appointment' => $appointment->booking_code]);
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
    public function test_access_to_the_form_per_staff_role(string $role, int $status): void
    {
        $this->create([], $this->createStaff($role))->assertStatus($status);
    }

    public function test_content_admin_and_patient_cannot_store_and_a_guest_is_sent_to_the_login(): void
    {
        $session = $this->sessionOn();
        $profile = $this->profile();
        $data = $this->payload($profile, $session);

        $this->store($data, $this->createStaff('content-admin'))->assertForbidden();
        $this->store($data, User::factory()->patient()->create())->assertForbidden();

        auth()->logout();
        $this->post(route('admin.appointments.store'), $data)->assertRedirect(route('admin.login'));
        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_the_urls_use_the_indonesian_segments(): void
    {
        $this->assertSame(url('/admin/pendaftaran/buat'), route('admin.appointments.create'));
        $this->assertSame(url('/admin/pendaftaran'), route('admin.appointments.store'));
    }

    // --- bagian 1: pasien --------------------------------------------------------------------

    public function test_the_initial_form_has_three_numbered_sections_and_two_inactive_ones(): void
    {
        $html = $this->create()
            ->assertOk()
            ->assertSee('1. Pasien')
            ->assertSee('2. Sesi')
            ->assertSee('3. Data Pembayaran')
            ->assertSee('Pilih pasien terlebih dahulu.')
            ->assertSee('aria-disabled="true"', false)
            ->getContent();

        $this->assertStringNotContainsString('name="payment_type"', $html);
        $this->assertStringNotContainsString('name="poli"', $html);
        $this->assertStringNotContainsString('x-wizard', $html);
    }

    public function test_the_patient_search_lists_matches_with_a_choose_link_and_marks_inactive_profiles(): void
    {
        $active = $this->profile(['full_name' => 'Siti Rahma', 'patient_number' => 'P000123']);
        $inactive = $this->profile(['full_name' => 'Siti Nonaktif', 'patient_number' => 'P000124', 'is_active' => false]);
        $this->profile(['full_name' => 'Budi Santoso', 'patient_number' => 'P000125']);

        $html = $this->create(['cari' => 'Siti'])
            ->assertOk()
            ->assertSee('Siti Rahma')
            ->assertSee('Siti Nonaktif')
            ->assertDontSee('Budi Santoso')
            ->assertSee('P000123')
            ->assertSee('Profil nonaktif')
            ->assertSee('href="'.route('admin.appointments.create', ['pasien' => $active->id]).'"', false)
            ->getContent();

        $this->assertStringNotContainsString('pasien='.$inactive->id, $html);

        $this->create(['cari' => 'P000125'])->assertSee('Budi Santoso')->assertDontSee('Siti Rahma');
    }

    public function test_an_empty_search_shows_the_empty_text_and_the_new_profile_link(): void
    {
        $this->create(['cari' => 'Zzzzz'])
            ->assertOk()
            ->assertSee('Pasien tidak ditemukan.')
            ->assertSee('Buat profil baru')
            ->assertSee('href="'.route('admin.patients.create', ['kembali' => '/admin/pendaftaran/buat?cari=Zzzzz']).'"', false);
    }

    public function test_the_new_profile_link_is_hidden_without_the_patients_manage_permission(): void
    {
        $this->seedRoles();
        $limited = User::factory()->staff()->create();
        $limited->givePermissionTo('appointments.manage');

        $this->create(['cari' => 'Zzzzz'], $limited)->assertOk()->assertSee('Pasien tidak ditemukan.')->assertDontSee('Buat profil baru');
    }

    public function test_a_selected_patient_is_summarised_with_the_masked_bpjs_only(): void
    {
        $profile = $this->profile(['full_name' => 'Siti Rahma', 'patient_number' => 'P000123', 'birth_date' => '1990-03-12', 'bpjs_number' => '0009900000099']);

        $this->create(['pasien' => $profile->id])
            ->assertOk()
            ->assertSee('Terpilih:')
            ->assertSee('Siti Rahma')
            ->assertSee('P000123')
            ->assertSee('12/03/1990')
            ->assertSee('•••••••••0099')
            ->assertDontSee('0009900000099')
            ->assertSee('Ganti')
            ->assertSee('Pilih sesi terlebih dahulu.');
    }

    public function test_an_inactive_selected_profile_is_refused_with_a_warning(): void
    {
        $inactive = $this->profile(['is_active' => false]);

        $html = $this->create(['pasien' => $inactive->id])
            ->assertOk()
            ->assertSee('Profil pasien tidak aktif atau tidak ditemukan.')
            ->assertDontSee('Terpilih:')
            ->getContent();

        $this->assertStringNotContainsString('name="poli"', $html);
    }

    public function test_patient_names_are_escaped_in_the_search_results_and_the_summary(): void
    {
        $evil = $this->profile(['full_name' => '<script>alert(1)</script>']);

        $this->create(['cari' => 'script'])
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $this->create(['pasien' => $evil->id])
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    // --- bagian 2: sesi ----------------------------------------------------------------------

    public function test_sessions_are_listed_with_counter_labels_and_unavailable_ones_are_disabled(): void
    {
        $profile = $this->profile();
        $onlineClosed = $this->sessionOn('2026-10-06', ['start_time' => '07:30:00', 'end_time' => '11:30:00']);
        $open = $this->sessionOn('2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $full = $this->sessionOn('2026-10-06', ['start_time' => '16:00:00', 'end_time' => '18:00:00', 'quota' => 3, 'booked_count' => 3, 'last_queue_number' => 3]);
        $this->sessionOn('2026-10-05', ['start_time' => '09:00:00', 'end_time' => '11:00:00']);

        $html = $this->create(['pasien' => $profile->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-06'])
            ->assertOk()
            ->assertSee('07.30–11.30')
            ->assertSee('Online ditutup; loket masih bisa')
            ->assertSee('13.00–15.00')
            ->assertSee('Sisa 20 dari 20')
            ->assertSee('16.00–18.00')
            ->assertSee('Penuh')
            ->assertSee('Kuota sesi sudah penuh.')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Online ditutup; loket masih bisa'));
        $this->assertMatchesRegularExpression('/<input(?![^>]*disabled)[^>]*name="sesi"[^>]*value="'.$onlineClosed->id.'"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input(?![^>]*disabled)[^>]*name="sesi"[^>]*value="'.$open->id.'"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input(?=[^>]*disabled)[^>]*name="sesi"[^>]*value="'.$full->id.'"[^>]*>/', $html);
        $this->assertStringNotContainsString('09.00–11.00', $html);
    }

    public function test_a_day_without_sessions_shows_the_empty_state(): void
    {
        $this->create(['pasien' => $this->profile()->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-07'])
            ->assertOk()
            ->assertSee('Tidak ada sesi pada tanggal ini');
    }

    public function test_choosing_a_session_derives_the_clinic_doctor_and_date_and_opens_the_payment_section(): void
    {
        $profile = $this->profile(['full_name' => 'Siti Rahma']);
        $session = $this->sessionOn('2026-10-08');

        $html = $this->create(['pasien' => $profile->id, 'sesi' => $session->id, 'poli' => 'tidak-ada', 'tanggal' => '2026-10-07'])
            ->assertOk()
            ->assertSee('Simpan Pendaftaran')
            ->assertSee('Kanal: Loket')
            ->assertSee('Status awal: Dikonfirmasi')
            ->assertSee('value="2026-10-08"', false)
            ->assertDontSee('Pilih sesi terlebih dahulu.')
            ->getContent();

        $this->assertStringContainsString('<input type="hidden" id="patient_profile_id" name="patient_profile_id" value="'.$profile->id.'">', $html);
        $this->assertStringContainsString('<input type="hidden" id="practice_session_id" name="practice_session_id" value="'.$session->id.'">', $html);
        $this->assertStringContainsString('action="'.route('admin.appointments.store').'"', $html);
        $this->assertStringContainsString('data-payment-toggle', $html);
    }

    public function test_a_session_that_the_counter_cannot_register_gets_a_warning_and_no_payment_section(): void
    {
        $profile = $this->profile();
        $full = $this->sessionOn('2026-10-06', ['quota' => 2, 'booked_count' => 2, 'last_queue_number' => 2]);

        $html = $this->create(['pasien' => $profile->id, 'sesi' => $full->id])
            ->assertOk()
            ->assertSee('Sesi yang dipilih tidak dapat didaftarkan: Penuh.')
            ->assertSee('Pilih sesi terlebih dahulu.')
            ->getContent();

        $this->assertStringNotContainsString('name="payment_type"', $html);
    }

    public function test_a_session_without_a_selected_patient_is_ignored(): void
    {
        $session = $this->sessionOn();

        $html = $this->create(['sesi' => $session->id])->assertOk()->assertSee('Pilih pasien terlebih dahulu.')->getContent();

        $this->assertStringNotContainsString('name="payment_type"', $html);
    }

    public function test_hostile_query_values_are_ignored_without_a_server_error(): void
    {
        $this->create([])->assertOk();

        $this->actingAs($this->officer)
            ->get('/admin/pendaftaran/buat?pasien[]=1&sesi=abc&tanggal=2099-13-40&poli[]=x&dokter=tidak-ada&cari[]=y&kembali=//evil.test&page=-1&per_page=999')
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Pilih pasien terlebih dahulu.');

        $this->actingAs($this->officer)->get('/admin/pendaftaran/buat?pasien=999999&sesi=999999')->assertOk();
        $this->actingAs($this->officer)->get('/admin/pendaftaran/buat?tanggal=2026-12-31')->assertOk();
    }

    public function test_the_cancel_link_follows_a_valid_kembali_only(): void
    {
        $this->create(['kembali' => '/admin/antrean?tanggal=2026-10-06'])->assertSee('href="/admin/antrean?tanggal=2026-10-06"', false);
        $this->create(['kembali' => '//evil.test'])->assertDontSee('evil.test')->assertSee('href="'.route('admin.appointments.index').'"', false);
        $this->create(['kembali' => '/pasien'])->assertSee('href="'.route('admin.appointments.index').'"', false);
    }

    // --- bagian 3 dan simpan: WLK-03 ---------------------------------------------------------

    public function test_wlk_03_saving_creates_a_confirmed_counter_appointment_with_the_next_queue_number(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();
        $this->bookAppointment($session, AppointmentStatus::Confirmed);

        $response = $this->store($this->payload($profile, $session));

        $appointment = Appointment::query()->where('patient_profile_id', $profile->id)->sole();

        $response->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHas('success', 'Pendaftaran walk-in berhasil. Kode booking '.$appointment->booking_code.', nomor antrean ANK-002.');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame(BookingChannel::Loket, $appointment->channel);
        $this->assertSame($this->officer->id, (int) $appointment->created_by_user_id);
        $this->assertSame(2, $appointment->queue_number);
        $this->assertNotNull($appointment->confirmed_at);
        $this->assertSame(2, PracticeSession::query()->findOrFail($session->id)->booked_count);

        $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole();
        $this->assertNull($history->from_status);
        $this->assertSame('confirmed', $history->to_status->value);
        $this->assertSame('staff', $history->actor_type->value);
        $this->assertSame($this->officer->id, (int) $history->actor_user_id);

        $audit = AuditLog::query()->where('action', 'appointment.created')->sole();
        $this->assertSame('loket', $audit->new_values['channel']);
    }

    public function test_after_saving_the_detail_page_offers_the_print_button(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();

        $this->store($this->payload($profile, $session));
        $appointment = Appointment::query()->sole();

        $this->actingAs($this->officer)->get($this->detailUrl($appointment))
            ->assertOk()
            ->assertSee('Cetak Bukti')
            ->assertSee('href="'.route('admin.appointments.proof', ['appointment' => $appointment->booking_code]).'"', false);
    }

    public function test_wlk_03_a_session_closed_for_online_booking_can_still_be_registered_at_the_counter(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn('2026-10-06', ['start_time' => '07:30:00', 'end_time' => '11:30:00']);

        $this->store($this->payload($profile, $session))->assertSessionHas('success');

        $this->assertSame(1, Appointment::query()->where('practice_session_id', $session->id)->count());
    }

    public function test_the_save_works_for_a_walk_in_profile_and_an_account_profile_alike(): void
    {
        $session = $this->sessionOn();
        $walkIn = $this->profile(['user_id' => null]);
        $withAccount = PatientProfile::factory()->withAccount()->create();

        $this->store($this->payload($walkIn, $session))->assertSessionHas('success');
        $this->store($this->payload($withAccount, $session))->assertSessionHas('success');

        $this->assertSame(2, Appointment::query()->where('channel', 'loket')->count());
    }

    // --- WLK-04: kuota bersama ---------------------------------------------------------------

    public function test_wlk_04_the_counter_shares_the_quota_with_online_bookings(): void
    {
        $session = $this->sessionOn('2026-10-06', ['quota' => 2]);
        $this->bookAppointment($session, AppointmentStatus::Pending, ['channel' => BookingChannel::Online]);
        $first = $this->profile(['full_name' => 'Pasien Loket Satu']);
        $second = $this->profile(['full_name' => 'Pasien Loket Dua']);

        $this->store($this->payload($first, $session))->assertSessionHas('success');
        $this->assertSame(2, PracticeSession::query()->findOrFail($session->id)->booked_count);

        $this->store($this->payload($second, $session))
            ->assertSessionHasErrors(['practice_session_id' => 'Kuota sesi ini sudah penuh. Silakan pilih tanggal atau sesi lain.']);

        $this->assertSame(0, Appointment::query()->where('patient_profile_id', $second->id)->count());
        $this->assertSame(2, PracticeSession::query()->findOrFail($session->id)->booked_count);
    }

    // --- BKG-07 sisi loket -------------------------------------------------------------------

    public function test_bkg_07_one_minute_before_the_end_is_accepted_and_exactly_the_end_is_rejected(): void
    {
        $session = $this->sessionOn();
        $inTime = $this->profile(['full_name' => 'Pasien Tepat Waktu']);
        $late = $this->profile(['full_name' => 'Pasien Terlambat']);

        $this->travelTo('2026-10-06 11:59:00');
        $this->store($this->payload($inTime, $session))->assertSessionHas('success');

        $this->travelTo('2026-10-06 12:00:00');
        $this->store($this->payload($late, $session))
            ->assertSessionHasErrors(['practice_session_id' => 'Sesi ini sudah berakhir.']);

        $this->assertSame(1, Appointment::query()->where('practice_session_id', $session->id)->count());
        $this->assertSame(0, Appointment::query()->where('patient_profile_id', $late->id)->count());
    }

    public function test_an_ended_session_is_not_listed_in_the_form(): void
    {
        $this->sessionOn('2026-10-06', ['start_time' => '04:00:00', 'end_time' => '06:00:00']);

        $this->create(['pasien' => $this->profile()->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-06'])
            ->assertOk()
            ->assertSee('Tidak ada sesi pada tanggal ini');
    }

    // --- BKG-08: batas aktif tidak berlaku di loket ------------------------------------------

    public function test_bkg_08_the_active_limit_does_not_apply_at_the_counter(): void
    {
        $profile = $this->profile();

        foreach (['2026-10-07', '2026-10-08', '2026-10-09'] as $date) {
            $this->bookAppointment($this->sessionOn($date), AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        }

        $fourth = $this->sessionOn('2026-10-10');

        $this->store($this->payload($profile, $fourth))->assertSessionHas('success');

        $this->assertSame(4, Appointment::query()->where('patient_profile_id', $profile->id)->active()->count());
    }

    public function test_a_duplicate_and_an_overlap_are_still_rejected_at_the_counter(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();
        $this->store($this->payload($profile, $session))->assertSessionHas('success');

        $this->store($this->payload($profile, $session))
            ->assertSessionHasErrors(['practice_session_id' => 'Pasien ini sudah memiliki pendaftaran aktif pada sesi dokter ini.']);

        $other = $this->createDoctor(['name' => 'Budi Hartono'], $this->createClinic(['name' => 'Poli Bedah', 'code' => 'BDH']));
        $overlapping = $this->sessionOn('2026-10-06', ['start_time' => '10:00:00', 'end_time' => '13:00:00'], $other);

        $response = $this->store($this->payload($profile, $overlapping));
        $response->assertSessionHasErrors('practice_session_id');
        $this->assertStringContainsString('Jadwal ini bertabrakan dengan pendaftaran lain pasien ini', session('errors')->first('practice_session_id'));

        $this->assertSame(1, Appointment::query()->where('patient_profile_id', $profile->id)->count());
    }

    // --- profil nonaktif dan field tersembunyi dirusak (E3, E5) ------------------------------

    public function test_an_inactive_profile_cannot_be_registered(): void
    {
        $inactive = $this->profile(['is_active' => false]);
        $session = $this->sessionOn();

        $this->store($this->payload($inactive, $session))
            ->assertRedirect(route('admin.appointments.create', ['poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-06']))
            ->assertSessionHasErrors(['patient_profile_id' => 'Profil pasien tidak aktif atau tidak ditemukan.']);

        $this->assertSame(0, Appointment::query()->count());
    }

    /** @return array<string, array{mixed, string}> */
    public static function badProfileIds(): array
    {
        return [
            'tidak ada' => ['999999', 'Profil pasien tidak aktif atau tidak ditemukan.'],
            'nol' => ['0', 'Profil pasien tidak aktif atau tidak ditemukan.'],
            'huruf' => ['abc', 'Profil pasien tidak aktif atau tidak ditemukan.'],
            'larik' => [['1'], 'Profil pasien tidak aktif atau tidak ditemukan.'],
            'kosong' => ['', 'Pasien wajib dipilih.'],
        ];
    }

    #[DataProvider('badProfileIds')]
    public function test_a_tampered_patient_profile_id_is_rejected(mixed $value, string $message): void
    {
        $session = $this->sessionOn();

        $this->store($this->payload($this->profile(), $session, ['patient_profile_id' => $value]))
            ->assertSessionHasErrors(['patient_profile_id' => $message]);

        $this->assertSame(0, Appointment::query()->count());
    }

    /** @return array<string, array{mixed, string}> */
    public static function badSessionIds(): array
    {
        return [
            'tidak ada' => ['999999', 'Sesi praktik tidak ditemukan.'],
            'huruf' => ['abc', 'Sesi praktik tidak ditemukan.'],
            'larik' => [['1'], 'Sesi praktik tidak ditemukan.'],
            'kosong' => ['', 'Sesi praktik wajib dipilih.'],
        ];
    }

    #[DataProvider('badSessionIds')]
    public function test_a_tampered_session_id_is_rejected(mixed $value, string $message): void
    {
        $this->store($this->payload($this->profile(), $this->sessionOn(), ['practice_session_id' => $value]))
            ->assertSessionHasErrors(['practice_session_id' => $message]);

        $this->assertSame(0, Appointment::query()->count());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unavailableSessions(): array
    {
        return [
            'sesi ditutup' => [['status' => 'closed', 'status_reason' => 'Ditutup']],
            'sesi dibatalkan' => [['status' => 'cancelled', 'status_reason' => 'Dibatalkan']],
            'sesi berakhir' => [['start_time' => '04:00:00', 'end_time' => '06:00:00']],
            'sesi penuh' => [['quota' => 1, 'booked_count' => 1, 'last_queue_number' => 1]],
            'di luar jendela' => [['session_date' => '2026-12-31']],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('unavailableSessions')]
    public function test_a_session_that_cannot_be_registered_is_rejected_by_the_server(array $overrides): void
    {
        $session = $this->sessionOn($overrides['session_date'] ?? '2026-10-06', $overrides);

        $this->store($this->payload($this->profile(), $session))->assertSessionHasErrors('practice_session_id');

        $this->assertSame(0, Appointment::query()->where('channel', 'loket')->count());
    }

    // --- pembayaran dan data sensitif (SEK-11, E4) -------------------------------------------

    public function test_payment_validation_errors_return_to_the_session_step_without_the_bpjs_number(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();
        $back = route('admin.appointments.create', ['pasien' => $profile->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-06', 'sesi' => $session->id]);

        $this->store($this->payload($profile, $session, ['payment_type' => 'bpjs', 'bpjs_number' => '000990000001']))
            ->assertRedirect($back)
            ->assertSessionHasErrors(['bpjs_number' => 'Nomor BPJS harus terdiri dari 13 digit angka.'])
            ->assertSessionMissing('_old_input.bpjs_number')
            ->assertSessionHas('_old_input.payment_type', 'bpjs')
            ->assertSessionHas('_old_input.complaint', 'Batuk berdahak sejak tiga hari.');

        $this->store($this->payload($profile, $session, ['payment_type' => 'asuransi', 'policy_number' => 'POL-77-AB']))
            ->assertRedirect($back)
            ->assertSessionHasErrors(['insurance_partner_id' => 'Mitra asuransi wajib dipilih untuk pembayaran asuransi.'])
            ->assertSessionMissing('_old_input.policy_number');

        $this->store($this->payload($profile, $session, ['payment_type' => '']))
            ->assertRedirect($back)
            ->assertSessionHasErrors(['payment_type' => 'Jenis pembayaran wajib dipilih.']);

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_session_level_rejection_returns_to_the_session_step_without_the_session_in_the_url(): void
    {
        $profile = $this->profile();
        $full = $this->sessionOn('2026-10-06', ['quota' => 1, 'booked_count' => 1, 'last_queue_number' => 1]);

        $this->store($this->payload($profile, $full))
            ->assertRedirect(route('admin.appointments.create', ['pasien' => $profile->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-06']));
    }

    public function test_a_bpjs_booking_is_stored_encrypted_and_never_shown_flashed_or_audited(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();

        $this->store($this->payload($profile, $session, ['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS]))
            ->assertSessionHas('success')
            ->assertSessionMissing('_old_input.bpjs_number');

        $appointment = Appointment::query()->sole();
        $this->assertSame(PaymentType::Bpjs, $appointment->payment_type);
        $this->assertSame('0011', $appointment->payment_reference_last4);
        $this->assertNotSame(self::BPJS, DB::table('appointments')->where('id', $appointment->id)->value('payment_reference'));

        $this->actingAs($this->officer)->get($this->detailUrl($appointment))
            ->assertOk()->assertSee('•••••••••0011')->assertDontSee(self::BPJS);

        $logged = json_encode(AuditLog::query()->get()->map(fn (AuditLog $log): array => [$log->old_values, $log->new_values, $log->description])->all());
        $this->assertStringNotContainsString(self::BPJS, (string) $logged);
        $this->assertStringNotContainsString('Batuk berdahak', (string) $logged);
    }

    public function test_an_insurance_booking_keeps_the_policy_masked(): void
    {
        $partner = InsurancePartner::factory()->create(['name' => 'Asuransi Contoh Sehat']);
        $profile = $this->profile();

        $this->store($this->payload($profile, $this->sessionOn(), ['payment_type' => 'asuransi', 'insurance_partner_id' => (string) $partner->id, 'policy_number' => 'POL-778899/12']))
            ->assertSessionHas('success');

        $appointment = Appointment::query()->sole();
        $this->assertSame($partner->id, (int) $appointment->insurance_partner_id);
        $this->assertSame('9/12', $appointment->payment_reference_last4);
        $this->actingAs($this->officer)->get($this->detailUrl($appointment))->assertSee('•••••••••9/12')->assertDontSee('POL-778899');
    }

    public function test_save_bpjs_stores_the_number_on_the_profile_with_an_audit_row_without_the_number(): void
    {
        $profile = $this->profile();

        $this->store($this->payload($profile, $this->sessionOn(), ['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS, 'save_bpjs' => '1']))
            ->assertSessionHas('success');

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('0011', $fresh->bpjs_number_last4);
        $this->assertSame(self::BPJS, $fresh->bpjs_number);
        $this->assertSame(1, AuditLog::query()->where('action', 'patient.updated')->count());
        $this->assertStringNotContainsString(self::BPJS, (string) json_encode(AuditLog::query()->where('action', 'patient.updated')->get()->map(fn (AuditLog $log): array => [$log->old_values, $log->new_values])->all()));
    }

    public function test_without_save_bpjs_the_profile_keeps_no_number(): void
    {
        $profile = $this->profile();

        $this->store($this->payload($profile, $this->sessionOn(), ['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS]))->assertSessionHas('success');

        $this->assertNull(PatientProfile::query()->findOrFail($profile->id)->bpjs_number_last4);
    }

    public function test_an_empty_bpjs_number_uses_the_number_stored_on_the_profile(): void
    {
        $profile = $this->profile(['bpjs_number' => '0009900000099']);

        $this->store($this->payload($profile, $this->sessionOn(), ['payment_type' => 'bpjs', 'bpjs_number' => '']))->assertSessionHas('success');

        $this->assertSame('0099', Appointment::query()->sole()->payment_reference_last4);
    }

    public function test_an_empty_bpjs_number_without_a_stored_one_is_rejected(): void
    {
        $this->store($this->payload($this->profile(), $this->sessionOn(), ['payment_type' => 'bpjs']))
            ->assertSessionHasErrors(['bpjs_number' => 'Nomor BPJS wajib diisi untuk pembayaran BPJS.']);
    }

    // --- galat dari book() (balapan) ---------------------------------------------------------

    /** @return array<string, array{string, bool}> */
    public static function raceCodes(): array
    {
        return [
            'SESSION_UNAVAILABLE' => [BookingException::SESSION_UNAVAILABLE, false],
            'OUTSIDE_WINDOW' => [BookingException::OUTSIDE_WINDOW, false],
            'CUTOFF_PASSED' => [BookingException::CUTOFF_PASSED, false],
            'QUOTA_FULL' => [BookingException::QUOTA_FULL, false],
            'DUPLICATE_BOOKING' => [BookingException::DUPLICATE_BOOKING, false],
            'OVERLAPPING_BOOKING' => [BookingException::OVERLAPPING_BOOKING, false],
            'INVALID_PAYMENT' => [BookingException::INVALID_PAYMENT, true],
            'SYSTEM_BUSY' => [BookingException::SYSTEM_BUSY, true],
        ];
    }

    #[DataProvider('raceCodes')]
    public function test_every_booking_exception_from_book_returns_to_the_right_step_with_old_input_but_without_bpjs(string $code, bool $withSession): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();
        $exception = BookingException::make($code, ['subjek' => 'pasien ini', 'days' => 30, 'minutes' => 60, 'doctor' => 'dr. X', 'start' => '09.00', 'end' => '12.00'], ['practice_session_id' => $session->id]);
        Appointment::creating(function () use ($exception): void {
            throw $exception;
        });

        $query = ['pasien' => $profile->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-06'];

        if ($withSession) {
            $query['sesi'] = $session->id;
        }

        $this->store($this->payload($profile, $session, ['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS]))
            ->assertRedirect(route('admin.appointments.create', $query))
            ->assertSessionHas('error', $exception->getMessage())
            ->assertSessionHas('_old_input.payment_type', 'bpjs')
            ->assertSessionHas('_old_input.complaint', 'Batuk berdahak sejak tiga hari.')
            ->assertSessionMissing('_old_input.bpjs_number');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_profile_that_became_inactive_during_the_save_returns_to_the_form_without_a_patient(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();
        Appointment::creating(function (): void {
            throw BookingException::profileInactive();
        });

        $this->store($this->payload($profile, $session))
            ->assertRedirect(route('admin.appointments.create'))
            ->assertSessionHas('error', 'Profil pasien tidak aktif atau tidak ditemukan.');
    }

    // --- E2: kirim ganda ---------------------------------------------------------------------

    public function test_a_double_submit_is_held_by_the_per_staff_lock(): void
    {
        $profile = $this->profile();
        $session = $this->sessionOn();
        $held = Cache::lock('walkin-submit:'.$this->officer->id, 10);
        $this->assertTrue($held->get());

        $this->store($this->payload($profile, $session, ['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS]))
            ->assertRedirect(route('admin.appointments.create', ['pasien' => $profile->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'tanggal' => '2026-10-06', 'sesi' => $session->id]))
            ->assertSessionHas('error', 'Permintaan Anda sedang diproses. Mohon tunggu sebentar dan jangan menekan tombol berulang kali.')
            ->assertSessionMissing('_old_input.bpjs_number');

        $this->assertSame(0, Appointment::query()->count());

        $held->release();
        $this->store($this->payload($profile, $session))->assertSessionHas('success');
        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_the_lock_is_released_after_a_save_and_another_staff_member_is_not_blocked(): void
    {
        $session = $this->sessionOn();
        $other = $this->createStaff('registration-officer');
        Cache::lock('walkin-submit:'.$other->id, 10)->get();

        $this->store($this->payload($this->profile(), $session))->assertSessionHas('success');
        $this->store($this->payload($this->profile(), $session))->assertSessionHas('success');

        $this->assertSame(2, Appointment::query()->count());
    }

    // --- NTF-03 ------------------------------------------------------------------------------

    public function test_ntf_03_a_patient_with_an_account_is_notified_and_a_walk_in_without_one_is_not(): void
    {
        $session = $this->sessionOn();
        $withAccount = PatientProfile::factory()->withAccount()->create();
        $recipient = User::query()->findOrFail($withAccount->user_id);
        $walkIn = $this->profile(['user_id' => null]);
        Notification::fake();

        $this->store($this->payload($walkIn, $session))->assertSessionHas('success');
        Notification::assertNothingSent();

        $this->store($this->payload($withAccount, $session))->assertSessionHas('success');
        Notification::assertSentToTimes($recipient, AppointmentStatusChangedNotification::class, 1);
    }

    // --- tombol masuk ------------------------------------------------------------------------

    public function test_the_list_page_offers_the_walk_in_button_to_the_officer(): void
    {
        $this->actingAs($this->officer)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('Pendaftaran Walk-in')
            ->assertSee('href="'.route('admin.appointments.create').'"', false);
    }

    public function test_the_list_page_hides_the_walk_in_button_without_the_manage_permission(): void
    {
        $this->seedRoles();
        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('appointments.view');

        $this->actingAs($viewer)->get(route('admin.appointments.index'))->assertOk()->assertDontSee('Pendaftaran Walk-in');
    }

    public function test_the_patient_detail_offers_register_visit_only_for_active_profiles(): void
    {
        $active = $this->profile();
        $inactive = $this->profile(['is_active' => false]);

        $this->actingAs($this->officer)->get(route('admin.patients.show', $active))
            ->assertOk()
            ->assertSee('Daftarkan Kunjungan')
            ->assertSee('href="'.route('admin.appointments.create', ['pasien' => $active->id]).'"', false);

        $this->actingAs($this->officer)->get(route('admin.patients.show', $inactive))
            ->assertOk()
            ->assertDontSee('Daftarkan Kunjungan');
    }
}
