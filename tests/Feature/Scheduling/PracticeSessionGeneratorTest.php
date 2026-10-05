<?php

namespace Tests\Feature\Scheduling;

use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * 00 F.2, 11 §6.4, F3-R7, F3-R8, F3-R10. JDW-01, JDW-02, JDW-13 (sisi generator), JDW-03/04 (sesi baru yang
 * tercakup cuti/libur langsung `cancelled`).
 *
 * Waktu tes: Senin 5 Oktober 2026 pukul 08.00. Jendela 30 hari = 5 Oktober s.d. 4 November (inklusif):
 * Senin 5, 12, 19, 26 Okt dan 2 Nov; Rabu 7, 14, 21, 28 Okt dan 4 Nov.
 */
class PracticeSessionGeneratorTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    private const MONDAYS = ['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02'];

    private const WEDNESDAYS = ['2026-10-07', '2026-10-14', '2026-10-21', '2026-10-28', '2026-11-04'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 08:00:00');
        $this->seed(SettingsSeeder::class);
    }

    private function generator(): PracticeSessionGenerator
    {
        return app(PracticeSessionGenerator::class);
    }

    private function window(): int
    {
        return app(SettingsService::class)->int('booking.window_days');
    }

    private function generate(?DoctorSchedule $only = null): int
    {
        return $this->generator()->ensureRange(CarbonImmutable::today(), CarbonImmutable::today()->addDays($this->window()), $only);
    }

    /** @return list<string> */
    private function datesOf(DoctorSchedule $schedule): array
    {
        return PracticeSession::query()
            ->where('doctor_schedule_id', $schedule->id)
            ->orderBy('session_date')
            ->get()
            ->map(fn (PracticeSession $session): string => $session->session_date->toDateString())
            ->all();
    }

    // --- JDW-01 ---

    public function test_jdw01_generates_one_session_per_matching_weekday_with_the_template_values(): void
    {
        $doctor = Doctor::factory()->create();
        $monday = $this->createSchedule(['doctor_id' => $doctor->id, 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'quota' => 20]);
        $wednesday = $this->createSchedule(['doctor_id' => $doctor->id, 'day_of_week' => 3, 'start_time' => '13:00:00', 'end_time' => '16:30:00', 'quota' => 10]);

        $created = $this->generate();

        $this->assertSame(10, $created);
        $this->assertSame(self::MONDAYS, $this->datesOf($monday));
        $this->assertSame(self::WEDNESDAYS, $this->datesOf($wednesday));

        $session = PracticeSession::query()->where('doctor_schedule_id', $wednesday->id)->orderBy('session_date')->firstOrFail();
        $this->assertSame($doctor->id, $session->doctor_id);
        $this->assertSame($wednesday->clinic_id, $session->clinic_id);
        $this->assertSame('13:00:00', $session->start_time);
        $this->assertSame('16:30:00', $session->end_time);
        $this->assertSame(10, $session->quota);
        $this->assertSame(0, $session->booked_count);
        $this->assertSame(0, $session->last_queue_number);
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertNull($session->status_reason);
        $this->assertNull($session->schedule_exception_id);
        $this->assertNull($session->pending_change);
        $this->assertFalse($session->is_manually_modified);
        $this->assertNotNull($session->created_at);
        $this->assertNotNull($session->updated_at);
    }

    public function test_jdw01_templates_that_are_not_eligible_produce_no_sessions(): void
    {
        $eligible = $this->createSchedule(['day_of_week' => 1]);

        DoctorSchedule::factory()->inactive()->create(['day_of_week' => 1]);
        $this->createSchedule(['day_of_week' => 1, 'valid_from' => '2026-09-01', 'valid_until' => '2026-10-04']);
        $this->createSchedule(['day_of_week' => 1, 'valid_from' => '2026-12-01']);
        DoctorSchedule::factory()->create(['day_of_week' => 1, 'doctor_id' => Doctor::factory()->inactive()->create()->id]);
        DoctorSchedule::factory()->create(['day_of_week' => 1, 'doctor_id' => Doctor::factory()->archived()->create()->id]);
        DoctorSchedule::factory()->create(['day_of_week' => 1, 'clinic_id' => Clinic::factory()->inactive()->create()->id]);

        $created = $this->generate();

        $this->assertSame(5, $created);
        $this->assertSame([$eligible->id], PracticeSession::query()->pluck('doctor_schedule_id')->unique()->values()->all());
    }

    public function test_the_range_is_clamped_to_today_and_never_creates_past_sessions(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 1]);

        $created = $this->generator()->ensureRange(CarbonImmutable::today()->subDays(10), CarbonImmutable::today()->addDays(2));

        $this->assertSame(1, $created);
        $this->assertSame(['2026-10-05'], $this->datesOf($schedule));
        $this->assertSame(0, $this->generator()->ensureRange(CarbonImmutable::today()->subDays(10), CarbonImmutable::today()->subDay()));
        $this->assertSame(1, PracticeSession::query()->count());
    }

    public function test_the_end_of_the_range_is_inclusive_and_the_window_limits_it(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 3]);

        $this->generator()->ensureRange(CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-13'));
        $this->assertSame(['2026-10-07'], $this->datesOf($schedule));

        $this->generator()->ensureRange(CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-14'));
        $this->assertSame(['2026-10-07', '2026-10-14'], $this->datesOf($schedule));
    }

    public function test_the_validity_range_of_the_template_limits_the_sessions_on_both_ends(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 1, 'valid_from' => '2026-10-12', 'valid_until' => '2026-10-26']);

        $this->generate();

        $this->assertSame(['2026-10-12', '2026-10-19', '2026-10-26'], $this->datesOf($schedule));
    }

    public function test_only_limits_generation_to_one_template(): void
    {
        $first = $this->createSchedule(['day_of_week' => 1]);
        $second = $this->createSchedule(['day_of_week' => 1]);

        $created = $this->generate($first);

        $this->assertSame(5, $created);
        $this->assertSame(5, $this->generator()->ensureRange(CarbonImmutable::today(), CarbonImmutable::today()->addDays(30), $second));
        $this->assertSame(0, $this->generate($first));
        $this->assertSame(10, PracticeSession::query()->count());
    }

    public function test_an_ineligible_only_template_creates_nothing(): void
    {
        $inactive = DoctorSchedule::factory()->inactive()->create(['day_of_week' => 1]);

        $this->assertSame(0, $this->generate($inactive));
        $this->assertSame(0, PracticeSession::query()->count());
    }

    // --- JDW-02 dan JDW-13 ---

    public function test_jdw02_running_twice_creates_no_duplicates_and_returns_zero(): void
    {
        $this->createSchedule(['day_of_week' => 1]);

        $this->assertSame(5, $this->generate());
        $this->assertSame(0, $this->generate());
        $this->assertSame(5, PracticeSession::query()->count());
    }

    public function test_jdw02_manually_modified_and_booked_sessions_are_not_overwritten_by_regeneration(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 1, 'quota' => 20]);
        $this->generate();

        $manual = PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->where('session_date', '2026-10-12')->firstOrFail();
        $manual->forceFill(['quota' => 3, 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'status' => SessionStatus::Closed, 'is_manually_modified' => true])->save();

        $booked = PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->where('session_date', '2026-10-19')->firstOrFail();
        $this->bookAppointment($booked);

        $schedule->update(['quota' => 99, 'start_time' => '07:00:00']);

        $this->assertSame(0, $this->generate());

        $manual->refresh();
        $this->assertSame([3, '09:00:00', SessionStatus::Closed, true], [$manual->quota, $manual->start_time, $manual->status, $manual->is_manually_modified]);

        $booked->refresh();
        $this->assertSame([20, '08:00:00', 1], [$booked->quota, $booked->start_time, $booked->booked_count]);
    }

    public function test_jdw13_manual_sessions_without_a_template_are_never_created_or_touched(): void
    {
        $doctor = Doctor::factory()->create();
        $schedule = $this->createSchedule(['doctor_id' => $doctor->id, 'day_of_week' => 1]);
        $first = PracticeSession::factory()->manual()->create(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'session_date' => '2026-10-12', 'start_time' => '13:00:00', 'end_time' => '15:00:00', 'quota' => 7]);
        $second = PracticeSession::factory()->manual()->create(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'session_date' => '2026-10-12', 'start_time' => '16:00:00', 'end_time' => '18:00:00', 'quota' => 8]);

        $this->generate();
        $this->generate();

        $this->assertSame(2, PracticeSession::query()->whereNull('doctor_schedule_id')->count());
        $this->assertSame(7, $first->fresh()->quota);
        $this->assertSame(8, $second->fresh()->quota);
        $this->assertSame(5, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());
    }

    // --- JDW-03 dan JDW-04 (sisi generator) ---

    public function test_jdw03_new_sessions_inside_a_doctor_leave_are_created_cancelled_with_the_exception(): void
    {
        $doctor = Doctor::factory()->create();
        $monday = $this->createSchedule(['doctor_id' => $doctor->id, 'day_of_week' => 1]);
        $wednesday = $this->createSchedule(['doctor_id' => $doctor->id, 'day_of_week' => 3]);
        $leave = ScheduleException::factory()->leave()->create([
            'doctor_id' => $doctor->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-14', 'reason' => 'Cuti tahunan',
        ]);

        $this->generate();

        $cancelled = PracticeSession::query()->where('status', SessionStatus::Cancelled->value)->orderBy('session_date')->get();

        $this->assertSame(['2026-10-12', '2026-10-14'], $cancelled->map(fn (PracticeSession $s): string => $s->session_date->toDateString())->all());
        $this->assertSame([$leave->id], $cancelled->pluck('schedule_exception_id')->unique()->values()->all());
        $this->assertSame(['Cuti tahunan'], $cancelled->pluck('status_reason')->unique()->values()->all());
        $this->assertSame([$monday->id, $wednesday->id], $cancelled->pluck('doctor_schedule_id')->all());
        $this->assertSame(8, PracticeSession::query()->where('status', SessionStatus::Open->value)->count());
        $this->assertSame(5, PracticeSession::query()->where('doctor_schedule_id', $monday->id)->count());
        $this->assertSame(5, PracticeSession::query()->where('doctor_schedule_id', $wednesday->id)->count());
    }

    public function test_jdw04_a_hospital_holiday_cancels_the_sessions_of_every_doctor_on_that_date(): void
    {
        $this->createSchedule(['day_of_week' => 1]);
        $this->createSchedule(['day_of_week' => 1]);
        $other = $this->createSchedule(['day_of_week' => 3]);
        $holiday = ScheduleException::factory()->hospitalHoliday()->create(['start_date' => '2026-10-19', 'end_date' => '2026-10-19']);

        $this->generate();

        $onHoliday = PracticeSession::query()->onDate(CarbonImmutable::parse('2026-10-19'))->get();

        $this->assertCount(2, $onHoliday);
        $this->assertSame(['cancelled'], $onHoliday->map(fn (PracticeSession $session): string => $session->status->value)->unique()->values()->all());
        $this->assertSame([$holiday->id], $onHoliday->pluck('schedule_exception_id')->unique()->values()->all());
        $this->assertSame(['Libur rumah sakit'], $onHoliday->pluck('status_reason')->unique()->values()->all());
        $this->assertSame(SessionStatus::Open, PracticeSession::query()->where('doctor_schedule_id', $other->id)->firstOrFail()->status);
        $this->assertSame(1, PracticeSession::query()->where('status', SessionStatus::Cancelled->value)->distinct()->count('session_date'));
    }

    public function test_an_exception_for_another_doctor_does_not_cancel_the_session(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 1]);
        ScheduleException::factory()->leave()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);

        $this->generate();

        $this->assertSame(0, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->where('status', SessionStatus::Cancelled->value)->count());
    }

    public function test_an_other_exception_applies_to_one_doctor_or_to_everyone_depending_on_doctor_id(): void
    {
        $first = $this->createSchedule(['day_of_week' => 1]);
        $second = $this->createSchedule(['day_of_week' => 1]);
        ScheduleException::factory()->create([
            'doctor_id' => $first->doctor_id, 'type' => 'other', 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'reason' => 'Kegiatan khusus',
        ]);
        ScheduleException::factory()->global()->create(['start_date' => '2026-10-19', 'end_date' => '2026-10-19']);

        $this->generate();

        $status = fn (DoctorSchedule $schedule, string $date): SessionStatus => PracticeSession::query()
            ->where('doctor_schedule_id', $schedule->id)->where('session_date', $date)->firstOrFail()->status;

        $this->assertSame(SessionStatus::Cancelled, $status($first, '2026-10-12'));
        $this->assertSame(SessionStatus::Open, $status($second, '2026-10-12'));
        $this->assertSame(SessionStatus::Cancelled, $status($first, '2026-10-19'));
        $this->assertSame(SessionStatus::Cancelled, $status($second, '2026-10-19'));
    }

    public function test_f3_r10_a_doctor_specific_exception_wins_over_a_global_one_and_the_smallest_id_wins_among_equals(): void
    {
        $first = $this->createSchedule(['day_of_week' => 1]);
        $second = $this->createSchedule(['day_of_week' => 1]);

        $globalA = ScheduleException::factory()->hospitalHoliday()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        ScheduleException::factory()->nationalHoliday()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        $leave = ScheduleException::factory()->leave()->create(['doctor_id' => $first->doctor_id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12']);

        $this->generate();

        $exceptionOf = fn (DoctorSchedule $schedule): ?int => PracticeSession::query()
            ->where('doctor_schedule_id', $schedule->id)->where('session_date', '2026-10-12')->firstOrFail()->schedule_exception_id;

        $this->assertGreaterThan($globalA->id, $leave->id);
        $this->assertSame($leave->id, $exceptionOf($first), 'Cuti khusus dokter didahulukan walau id lebih besar.');
        $this->assertSame($globalA->id, $exceptionOf($second), 'Antar pengecualian setara: id terkecil.');
    }

    public function test_an_exception_outside_the_range_is_ignored(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 1]);
        ScheduleException::factory()->hospitalHoliday()->create(['start_date' => '2026-10-13', 'end_date' => '2026-10-18']);

        $this->generate();

        $this->assertSame(0, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->where('status', SessionStatus::Cancelled->value)->count());
    }

    // --- Potongan 500 baris ---

    public function test_rows_are_inserted_in_chunks_of_five_hundred(): void
    {
        $doctor = Doctor::factory()->create();
        DoctorSchedule::factory()->count(110)->create(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 1]);

        DB::enableQueryLog();
        $created = $this->generate();
        $inserts = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'insert ignore'))->count();
        DB::disableQueryLog();

        $this->assertSame(550, $created);
        $this->assertSame(2, $inserts);
        $this->assertSame(550, PracticeSession::query()->count());
        $this->assertSame(0, $this->generate());
    }

    // --- ensureDailyHorizon (F3-R8) ---

    public function test_marker_key_format(): void
    {
        $this->assertSame('sessions:generated:2026-10-05', PracticeSessionGenerator::markerKey(CarbonImmutable::parse('2026-10-05 15:30:00')));
        $this->assertSame('sessions:generate', PracticeSessionGenerator::LOCK_KEY);
    }

    public function test_the_daily_horizon_runs_once_per_day_and_sets_the_marker(): void
    {
        $this->createSchedule(['day_of_week' => 1]);

        $this->assertFalse(Cache::has('sessions:generated:2026-10-05'));
        $this->assertSame(5, $this->generator()->ensureDailyHorizon());
        $this->assertTrue(Cache::has('sessions:generated:2026-10-05'));

        $this->createSchedule(['day_of_week' => 2]);

        $this->assertSame(0, $this->generator()->ensureDailyHorizon(), 'Penanda hari ini mencegah pembangkitan kedua.');
        $this->assertSame(5, PracticeSession::query()->count());
    }

    public function test_saving_a_template_triggers_ensure_range_directly_regardless_of_the_marker(): void
    {
        $this->createSchedule(['day_of_week' => 1]);
        $this->generator()->ensureDailyHorizon();
        $late = $this->createSchedule(['day_of_week' => 2]);

        $this->assertSame(0, $this->generator()->ensureDailyHorizon());
        $this->assertSame(5, $this->generate($late), 'Pemicu simpan templat memanggil ensureRange langsung.');
    }

    public function test_the_marker_expires_at_the_end_of_the_day_and_the_next_day_generates_again(): void
    {
        $this->createSchedule(['day_of_week' => 1]);
        $this->assertSame(5, $this->generator()->ensureDailyHorizon());
        $this->createSchedule(['day_of_week' => 2]);

        $this->travelTo('2026-10-05 23:59:00');
        $this->assertSame(0, $this->generator()->ensureDailyHorizon());

        $this->travelTo('2026-10-06 00:00:01');
        $this->assertFalse(Cache::has('sessions:generated:2026-10-05'), 'Penanda kemarin sudah kedaluwarsa.');
        $this->assertFalse(Cache::has('sessions:generated:2026-10-06'));
        $this->assertSame(5, $this->generator()->ensureDailyHorizon(), 'Hari baru: selasa 6 Okt s.d. 3 Nov (5 sesi baru).');
        $this->assertTrue(Cache::has('sessions:generated:2026-10-06'));
    }

    public function test_the_daily_horizon_uses_the_booking_window_setting(): void
    {
        app(SettingsService::class)->set('booking.window_days', 7);
        app(SettingsService::class)->forget();
        $schedule = $this->createSchedule(['day_of_week' => 1]);

        $this->assertSame(2, $this->generator()->ensureDailyHorizon());
        $this->assertSame(['2026-10-05', '2026-10-12'], $this->datesOf($schedule));
    }

    public function test_the_daily_horizon_returns_zero_without_waiting_when_another_process_holds_the_lock(): void
    {
        $this->createSchedule(['day_of_week' => 1]);
        $other = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30);
        $this->assertTrue($other->get());

        $this->assertSame(0, $this->generator()->ensureDailyHorizon());
        $this->assertSame(0, PracticeSession::query()->count());
        $this->assertFalse(Cache::has('sessions:generated:2026-10-05'), 'Penanda tidak diset bila kunci tidak diperoleh.');

        $other->release();

        $this->assertSame(5, $this->generator()->ensureDailyHorizon());
    }

    public function test_the_lock_is_released_after_the_daily_horizon_runs(): void
    {
        $this->createSchedule(['day_of_week' => 1]);
        $this->generator()->ensureDailyHorizon();

        $lock = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30);

        $this->assertTrue($lock->get());
        $lock->release();
    }
}
