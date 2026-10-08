<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\WebsiteSetting;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingRules;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F6-R11, F.5 #1-#8: BookingRules::assertBookable(). Semua batas waktu diuji lewat parameter `$now`;
 * `travelTo` hanya menetapkan "hari ini" bagi factory. Sesi bawaan: Selasa 6 Oktober 2026 pukul 09.00-12.00,
 * kuota 5, dokter dan poliklinik baru.
 */
class BookingRulesTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
    }

    // --- pembantu ---

    private function rules(int $windowDays = 30, int $onlineCutoffMinutes = 60, int $maxActive = 3): BookingRules
    {
        return new BookingRules($windowDays, $onlineCutoffMinutes, 120, 60, $maxActive, false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sessionAttributes(array $overrides = []): array
    {
        return array_merge([
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 5,
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeSession(array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge(['doctor_schedule_id' => null], $this->sessionAttributes($overrides)));
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime);
    }

    private function officer(): Actor
    {
        return Actor::staff($this->createStaff('registration-officer'));
    }

    private function check(
        PatientProfile $profile,
        PracticeSession $session,
        Actor $actor,
        ?BookingRules $rules = null,
        ?BookingData $data = null,
        ?CarbonImmutable $now = null,
    ): void {
        ($rules ?? $this->rules())->assertBookable(
            $profile,
            $session,
            $data ?? new BookingData(PaymentType::Umum),
            $actor,
            $now ?? CarbonImmutable::now(),
        );
    }

    private function assertRejected(string $code, callable $call): BookingException
    {
        try {
            $call();
        } catch (BookingException $e) {
            $this->assertSame($code, $e->errorCode, 'Kode galat: '.$e->getMessage());

            return $e;
        }

        $this->fail("Seharusnya ditolak dengan {$code}, tetapi diterima.");
    }

    private function assertAccepted(callable $call): void
    {
        $call();

        $this->addToAssertionCount(1);
    }

    /**
     * Sesi dengan satu kondisi yang membuat F.5 #1 gagal; `$condition` = nilai dari `unavailableConditions()`.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function unavailableSession(string $condition, array $overrides = []): PracticeSession
    {
        $clinic = Clinic::factory()->create([
            'is_active' => $condition !== 'clinic_inactive',
            'is_bookable' => $condition !== 'clinic_not_bookable',
        ]);

        $doctorFactory = match ($condition) {
            'doctor_inactive' => Doctor::factory()->inactive(),
            'doctor_archived' => Doctor::factory()->archived(),
            default => Doctor::factory(),
        };
        $doctor = $doctorFactory->create(['clinic_id' => $clinic->id]);

        $sessionFactory = match ($condition) {
            'closed' => PracticeSession::factory()->closed(),
            'needs_review' => PracticeSession::factory()->needsReview(['source' => PracticeSession::SOURCE_MANUAL_TIME]),
            'cancelled' => PracticeSession::factory()->cancelled(),
            default => PracticeSession::factory(),
        };

        return $sessionFactory->manual()->create(array_merge($this->sessionAttributes($overrides), [
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]));
    }

    /**
     * Pendaftaran aktif pasien pada tiga sesi berbeda tanggal (6, 7, 8 Oktober; status pending, confirmed,
     * checked_in) sehingga hitungan "aktif mendatang" = 3 tanpa saling bertabrakan.
     *
     * @return list<PracticeSession>
     */
    private function bookThreeUpcoming(PatientProfile $profile): array
    {
        $sessions = [];

        foreach ([['2026-10-06', AppointmentStatus::Pending], ['2026-10-07', AppointmentStatus::Confirmed], ['2026-10-08', AppointmentStatus::CheckedIn]] as [$date, $status]) {
            $session = $this->makeSession(['session_date' => $date]);
            $this->bookAppointment($session, $status, ['patient_profile_id' => $profile->id]);
            $sessions[] = $session;
        }

        return $sessions;
    }

    // --- jalur normal ---

    public function test_a_normal_session_passes_every_rule_for_both_channels(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $session, Actor::patient($user)));
        $this->assertAccepted(fn () => $this->check($user->patientProfile, $session, $this->officer()));
    }

    public function test_the_profile_state_is_not_checked_here_f6_r11(): void
    {
        // Profil/akun nonaktif ditolak middleware, Policy, dan pemeriksaan ulang di book() (BookingException::profileInactive()).
        $user = $this->createPatient([], ['is_active' => false]);

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $this->makeSession(), Actor::patient($user)));
    }

    // --- #1 SESSION_UNAVAILABLE (BKG-05, JDW-03, JDW-04) ---

    /** @return array<string, array{string}> */
    public static function unavailableConditions(): array
    {
        return [
            'sesi ditutup' => ['closed'],
            'sesi menunggu tinjauan' => ['needs_review'],
            'sesi dibatalkan (cuti, libur, atau lainnya)' => ['cancelled'],
            'dokter nonaktif' => ['doctor_inactive'],
            'dokter diarsipkan' => ['doctor_archived'],
            'poliklinik nonaktif' => ['clinic_inactive'],
            'poliklinik tidak dapat dipesan' => ['clinic_not_bookable'],
        ];
    }

    #[DataProvider('unavailableConditions')]
    public function test_bkg_05_an_unavailable_session_is_rejected_for_both_channels(string $condition): void
    {
        $user = $this->createPatient();
        $session = $this->unavailableSession($condition);

        $online = $this->assertRejected('SESSION_UNAVAILABLE', fn () => $this->check($user->patientProfile, $session, Actor::patient($user)));
        $this->assertRejected('SESSION_UNAVAILABLE', fn () => $this->check($user->patientProfile, $session, $this->officer()));

        $this->assertSame('Sesi praktik ini tidak tersedia untuk pendaftaran. Silakan pilih sesi lain.', $online->getMessage());
        $this->assertSame($session->id, $online->context['practice_session_id']);
    }

    // --- #2 OUTSIDE_WINDOW (BKG-06) ---

    /** @return array<string, array{string, bool}> */
    public static function windowDates(): array
    {
        // "Hari ini" = Senin 5 Oktober 2026; jendela bawaan 30 hari.
        return [
            'hari ini' => ['2026-10-05', true],
            'besok' => ['2026-10-06', true],
            'hari ke-30 (batas terakhir)' => ['2026-11-04', true],
            'hari ke-31' => ['2026-11-05', false],
            'kemarin (juga melewati cutoff, tetapi #2 lebih dulu)' => ['2026-10-04', false],
            'jauh di masa lalu' => ['2026-09-01', false],
            'jauh di masa depan' => ['2027-01-01', false],
        ];
    }

    #[DataProvider('windowDates')]
    public function test_bkg_06_the_default_thirty_day_window_has_exact_edges(string $date, bool $accepted): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession(['session_date' => $date, 'start_time' => '20:00:00', 'end_time' => '22:00:00']);
        $call = fn () => $this->check($user->patientProfile, $session, Actor::patient($user), now: $this->at('2026-10-05 10:00:00'));

        if ($accepted) {
            $this->assertAccepted($call);

            return;
        }

        $e = $this->assertRejected('OUTSIDE_WINDOW', $call);
        $this->assertSame('Pendaftaran hanya dapat dilakukan untuk tanggal mulai hari ini sampai 30 hari ke depan.', $e->getMessage());
        $this->assertSame($session->id, $e->context['practice_session_id']);
    }

    /** @return array<string, array{string, bool}> */
    public static function sevenDayWindowDates(): array
    {
        return [
            'hari ke-7' => ['2026-10-12', true],
            'hari ke-8' => ['2026-10-13', false],
        ];
    }

    #[DataProvider('sevenDayWindowDates')]
    public function test_bkg_06_a_shorter_window_moves_the_edge(string $date, bool $accepted): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession(['session_date' => $date, 'start_time' => '20:00:00', 'end_time' => '22:00:00']);
        $call = fn () => $this->check($user->patientProfile, $session, Actor::patient($user), $this->rules(windowDays: 7), now: $this->at('2026-10-05 10:00:00'));

        if ($accepted) {
            $this->assertAccepted($call);

            return;
        }

        $e = $this->assertRejected('OUTSIDE_WINDOW', $call);
        $this->assertSame('Pendaftaran hanya dapat dilakukan untuk tanggal mulai hari ini sampai 7 hari ke depan.', $e->getMessage());
    }

    public function test_the_window_also_applies_to_the_counter_channel(): void
    {
        $user = $this->createPatient();
        $beyond = $this->makeSession(['session_date' => '2026-11-05']);
        $edge = $this->makeSession(['session_date' => '2026-11-04']);
        $officer = $this->officer();

        $this->assertRejected('OUTSIDE_WINDOW', fn () => $this->check($user->patientProfile, $beyond, $officer, now: $this->at('2026-10-05 10:00:00')));
        $this->assertAccepted(fn () => $this->check($user->patientProfile, $edge, $officer, now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_the_window_moves_at_midnight_of_the_given_moment(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $actor = Actor::patient($user);
        $day30 = $this->makeSession(['session_date' => '2026-11-04']);
        $day31 = $this->makeSession(['session_date' => '2026-11-05']);

        $this->assertAccepted(fn () => $this->check($profile, $day30, $actor, now: $this->at('2026-10-05 23:59:59')));
        $this->assertRejected('OUTSIDE_WINDOW', fn () => $this->check($profile, $day31, $actor, now: $this->at('2026-10-05 23:59:59')));
        $this->assertAccepted(fn () => $this->check($profile, $day31, $actor, now: $this->at('2026-10-06 00:00:00')));
    }

    // --- #3 CUTOFF_PASSED (BKG-07, JDW-05) ---

    /** @return array<string, array{string, bool}> */
    public static function onlineCutoffMoments(): array
    {
        // Sesi 6 Oktober 2026 pukul 09.00-12.00, cutoff online 60 menit => batas 08.00.00.
        return [
            '120 menit sebelum mulai' => ['07:00:00', true],
            '61 menit sebelum mulai (start-61)' => ['07:59:00', true],
            '60 menit lewat 1 detik (satu detik sebelum batas)' => ['07:59:59', true],
            'tepat 60 menit sebelum mulai (start-60)' => ['08:00:00', false],
            'satu detik sesudah batas' => ['08:00:01', false],
            '59 menit sebelum mulai (start-59)' => ['08:01:00', false],
            'tepat saat mulai' => ['09:00:00', false],
            'satu detik sebelum berakhir' => ['11:59:59', false],
            'tepat saat berakhir' => ['12:00:00', false],
        ];
    }

    #[DataProvider('onlineCutoffMoments')]
    public function test_bkg_07_online_cutoff_is_exactly_sixty_minutes_before_the_start(string $time, bool $accepted): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $call = fn () => $this->check($user->patientProfile, $session, Actor::patient($user), now: $this->at('2026-10-06 '.$time));

        if ($accepted) {
            $this->assertAccepted($call);

            return;
        }

        $e = $this->assertRejected('CUTOFF_PASSED', $call);
        $this->assertSame(
            'Pendaftaran online untuk sesi ini sudah ditutup (60 menit sebelum sesi dimulai). Silakan datang ke loket pendaftaran atau hubungi rumah sakit.',
            $e->getMessage(),
        );
    }

    /** @return array<string, array{string, bool}> */
    public static function counterCutoffMoments(): array
    {
        return [
            '60 menit sebelum mulai (online sudah tutup, loket buka)' => ['08:00:00', true],
            '10 menit sebelum mulai (start-10)' => ['08:50:00', true],
            'tepat saat mulai' => ['09:00:00', true],
            'satu detik sebelum berakhir' => ['11:59:59', true],
            'tepat saat berakhir (end)' => ['12:00:00', false],
            'satu menit sesudah berakhir (end+1)' => ['12:01:00', false],
        ];
    }

    #[DataProvider('counterCutoffMoments')]
    public function test_bkg_07_the_counter_channel_closes_at_the_end_of_the_session(string $time, bool $accepted): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $call = fn () => $this->check($user->patientProfile, $session, $this->officer(), now: $this->at('2026-10-06 '.$time));

        if ($accepted) {
            $this->assertAccepted($call);

            return;
        }

        $e = $this->assertRejected('CUTOFF_PASSED', $call);
        $this->assertSame('Sesi ini sudah berakhir.', $e->getMessage());
        $this->assertSame($session->id, $e->context['practice_session_id']);
    }

    public function test_bkg_07_the_online_cutoff_follows_the_setting_value(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $rules = $this->rules(onlineCutoffMinutes: 90);

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $session, Actor::patient($user), $rules, now: $this->at('2026-10-06 07:29:59')));

        $e = $this->assertRejected('CUTOFF_PASSED', fn () => $this->check($user->patientProfile, $session, Actor::patient($user), $rules, now: $this->at('2026-10-06 07:30:00')));
        $this->assertStringContainsString('(90 menit sebelum sesi dimulai)', $e->getMessage());
    }

    public function test_jdw_05_a_session_that_already_ended_today_is_rejected_for_both_channels(): void
    {
        $user = $this->createPatient();
        $ended = $this->makeSession(['session_date' => '2026-10-05', 'start_time' => '06:00:00', 'end_time' => '09:00:00']);

        $online = $this->assertRejected('CUTOFF_PASSED', fn () => $this->check($user->patientProfile, $ended, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
        $counter = $this->assertRejected('CUTOFF_PASSED', fn () => $this->check($user->patientProfile, $ended, $this->officer(), now: $this->at('2026-10-05 10:00:00')));

        $this->assertStringStartsWith('Pendaftaran online', $online->getMessage());
        $this->assertSame('Sesi ini sudah berakhir.', $counter->getMessage());
    }

    public function test_jdw_05_a_later_session_today_is_bookable_until_one_hour_before_it_starts(): void
    {
        $user = $this->createPatient();
        $later = $this->makeSession(['session_date' => '2026-10-05', 'start_time' => '14:00:00', 'end_time' => '17:00:00']);
        $actor = Actor::patient($user);

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $later, $actor, now: $this->at('2026-10-05 10:00:00')));
        $this->assertAccepted(fn () => $this->check($user->patientProfile, $later, $actor, now: $this->at('2026-10-05 12:59:59')));
        $this->assertRejected('CUTOFF_PASSED', fn () => $this->check($user->patientProfile, $later, $actor, now: $this->at('2026-10-05 13:00:00')));

        $officer = $this->officer();
        $this->assertAccepted(fn () => $this->check($user->patientProfile, $later, $officer, now: $this->at('2026-10-05 16:59:59')));
        $this->assertRejected('CUTOFF_PASSED', fn () => $this->check($user->patientProfile, $later, $officer, now: $this->at('2026-10-05 17:00:00')));
    }

    // --- #4 QUOTA_FULL (BKG-04) ---

    public function test_bkg_04_a_full_session_is_rejected_for_both_channels(): void
    {
        $user = $this->createPatient();
        $full = $this->makeSession(['quota' => 2, 'booked_count' => 2, 'last_queue_number' => 2]);

        $online = $this->assertRejected('QUOTA_FULL', fn () => $this->check($user->patientProfile, $full, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
        $this->assertRejected('QUOTA_FULL', fn () => $this->check($user->patientProfile, $full, $this->officer(), now: $this->at('2026-10-05 10:00:00')));

        $this->assertSame('Kuota sesi ini sudah penuh. Silakan pilih tanggal atau sesi lain.', $online->getMessage());
    }

    public function test_bkg_04_one_remaining_place_is_enough(): void
    {
        $user = $this->createPatient();
        $almostFull = $this->makeSession(['quota' => 2, 'booked_count' => 1, 'last_queue_number' => 1]);

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $almostFull, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_the_quota_check_reads_the_counter_of_the_given_instance(): void
    {
        // Pemanggil (book()) wajib mengoper baris terkunci yang segar; assertBookable tidak memuat ulang.
        $user = $this->createPatient();
        $session = $this->makeSession(['quota' => 2, 'booked_count' => 0]);
        $session->booked_count = 2;

        $this->assertRejected('QUOTA_FULL', fn () => $this->check($user->patientProfile, $session, Actor::patient($user)));
        $this->assertSame(0, (int) PracticeSession::query()->whereKey($session->id)->value('booked_count'));
    }

    // --- #5 DUPLICATE_BOOKING (BKG-02) ---

    /** @return array<string, array{AppointmentStatus, bool}> */
    public static function duplicateStatuses(): array
    {
        return [
            'pending' => [AppointmentStatus::Pending, true],
            'confirmed' => [AppointmentStatus::Confirmed, true],
            'checked_in' => [AppointmentStatus::CheckedIn, true],
            'completed (tidak aktif)' => [AppointmentStatus::Completed, false],
            'cancelled (tidak aktif)' => [AppointmentStatus::Cancelled, false],
            'no_show (tidak aktif)' => [AppointmentStatus::NoShow, false],
        ];
    }

    #[DataProvider('duplicateStatuses')]
    public function test_bkg_02_only_an_active_appointment_on_the_same_session_is_a_duplicate(AppointmentStatus $status, bool $rejected): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $session = $this->makeSession();
        $existing = $this->bookAppointment($session, $status, ['patient_profile_id' => $profile->id]);
        $call = fn () => $this->check($profile, $session, Actor::patient($user), now: $this->at('2026-10-05 10:00:00'));

        if (! $rejected) {
            $this->assertAccepted($call);

            return;
        }

        $e = $this->assertRejected('DUPLICATE_BOOKING', $call);
        $this->assertSame('Anda sudah memiliki pendaftaran aktif pada sesi dokter ini.', $e->getMessage());
        $this->assertSame($existing->booking_code, $e->context['appointment_code']);
        $this->assertSame($session->id, $e->context['practice_session_id']);
    }

    public function test_the_duplicate_message_names_the_patient_for_the_counter_channel(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $user->patientProfile->id]);

        $e = $this->assertRejected('DUPLICATE_BOOKING', fn () => $this->check($user->patientProfile, $session, $this->officer(), now: $this->at('2026-10-05 10:00:00')));

        $this->assertSame('Pasien ini sudah memiliki pendaftaran aktif pada sesi dokter ini.', $e->getMessage());
    }

    public function test_another_patients_appointment_on_the_session_is_not_a_duplicate(): void
    {
        $user = $this->createPatient();
        $other = $this->createPatient();
        $session = $this->makeSession();
        $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $other->patientProfile->id]);

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $session, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    // --- #6 OVERLAPPING_BOOKING (BKG-03) ---

    /** @return array<string, array{string, string, bool}> */
    public static function overlapCases(): array
    {
        // Pendaftaran yang sudah ada: 09.00-12.00 pada 7 Oktober 2026. Kolom: mulai, selesai, bertabrakan?
        return [
            'beririsan di akhir (11.00-13.00)' => ['11:00:00', '13:00:00', true],
            'berimpit tepat di batas akhir (12.00-14.00)' => ['12:00:00', '14:00:00', false],
            'berimpit tepat di batas awal (07.00-09.00)' => ['07:00:00', '09:00:00', false],
            'beririsan di awal (08.00-10.00)' => ['08:00:00', '10:00:00', true],
            'berada di dalam (09.30-10.30)' => ['09:30:00', '10:30:00', true],
            'melingkupi (08.00-13.00)' => ['08:00:00', '13:00:00', true],
            'jam sama persis (09.00-12.00)' => ['09:00:00', '12:00:00', true],
            'satu detik sebelum batas akhir' => ['11:59:59', '14:00:00', true],
            'satu detik sesudah batas akhir' => ['12:00:01', '14:00:00', false],
            'jauh sesudahnya' => ['14:00:00', '16:00:00', false],
        ];
    }

    #[DataProvider('overlapCases')]
    public function test_bkg_03_overlap_is_strict_and_spans_doctors(string $start, string $end, bool $overlaps): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $existing = $this->makeSession(['session_date' => '2026-10-07']);
        $booked = $this->bookAppointment($existing, AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $target = $this->makeSession(['session_date' => '2026-10-07', 'start_time' => $start, 'end_time' => $end]);
        $call = fn () => $this->check($profile, $target, Actor::patient($user), now: $this->at('2026-10-05 10:00:00'));

        $this->assertNotSame($existing->doctor_id, $target->doctor_id, 'Benturan berlaku lintas dokter.');

        if (! $overlaps) {
            $this->assertAccepted($call);

            return;
        }

        $e = $this->assertRejected('OVERLAPPING_BOOKING', $call);
        $this->assertSame($booked->booking_code, $e->context['appointment_code']);
        $this->assertSame($target->id, $e->context['practice_session_id']);
    }

    public function test_the_overlap_message_names_the_conflicting_doctor_and_hours(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $existing = $this->makeSession(['session_date' => '2026-10-07']);
        $this->bookAppointment($existing, AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $target = $this->makeSession(['session_date' => '2026-10-07', 'start_time' => '11:00:00', 'end_time' => '13:00:00']);
        $doctorName = $existing->load('doctor')->doctor->display_name;

        $online = $this->assertRejected('OVERLAPPING_BOOKING', fn () => $this->check($profile, $target, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
        $counter = $this->assertRejected('OVERLAPPING_BOOKING', fn () => $this->check($profile, $target, $this->officer(), now: $this->at('2026-10-05 10:00:00')));

        $this->assertSame("Jadwal ini bertabrakan dengan pendaftaran lain Anda pada tanggal yang sama ({$doctorName}, 09.00–12.00).", $online->getMessage());
        $this->assertSame("Jadwal ini bertabrakan dengan pendaftaran lain pasien ini pada tanggal yang sama ({$doctorName}, 09.00–12.00).", $counter->getMessage());
    }

    public function test_an_overlap_with_another_session_of_the_same_doctor_is_rejected(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $existing = $this->makeSession(['session_date' => '2026-10-07']);
        $this->bookAppointment($existing, AppointmentStatus::Pending, ['patient_profile_id' => $profile->id]);
        $sameDoctor = $this->makeSession(['session_date' => '2026-10-07', 'start_time' => '11:00:00', 'end_time' => '13:00:00', 'doctor_id' => $existing->doctor_id]);

        $this->assertRejected('OVERLAPPING_BOOKING', fn () => $this->check($profile, $sameDoctor, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_the_same_hours_on_another_date_do_not_overlap(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $this->bookAppointment($this->makeSession(['session_date' => '2026-10-07']), AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $nextDay = $this->makeSession(['session_date' => '2026-10-08']);

        $this->assertAccepted(fn () => $this->check($profile, $nextDay, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    /** @return array<string, array{AppointmentStatus}> */
    public static function inactiveStatuses(): array
    {
        return [
            'cancelled' => [AppointmentStatus::Cancelled],
            'completed' => [AppointmentStatus::Completed],
            'no_show' => [AppointmentStatus::NoShow],
        ];
    }

    #[DataProvider('inactiveStatuses')]
    public function test_an_inactive_appointment_does_not_overlap(AppointmentStatus $status): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $this->bookAppointment($this->makeSession(['session_date' => '2026-10-07']), $status, ['patient_profile_id' => $profile->id]);
        $target = $this->makeSession(['session_date' => '2026-10-07', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

        $this->assertAccepted(fn () => $this->check($profile, $target, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_another_patients_appointment_does_not_overlap(): void
    {
        $user = $this->createPatient();
        $other = $this->createPatient();
        $this->bookAppointment($this->makeSession(['session_date' => '2026-10-07']), AppointmentStatus::Confirmed, ['patient_profile_id' => $other->patientProfile->id]);
        $target = $this->makeSession(['session_date' => '2026-10-07', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $target, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    // --- #7 ACTIVE_LIMIT_REACHED (BKG-08) ---

    public function test_bkg_08_the_fourth_online_booking_is_rejected_but_the_counter_is_exempt(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $this->bookThreeUpcoming($profile);
        $fourth = $this->makeSession(['session_date' => '2026-10-09']);
        $now = $this->at('2026-10-05 10:00:00');

        $e = $this->assertRejected('ACTIVE_LIMIT_REACHED', fn () => $this->check($profile, $fourth, Actor::patient($user), now: $now));
        $this->assertSame(
            'Anda sudah memiliki 3 pendaftaran aktif yang akan datang (batas maksimum). Selesaikan atau batalkan salah satunya terlebih dahulu.',
            $e->getMessage(),
        );
        $this->assertSame($fourth->id, $e->context['practice_session_id']);

        $this->assertAccepted(fn () => $this->check($profile, $fourth, $this->officer(), now: $now));
    }

    public function test_bkg_08_the_limit_follows_the_setting_value(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $this->bookThreeUpcoming($profile);
        $fourth = $this->makeSession(['session_date' => '2026-10-09']);
        $now = $this->at('2026-10-05 10:00:00');

        $this->assertAccepted(fn () => $this->check($profile, $fourth, Actor::patient($user), $this->rules(maxActive: 4), now: $now));

        $e = $this->assertRejected('ACTIVE_LIMIT_REACHED', fn () => $this->check($profile, $fourth, Actor::patient($user), $this->rules(maxActive: 2), now: $now));
        $this->assertStringStartsWith('Anda sudah memiliki 2 pendaftaran aktif', $e->getMessage());
    }

    public function test_only_active_upcoming_appointments_of_the_patient_are_counted(): void
    {
        $user = $this->createPatient();
        $other = $this->createPatient();
        $profile = $user->patientProfile;

        foreach ([['2026-10-06', AppointmentStatus::Pending], ['2026-10-07', AppointmentStatus::Confirmed]] as [$date, $status]) {
            $this->bookAppointment($this->makeSession(['session_date' => $date]), $status, ['patient_profile_id' => $profile->id]);
        }

        foreach ([['2026-10-08', AppointmentStatus::Completed], ['2026-10-09', AppointmentStatus::Cancelled], ['2026-10-10', AppointmentStatus::NoShow]] as [$date, $status]) {
            $this->bookAppointment($this->makeSession(['session_date' => $date]), $status, ['patient_profile_id' => $profile->id]);
        }

        foreach (['2026-10-11', '2026-10-12', '2026-10-13'] as $date) {
            $this->bookAppointment($this->makeSession(['session_date' => $date]), AppointmentStatus::Confirmed, ['patient_profile_id' => $other->patientProfile->id]);
        }

        $target = $this->makeSession(['session_date' => '2026-10-14']);

        $this->assertAccepted(fn () => $this->check($profile, $target, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_bkg_08_a_session_of_today_that_already_ended_is_not_counted(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $today = $this->makeSession(['session_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $this->bookAppointment($today, AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $this->bookAppointment($this->makeSession(['session_date' => '2026-10-06']), AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $this->bookAppointment($this->makeSession(['session_date' => '2026-10-07']), AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $target = $this->makeSession(['session_date' => '2026-10-09']);
        $actor = Actor::patient($user);

        // Satu detik sebelum berakhir: masih dihitung (3 aktif mendatang) => ditolak.
        $this->assertRejected('ACTIVE_LIMIT_REACHED', fn () => $this->check($profile, $target, $actor, now: $this->at('2026-10-05 09:59:59')));
        // Tepat saat berakhir: tidak lagi dihitung (2 aktif mendatang) => diterima.
        $this->assertAccepted(fn () => $this->check($profile, $target, $actor, now: $this->at('2026-10-05 10:00:00')));
    }

    // --- #8 INVALID_PAYMENT (BKG-09) ---

    /** @return array<string, array{BookingData}> */
    public static function invalidPayments(): array
    {
        return [
            'bpjs tanpa nomor' => [new BookingData(PaymentType::Bpjs)],
            'bpjs 12 digit' => [new BookingData(PaymentType::Bpjs, '000990000001')],
            'bpjs 14 digit' => [new BookingData(PaymentType::Bpjs, '00099000000112')],
            'bpjs berisi huruf' => [new BookingData(PaymentType::Bpjs, '00099000000AB')],
            'bpjs berspasi' => [new BookingData(PaymentType::Bpjs, '0009 9000 0001')],
            'bpjs dengan newline di akhir' => [new BookingData(PaymentType::Bpjs, "0009900000011\n")],
            'bpjs dengan mitra asuransi' => [new BookingData(PaymentType::Bpjs, '0009900000011', 1)],
            'asuransi tanpa mitra' => [new BookingData(PaymentType::Asuransi)],
            'asuransi tanpa mitra tetapi dengan polis' => [new BookingData(PaymentType::Asuransi, 'POL-1')],
            'umum dengan nomor' => [new BookingData(PaymentType::Umum, '0009900000011')],
            'umum dengan mitra' => [new BookingData(PaymentType::Umum, null, 1)],
        ];
    }

    #[DataProvider('invalidPayments')]
    public function test_bkg_09_invalid_payment_data_is_rejected(BookingData $data): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();

        $e = $this->assertRejected('INVALID_PAYMENT', fn () => $this->check($user->patientProfile, $session, Actor::patient($user), data: $data, now: $this->at('2026-10-05 10:00:00')));

        $this->assertSame(
            'Data pembayaran tidak sesuai: nomor BPJS (13 digit) wajib untuk pembayaran BPJS dan mitra asuransi wajib untuk pembayaran asuransi.',
            $e->getMessage(),
        );
        $this->assertSame($session->id, $e->context['practice_session_id']);
    }

    /** @return array<string, array{BookingData}> */
    public static function validPayments(): array
    {
        return [
            'umum tanpa isian' => [new BookingData(PaymentType::Umum)],
            'umum dengan keluhan dan simpan BPJS' => [new BookingData(PaymentType::Umum, null, null, 'Batuk', true)],
            'bpjs 13 digit' => [new BookingData(PaymentType::Bpjs, '0009900000011')],
            'bpjs 13 digit diawali nol dan simpan BPJS' => [new BookingData(PaymentType::Bpjs, '0000000000000', null, null, true)],
        ];
    }

    #[DataProvider('validPayments')]
    public function test_bkg_09_valid_payment_data_is_accepted(BookingData $data): void
    {
        $user = $this->createPatient();

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $this->makeSession(), Actor::patient($user), data: $data, now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_bkg_09_insurance_needs_an_existing_active_partner(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $session = $this->makeSession();
        $active = InsurancePartner::factory()->create();
        $inactive = InsurancePartner::factory()->inactive()->create();
        $now = $this->at('2026-10-05 10:00:00');
        $actor = Actor::patient($user);

        $this->assertAccepted(fn () => $this->check($profile, $session, $actor, data: new BookingData(PaymentType::Asuransi, null, $active->id), now: $now));
        $this->assertAccepted(fn () => $this->check($profile, $session, $actor, data: new BookingData(PaymentType::Asuransi, 'POL/2026-001', $active->id), now: $now));
        $this->assertAccepted(fn () => $this->check($profile, $session, $actor, data: new BookingData(PaymentType::Asuransi, str_repeat('A', 30), $active->id), now: $now));

        $rejected = [
            'mitra nonaktif' => new BookingData(PaymentType::Asuransi, null, $inactive->id),
            'mitra tidak ada' => new BookingData(PaymentType::Asuransi, null, 999999),
            'polis 31 karakter' => new BookingData(PaymentType::Asuransi, str_repeat('A', 31), $active->id),
            'polis berspasi' => new BookingData(PaymentType::Asuransi, 'POL 001', $active->id),
            'polis berisi karakter terlarang' => new BookingData(PaymentType::Asuransi, 'POL#001', $active->id),
            'polis dengan newline di akhir' => new BookingData(PaymentType::Asuransi, "POL-001\n", $active->id),
        ];

        foreach ($rejected as $label => $data) {
            $e = $this->assertRejected('INVALID_PAYMENT', fn () => $this->check($profile, $session, $actor, data: $data, now: $now));
            $this->assertSame('INVALID_PAYMENT', $e->errorCode, $label);
        }
    }

    public function test_the_payment_rule_also_applies_to_the_counter_channel(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $officer = $this->officer();

        $this->assertRejected('INVALID_PAYMENT', fn () => $this->check($user->patientProfile, $session, $officer, data: new BookingData(PaymentType::Bpjs, '12345'), now: $this->at('2026-10-05 10:00:00')));
        $this->assertAccepted(fn () => $this->check($user->patientProfile, $session, $officer, data: new BookingData(PaymentType::Bpjs, '0009900000011'), now: $this->at('2026-10-05 10:00:00')));
    }

    // --- urutan aturan: yang pertama gagal menentukan kode ---

    public function test_rule_1_wins_over_the_window(): void
    {
        $user = $this->createPatient();
        $closedAndFar = $this->unavailableSession('closed', ['session_date' => '2027-01-01']);

        $this->assertRejected('SESSION_UNAVAILABLE', fn () => $this->check($user->patientProfile, $closedAndFar, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_rule_3_wins_over_the_quota(): void
    {
        $user = $this->createPatient();
        $fullAndLate = $this->makeSession(['quota' => 2, 'booked_count' => 2, 'last_queue_number' => 2]);

        $this->assertRejected('CUTOFF_PASSED', fn () => $this->check($user->patientProfile, $fullAndLate, Actor::patient($user), now: $this->at('2026-10-06 08:30:00')));
    }

    public function test_rule_4_wins_over_the_duplicate(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession(['quota' => 1]);
        $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $user->patientProfile->id]);

        $this->assertRejected('QUOTA_FULL', fn () => $this->check($user->patientProfile, $session, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_rule_5_wins_over_the_overlap(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $target = $this->makeSession(['session_date' => '2026-10-07']);
        $this->bookAppointment($target, AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $this->bookAppointment($this->makeSession(['session_date' => '2026-10-07', 'start_time' => '10:00:00', 'end_time' => '11:00:00']), AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);

        $this->assertRejected('DUPLICATE_BOOKING', fn () => $this->check($profile, $target, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_rule_6_wins_over_the_limit(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $this->bookThreeUpcoming($profile);
        $overlapping = $this->makeSession(['session_date' => '2026-10-07', 'start_time' => '11:00:00', 'end_time' => '13:00:00']);

        $this->assertRejected('OVERLAPPING_BOOKING', fn () => $this->check($profile, $overlapping, Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));
    }

    public function test_rule_7_wins_over_the_payment_but_the_counter_skips_it(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $this->bookThreeUpcoming($profile);
        $fourth = $this->makeSession(['session_date' => '2026-10-10']);
        $invalid = new BookingData(PaymentType::Bpjs);
        $now = $this->at('2026-10-05 10:00:00');

        $this->assertRejected('ACTIVE_LIMIT_REACHED', fn () => $this->check($profile, $fourth, Actor::patient($user), data: $invalid, now: $now));
        $this->assertRejected('INVALID_PAYMENT', fn () => $this->check($profile, $fourth, $this->officer(), data: $invalid, now: $now));
    }

    // --- SET-02: pengaturan memengaruhi aturan ---

    public function test_set_02_a_changed_window_takes_effect_immediately(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = $this->app->make(SettingsService::class);
        $user = $this->createPatient();
        $session = $this->makeSession(['session_date' => '2026-10-20']);
        $now = $this->at('2026-10-05 10:00:00');

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $session, Actor::patient($user), $settings->bookingRules(), now: $now));

        WebsiteSetting::query()->where('key', 'booking.window_days')->update(['value' => '7']);
        $settings->forget();

        $e = $this->assertRejected('OUTSIDE_WINDOW', fn () => $this->check($user->patientProfile, $session, Actor::patient($user), $settings->bookingRules(), now: $now));
        $this->assertStringContainsString('sampai 7 hari ke depan', $e->getMessage());
    }

    public function test_set_02_a_changed_cutoff_takes_effect_immediately(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = $this->app->make(SettingsService::class);
        $user = $this->createPatient();
        $session = $this->makeSession();
        $now = $this->at('2026-10-06 07:30:00');

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $session, Actor::patient($user), $settings->bookingRules(), now: $now));

        WebsiteSetting::query()->where('key', 'booking.online_cutoff_minutes')->update(['value' => '90']);
        $settings->forget();

        $e = $this->assertRejected('CUTOFF_PASSED', fn () => $this->check($user->patientProfile, $session, Actor::patient($user), $settings->bookingRules(), now: $now));
        $this->assertStringContainsString('(90 menit sebelum sesi dimulai)', $e->getMessage());
    }

    public function test_set_02_a_changed_active_limit_takes_effect_immediately(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = $this->app->make(SettingsService::class);
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $this->bookThreeUpcoming($profile);
        $fourth = $this->makeSession(['session_date' => '2026-10-09']);
        $now = $this->at('2026-10-05 10:00:00');

        $this->assertRejected('ACTIVE_LIMIT_REACHED', fn () => $this->check($profile, $fourth, Actor::patient($user), $settings->bookingRules(), now: $now));

        WebsiteSetting::query()->where('key', 'booking.max_active_per_patient')->update(['value' => '4']);
        $settings->forget();

        $this->assertAccepted(fn () => $this->check($profile, $fourth, Actor::patient($user), $settings->bookingRules(), now: $now));
    }

    // --- K-3: konsistensi dengan DoctorAvailabilityQuery untuk kasus `available` ---

    /** @return array<string, array{string, string, bool, bool}> */
    public static function agreementCases(): array
    {
        // Kolom: jenis sesi, jam "sekarang" pada 6 Oktober 2026, dapat dipesan online?, dapat dipesan di loket?
        return [
            'tersedia, jauh sebelum cutoff' => ['normal', '07:00:00', true, true],
            'tersedia, satu detik sebelum cutoff' => ['normal', '07:59:59', true, true],
            'tepat di cutoff online' => ['normal', '08:00:00', false, true],
            'sudah mulai tetapi belum berakhir' => ['normal', '11:59:59', false, true],
            'tepat berakhir' => ['normal', '12:00:00', false, false],
            'penuh' => ['full', '07:00:00', false, false],
            'tepat pada batas jendela 30 hari' => ['day_30', '07:00:00', true, true],
            'melewati batas jendela 30 hari' => ['day_31', '07:00:00', false, false],
            'ditutup petugas' => ['closed', '07:00:00', false, false],
            'dibatalkan' => ['cancelled', '07:00:00', false, false],
            'menunggu tinjauan' => ['needs_review', '07:00:00', false, false],
            'dokter nonaktif' => ['doctor_inactive', '07:00:00', false, false],
            'dokter diarsipkan' => ['doctor_archived', '07:00:00', false, false],
            'poliklinik nonaktif' => ['clinic_inactive', '07:00:00', false, false],
            'poliklinik tidak dapat dipesan' => ['clinic_not_bookable', '07:00:00', false, false],
        ];
    }

    #[DataProvider('agreementCases')]
    public function test_k3_assert_bookable_agrees_with_the_availability_query_for_available_sessions(string $kind, string $time, bool $online, bool $counter): void
    {
        $now = $this->at('2026-10-06 '.$time);
        $session = match ($kind) {
            'normal' => $this->makeSession(),
            'full' => $this->makeSession(['quota' => 2, 'booked_count' => 2, 'last_queue_number' => 2]),
            'day_30' => $this->makeSession(['session_date' => '2026-11-05']),
            'day_31' => $this->makeSession(['session_date' => '2026-11-06']),
            default => $this->unavailableSession($kind),
        };
        $user = $this->createPatient();
        $profile = $user->patientProfile;

        $availability = $this->app->make(DoctorAvailabilityQuery::class);
        $onlinePasses = $this->passes(fn () => $this->check($profile, $session, Actor::patient($user), now: $now));
        $counterPasses = $this->passes(fn () => $this->check($profile, $session, $this->officer(), now: $now));

        $this->assertSame($online, $availability->forSession($session, false, $now)->isBookableOnline);
        $this->assertSame($counter, $availability->forSession($session, true, $now)->isBookableCounter);
        $this->assertSame($online, $onlinePasses, 'assertBookable online harus sepakat dengan isBookableOnline.');
        $this->assertSame($counter, $counterPasses, 'assertBookable loket harus sepakat dengan isBookableCounter.');
    }

    // --- F6-R12: hanya baca; muat relasi tanpa lazy load ---

    public function test_f6_r12_assert_bookable_reads_only_and_takes_no_locks(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $partner = InsurancePartner::factory()->create();
        $this->bookAppointment($this->makeSession(['session_date' => '2026-10-07']), AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);
        $session = $this->makeSession();
        $appointmentsBefore = Appointment::query()->count();
        $counterBefore = [$session->fresh()->booked_count, $session->fresh()->last_queue_number];

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->check($profile, $session, Actor::patient($user), data: new BookingData(PaymentType::Asuransi, 'POL/2026-001', $partner->id), now: $this->at('2026-10-05 10:00:00'));

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertGreaterThanOrEqual(4, count($queries), 'Aturan #5-#8 masing-masing menjalankan satu kueri.');

        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/\bfor update\b|^\s*(insert|update|delete)\b/i', $sql, 'assertBookable tidak boleh mengunci atau menulis: '.$sql);
        }

        $this->assertSame($appointmentsBefore, Appointment::query()->count());
        $this->assertSame($counterBefore, [$session->fresh()->booked_count, $session->fresh()->last_queue_number]);
    }

    public function test_a_session_from_a_collection_is_checked_without_a_lazy_loading_violation(): void
    {
        $user = $this->createPatient();
        $a = $this->makeSession();
        $b = $this->makeSession(['session_date' => '2026-10-07']);
        $loaded = PracticeSession::query()->whereKey([$a->id, $b->id])->orderBy('id')->get();

        $this->assertFalse($loaded->first()->relationLoaded('doctor'));

        $this->assertAccepted(fn () => $this->check($user->patientProfile, $loaded->first(), Actor::patient($user), now: $this->at('2026-10-05 10:00:00')));

        $this->assertTrue($loaded->first()->relationLoaded('doctor'));
        $this->assertTrue($loaded->first()->relationLoaded('clinic'));
    }

    // --- pembantu bersama ---

    private function passes(callable $call): bool
    {
        try {
            $call();
        } catch (BookingException) {
            return false;
        }

        return true;
    }
}
