<?php

namespace Tests\Feature\Patient;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Riwayat pendaftaran pasien (#30, F6-R23): PAS-01 (hanya milik sendiri), PAS-10 (`jadwal_berubah`), urutan dan
 * paginasi, filter status/tanggal, input larik/liar tanpa 500, empty state, SEK-15 (escape). Waktu dibekukan pada
 * Senin 2026-10-12 08.00.
 */
class AppointmentHistoryTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const WARNING = 'Filter tidak valid dan diabaikan.';

    private Clinic $clinic;

    private Doctor $doctor;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00'));

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $this->clinic);
    }

    // --- pembantu ----------------------------------------------------------------------------

    /** Kode booking valid (alfabet Crockford) dan berurutan: UPH-ABCD-0001, UPH-ABCD-0002, … */
    private function code(): string
    {
        return sprintf('UPH-ABCD-%04d', ++$this->sequence);
    }

    private function sessionOn(string $date, string $start = '09:00:00', string $end = '12:00:00', ?Doctor $doctor = null): PracticeSession
    {
        $doctor ??= $this->doctor;

        return $this->createSession([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'doctor_schedule_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(User $user, PracticeSession $session, AppointmentStatus $status = AppointmentStatus::Confirmed, array $overrides = []): Appointment
    {
        return $this->bookAppointment($session, $status, array_merge([
            'booking_code' => $this->code(),
            'patient_profile_id' => $user->patientProfile->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function history(User $user, array $query = []): TestResponse
    {
        return $this->actingAs($user)->get(route('patient.appointments.index', $query));
    }

    // --- akses -------------------------------------------------------------------------------

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('patient.appointments.index'))->assertRedirect(route('login'));
    }

    public function test_staff_are_sent_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('patient.appointments.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    // --- PAS-01: hanya milik sendiri ----------------------------------------------------------

    public function test_the_history_lists_only_the_own_appointments(): void
    {
        $owner = $this->createPatient(['email' => 'a@contoh.test'], ['full_name' => 'Pasien Pemilik']);
        $other = $this->createPatient(['email' => 'b@contoh.test'], ['full_name' => 'Pasien Lain']);

        $own = [
            $this->book($owner, $this->sessionOn('2026-10-13')),
            $this->book($owner, $this->sessionOn('2026-10-14')),
            $this->book($owner, $this->sessionOn('2026-10-15')),
        ];
        $foreign = [
            $this->book($other, $this->sessionOn('2026-10-16')),
            $this->book($other, $this->sessionOn('2026-10-17')),
        ];

        $response = $this->history($owner)->assertOk();

        foreach ($own as $appointment) {
            $response->assertSee($appointment->booking_code);
        }
        foreach ($foreign as $appointment) {
            $response->assertDontSee($appointment->booking_code);
        }
        $response->assertSee('Menampilkan 1–3 dari 3 data');
    }

    public function test_the_page_renders_title_breadcrumb_columns_and_row_details(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'), AppointmentStatus::Pending, ['queue_number' => 7]);
        $code = $appointment->booking_code;

        $response = $this->history($user)->assertOk();

        $response->assertSee('<title>Riwayat Pendaftaran — '.config('app.name').'</title>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Riwayat Pendaftaran</li>', false)
            ->assertSee('<th scope="col">Kode</th>', false)
            ->assertSee('<th scope="col">Tanggal</th>', false)
            ->assertSee('<th scope="col">Dokter</th>', false)
            ->assertSee('<th scope="col">Antrean</th>', false)
            ->assertSee('<th scope="col">Status</th>', false)
            ->assertSee('href="'.route('patient.appointments.show', $code).'"', false)
            ->assertSee('aria-label="Detail pendaftaran '.$code.'"', false)
            ->assertSee('dr. Sinta Maharani, Sp.A')
            ->assertSee('Poli Anak')
            ->assertSee('ANK-007')
            ->assertSee('09.00–12.00')
            ->assertSee('Menunggu konfirmasi')
            ->assertSee('Daftar Kunjungan')
            ->assertSee(CarbonImmutable::parse('2026-10-13')->translatedFormat('j M y'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_menu_marks_the_history_item_as_current(): void
    {
        $html = $this->history($this->createPatient())->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('patient.appointments.index'), '#').'"\s+aria-current="page"#', $html);
    }

    public function test_the_changed_schedule_marker_is_shown_on_the_row(): void
    {
        $user = $this->createPatient();
        $this->book($user, $this->sessionOn('2026-10-13'), AppointmentStatus::Confirmed, ['schedule_changed_at' => now()]);

        $this->history($user)->assertOk()->assertSee('Jadwal berubah');
    }

    // --- urutan dan paginasi -----------------------------------------------------------------

    public function test_rows_are_ordered_by_date_then_start_time_descending(): void
    {
        $user = $this->createPatient();
        $morning = $this->book($user, $this->sessionOn('2026-10-20', '09:00:00', '12:00:00'));
        $afternoon = $this->book($user, $this->sessionOn('2026-10-20', '13:00:00', '15:00:00'));
        $earlier = $this->book($user, $this->sessionOn('2026-10-15'));
        $past = $this->book($user, $this->sessionOn('2026-10-05'), AppointmentStatus::Completed);

        $this->history($user)
            ->assertOk()
            ->assertSeeInOrder([
                $afternoon->booking_code,
                $morning->booking_code,
                $earlier->booking_code,
                $past->booking_code,
            ]);
    }

    public function test_the_history_is_paginated_by_ten_and_eager_loads_its_relations(): void
    {
        $user = $this->createPatient();
        $codes = [];

        for ($day = 1; $day <= 12; $day++) {
            $codes[$day] = $this->book($user, $this->sessionOn(CarbonImmutable::parse('2026-11-01')->addDays($day)->toDateString()))->booking_code;
        }

        $first = $this->history($user)->assertOk();
        // Terbaru dulu: kode 12 sampai 3 di halaman 1, kode 2 dan 1 di halaman 2.
        for ($day = 12; $day >= 3; $day--) {
            $first->assertSee($codes[$day]);
        }
        $first->assertDontSee($codes[2])
            ->assertDontSee($codes[1])
            ->assertSee('Menampilkan 1–10 dari 12 data')
            ->assertSee('page=2', false);

        $second = $this->history($user, ['page' => 2])->assertOk();
        $second->assertSee($codes[2])
            ->assertSee($codes[1])
            ->assertDontSee($codes[3])
            ->assertSee('Menampilkan 11–12 dari 12 data');
    }

    public function test_per_page_can_be_raised_and_the_page_links_keep_the_filters(): void
    {
        $user = $this->createPatient();

        for ($day = 1; $day <= 12; $day++) {
            $this->book($user, $this->sessionOn(CarbonImmutable::parse('2026-11-01')->addDays($day)->toDateString()));
        }

        $this->history($user, ['per_page' => 25])->assertOk()->assertSee('Menampilkan 1–12 dari 12 data');

        $this->history($user, ['status' => 'confirmed'])
            ->assertOk()
            ->assertSee('status=confirmed', false)
            ->assertSee('page=2', false);
    }

    public function test_a_page_beyond_the_last_shows_the_out_of_range_notice(): void
    {
        $user = $this->createPatient();
        $this->book($user, $this->sessionOn('2026-10-13'));

        $this->history($user, ['page' => 5])
            ->assertOk()
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1');
    }

    // --- empty state -------------------------------------------------------------------------

    public function test_a_patient_without_appointments_sees_the_empty_state_with_the_register_button(): void
    {
        $response = $this->history($this->createPatient())->assertOk();

        $response->assertSee('Belum ada pendaftaran')
            ->assertSee('Pendaftaran kunjungan Anda akan tampil di sini.')
            ->assertSee('href="'.route('patient.appointments.create').'"', false)
            ->assertDontSee('<table', false)
            ->assertDontSee('Tidak ada data yang cocok');
    }

    public function test_a_filter_without_results_uses_the_default_message_and_offers_reset(): void
    {
        $user = $this->createPatient();
        $this->book($user, $this->sessionOn('2026-10-13'));

        $this->history($user, ['status' => 'cancelled'])
            ->assertOk()
            ->assertSee('Pendaftaran tidak ditemukan')
            ->assertSee('Tidak ada data yang cocok dengan pencarian atau filter Anda.')
            ->assertSee('Hapus filter')
            ->assertDontSee('Belum ada pendaftaran');
    }

    // --- filter status dan tanggal -----------------------------------------------------------

    public function test_each_status_filter_keeps_only_that_status(): void
    {
        $user = $this->createPatient();
        $byStatus = [];

        foreach (AppointmentStatus::cases() as $index => $status) {
            $date = CarbonImmutable::parse('2026-10-13')->addDays($index)->toDateString();
            $byStatus[$status->value] = $this->book($user, $this->sessionOn($date), $status)->booking_code;
        }

        foreach ($byStatus as $value => $code) {
            $response = $this->history($user, ['status' => $value])->assertOk();
            $response->assertSee($code);

            foreach ($byStatus as $otherValue => $otherCode) {
                if ($otherValue !== $value) {
                    $response->assertDontSee($otherCode);
                }
            }
            $this->assertMatchesRegularExpression('#<option value="'.$value.'"\s+selected\s*>#', $response->getContent());
        }
    }

    public function test_the_date_range_is_inclusive_on_both_ends(): void
    {
        $user = $this->createPatient();
        $a = $this->book($user, $this->sessionOn('2026-10-13'))->booking_code;
        $b = $this->book($user, $this->sessionOn('2026-10-15'))->booking_code;
        $c = $this->book($user, $this->sessionOn('2026-10-20'))->booking_code;

        $this->history($user, ['dari' => '2026-10-15', 'sampai' => '2026-10-15'])
            ->assertOk()->assertSee($b)->assertDontSee($a)->assertDontSee($c);
        $this->history($user, ['dari' => '2026-10-15'])
            ->assertOk()->assertSee($b)->assertSee($c)->assertDontSee($a);
        $this->history($user, ['sampai' => '2026-10-15'])
            ->assertOk()->assertSee($a)->assertSee($b)->assertDontSee($c);
    }

    public function test_an_until_date_before_the_from_date_is_ignored_with_a_warning(): void
    {
        $user = $this->createPatient();
        $a = $this->book($user, $this->sessionOn('2026-10-13'))->booking_code;
        $c = $this->book($user, $this->sessionOn('2026-10-20'))->booking_code;

        $this->history($user, ['dari' => '2026-10-20', 'sampai' => '2026-10-13'])
            ->assertOk()
            ->assertSee(self::WARNING)
            ->assertSee($c)
            ->assertDontSee($a);
    }

    public function test_the_filter_form_does_not_reflect_invalid_values(): void
    {
        $user = $this->createPatient();
        $this->book($user, $this->sessionOn('2026-10-13'));

        $response = $this->history($user, ['status' => '"><script>alert(1)</script>', 'dari' => '"><script>alert(2)</script>'])->assertOk();

        $response->assertSee(self::WARNING)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<script>alert(2)</script>', false);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidFilters(): array
    {
        return [
            'status liar' => [['status' => 'foo']],
            'status huruf besar' => [['status' => 'CONFIRMED']],
            'status larik' => [['status' => ['confirmed']]],
            'status larik asosiatif' => [['status' => ['a' => 'b']]],
            'dari bukan tanggal' => [['dari' => 'besok']],
            'dari format lain' => [['dari' => '15/10/2026']],
            'dari tanggal mustahil' => [['dari' => '2026-02-30']],
            'dari larik' => [['dari' => ['2026-10-15']]],
            'sampai bukan tanggal' => [['sampai' => 'lusa']],
            'sampai larik' => [['sampai' => ['2026-10-15']]],
            'jadwal_berubah larik' => [['jadwal_berubah' => ['1']]],
            'per_page liar' => [['per_page' => '7']],
            'per_page larik' => [['per_page' => ['10']]],
            'page bukan angka' => [['page' => 'abc']],
            'page nol' => [['page' => '0']],
            'page negatif' => [['page' => '-1']],
            'page larik' => [['page' => ['2']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[DataProvider('invalidFilters')]
    public function test_invalid_or_array_filters_are_ignored_with_a_warning_and_never_fail(array $query): void
    {
        $user = $this->createPatient();
        $first = $this->book($user, $this->sessionOn('2026-10-13'))->booking_code;
        $second = $this->book($user, $this->sessionOn('2026-10-14'), AppointmentStatus::Pending)->booking_code;

        $this->history($user, $query)
            ->assertOk()
            ->assertSee(self::WARNING)
            ->assertSee($first)
            ->assertSee($second);
    }

    // --- PAS-10: jadwal_berubah --------------------------------------------------------------

    public function test_the_schedule_changed_filter_keeps_only_own_changed_appointments(): void
    {
        $owner = $this->createPatient(['email' => 'a@contoh.test']);
        $other = $this->createPatient(['email' => 'b@contoh.test']);

        $changed = $this->book($owner, $this->sessionOn('2026-10-13'), AppointmentStatus::Confirmed, ['schedule_changed_at' => now()->subDay()])->booking_code;
        $plainOne = $this->book($owner, $this->sessionOn('2026-10-14'))->booking_code;
        $plainTwo = $this->book($owner, $this->sessionOn('2026-10-15'))->booking_code;
        $foreignChanged = $this->book($other, $this->sessionOn('2026-10-16'), AppointmentStatus::Confirmed, ['schedule_changed_at' => now()->subDay()])->booking_code;

        $this->history($owner, ['jadwal_berubah' => '1'])
            ->assertOk()
            ->assertSee($changed)
            ->assertDontSee($plainOne)
            ->assertDontSee($plainTwo)
            ->assertDontSee($foreignChanged)
            ->assertDontSee(self::WARNING)
            ->assertSee('Menampilkan 1–1 dari 1 data')
            ->assertSee('name="jadwal_berubah"', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function ignoredScheduleChangedValues(): array
    {
        return [
            'nol' => ['0'],
            'teks' => ['abc'],
            'spasi' => [' '],
            'dua' => ['2'],
            'benar teks' => ['true'],
            'kosong' => [''],
        ];
    }

    #[DataProvider('ignoredScheduleChangedValues')]
    public function test_other_schedule_changed_values_are_ignored_and_show_the_full_list(string $value): void
    {
        $owner = $this->createPatient(['email' => 'a@contoh.test']);
        $changed = $this->book($owner, $this->sessionOn('2026-10-13'), AppointmentStatus::Confirmed, ['schedule_changed_at' => now()->subDay()])->booking_code;
        $plain = $this->book($owner, $this->sessionOn('2026-10-14'))->booking_code;

        $this->history($owner, ['jadwal_berubah' => $value])
            ->assertOk()
            ->assertSee($changed)
            ->assertSee($plain)
            ->assertSee('Menampilkan 1–2 dari 2 data');
    }

    public function test_the_schedule_changed_filter_combines_with_status_and_is_kept_in_the_form(): void
    {
        $owner = $this->createPatient();
        $changedConfirmed = $this->book($owner, $this->sessionOn('2026-10-13'), AppointmentStatus::Confirmed, ['schedule_changed_at' => now()->subDay()])->booking_code;
        $changedPending = $this->book($owner, $this->sessionOn('2026-10-14'), AppointmentStatus::Pending, ['schedule_changed_at' => now()->subDay()])->booking_code;

        $response = $this->history($owner, ['jadwal_berubah' => '1', 'status' => 'pending'])->assertOk();

        $response->assertSee($changedPending)->assertDontSee($changedConfirmed);
        $this->assertMatchesRegularExpression('#name="jadwal_berubah"[^>]*\schecked#', $response->getContent());
    }

    // --- SEK-15 ------------------------------------------------------------------------------

    public function test_the_doctor_name_is_escaped_in_the_history(): void
    {
        $user = $this->createPatient();
        $evil = $this->createDoctor(['name' => '<script>alert(1)</script>', 'title_prefix' => '', 'title_suffix' => ''], $this->clinic);
        $this->book($user, $this->sessionOn('2026-10-13', '09:00:00', '12:00:00', $evil));

        $this->history($user)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
