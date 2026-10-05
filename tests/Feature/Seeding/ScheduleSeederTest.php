<?php

namespace Tests\Feature\Seeding;

use App\Enums\Availability;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Models\User;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DoctorScheduleSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\ScheduleExceptionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder demo jadwal (13 §7, F3-R7, F3-R28): 52 templat, sesi untuk hari ini s.d. hari ini + 30 (tanpa sesi lampau),
 * dua pengecualian lewat ScheduleChangeService, satu sesi ditutup, satu sesi tambahan manual dr. Teguh, dan sesi
 * cadangan hari ini. Prasyarat dibuat tanpa gambar (dokter dari DoctorSeeder::ROWS lewat factory) agar tes cepat;
 * satu tes memakai DatabaseSeeder penuh.
 */
class ScheduleSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Oracle yang diketik ulang dari 13 §7.1 (bukan dari konstanta seeder): "{hari ISO} {mulai}-{selesai} {kuota}".
     *
     * @var array<string, list<string>>
     */
    private const ORACLE = [
        'raka-pradipta-wibowo' => ['1 09:00-12:00 15', '3 09:00-12:00 15', '5 13:00-15:00 10'],
        'sekar-ayuningtyas' => ['1 08:00-11:00 20', '3 08:00-11:00 20', '5 08:00-11:00 20', '6 08:00-10:00 15'],
        'bagas-setiawan-nugroho' => ['2 13:00-16:00 20', '4 13:00-16:00 20', '6 10:00-12:00 15'],
        'laras-wulandari' => ['1 09:00-12:00 20', '2 09:00-12:00 20', '4 09:00-12:00 20', '6 09:00-11:00 15'],
        'dimas-aryaputra' => ['2 16:00-19:00 15', '3 13:00-16:00 20', '5 13:00-16:00 20'],
        'nadira-kusumawardani' => ['1 13:00-16:00 15', '3 13:00-16:00 15', '5 09:00-12:00 15'],
        'hendra-mahardika' => ['2 09:00-12:00 12', '4 09:00-12:00 12'],
        'maya-kartika-sari' => ['1 16:00-19:00 12', '4 16:00-19:00 12', '6 09:00-12:00 10'],
        'fajar-ramadhan-lubis' => ['2 13:00-16:00 15', '5 13:00-16:00 15'],
        'intan-permatasari' => ['2 08:00-11:00 20', '4 08:00-11:00 20', '6 08:00-11:00 15'],
        'yoga-prasetya' => ['1 13:00-16:00 15', '3 09:00-12:00 15', '5 16:00-19:00 15'],
        'citra-anindya-putri' => ['1 16:00-19:00 20', '3 16:00-19:00 20', '6 09:00-12:00 20'],
        'arif-budiman-santoso' => ['2 16:00-19:00 12', '4 13:00-16:00 12', '6 09:00-11:00 10'],
        'rani-oktaviani' => ['1 08:00-11:00 30', '2 08:00-11:00 30', '3 08:00-11:00 30', '4 08:00-11:00 30'],
        'teguh-wicaksono' => ['1 13:00-16:00 25', '4 16:00-19:00 25', '5 13:00-16:00 25', '6 08:00-11:00 30'],
        'ayu-larasati-hakim' => ['1 09:00-12:00 15', '3 13:00-16:00 15', '5 09:00-12:00 15', '6 09:00-12:00 15'],
        'eko-susanto' => ['2 13:00-16:00 12'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 07:00:00'); // Senin
    }

    /**
     * Prasyarat seeder jadwal tanpa gambar: pengaturan, akun demo, dan 18 dokter dari DoctorSeeder::ROWS
     * (masing-masing dengan poliklinik sendiri), lewat factory.
     */
    private function seedPrerequisites(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(DemoUserSeeder::class);

        foreach (DoctorSeeder::ROWS as $index => $row) {
            $clinic = Clinic::factory()->create(['name' => 'Poliklinik '.$row['name']]);

            Doctor::factory()->create([
                'clinic_id' => $clinic->id,
                'name' => $row['name'],
                'slug' => $row['slug'],
                'title_prefix' => $row['prefix'],
                'title_suffix' => $row['suffix'],
                'status' => $row['status'],
                'archived_at' => $row['status'] === 'archived' ? now()->subDays(30) : null,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function seedSchedules(): void
    {
        $this->seed(DoctorScheduleSeeder::class);
        $this->seed(ScheduleExceptionSeeder::class);
    }

    private function doctorId(string $slug): int
    {
        return (int) Doctor::query()->where('slug', $slug)->value('id');
    }

    // --- templat (13 §7.1) ---------------------------------------------------------------------

    public function test_templates_match_13_section_7_1_exactly(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $this->assertSame(52, DoctorSchedule::query()->count());
        $this->assertSame(51, DoctorSchedule::query()->where('is_active', true)->count());
        $this->assertSame(1, DoctorSchedule::query()->where('is_active', false)->count());

        $actual = DoctorSchedule::query()->with('doctor')->get()
            ->groupBy(fn (DoctorSchedule $schedule): string => $schedule->doctor->slug)
            ->map(fn ($group) => $group->map(fn (DoctorSchedule $schedule): string => sprintf(
                '%d %s-%s %d',
                $schedule->day_of_week,
                substr((string) $schedule->start_time, 0, 5),
                substr((string) $schedule->end_time, 0, 5),
                $schedule->quota,
            ))->sort()->values()->all())
            ->all();

        $expected = array_map(function (array $rows): array {
            sort($rows);

            return $rows;
        }, self::ORACLE);
        ksort($actual);
        ksort($expected);

        $this->assertSame($expected, $actual);
    }

    public function test_every_template_follows_the_demo_rules(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $creatorId = (int) User::query()->where('email', 'konten@unpamhospital.test')->value('id');
        $schedules = DoctorSchedule::query()->with('doctor')->get();

        foreach ($schedules as $schedule) {
            $this->assertSame($creatorId, (int) $schedule->created_by);
            $this->assertSame($schedule->doctor->clinic_id, $schedule->clinic_id);
            $this->assertSame('2026-08-21', $schedule->valid_from->toDateString(), 'valid_from = today - 45 hari');
            $this->assertNull($schedule->valid_until);
            $this->assertNotSame(7, $schedule->day_of_week, 'Tidak ada jadwal hari Minggu.');
            $this->assertGreaterThanOrEqual(10, $schedule->quota);
            $this->assertLessThanOrEqual(30, $schedule->quota);
            $this->assertSame($schedule->doctor->slug !== 'eko-susanto', $schedule->is_active);
        }

        // Tidak ada irisan jam pada hari yang sama untuk satu dokter.
        foreach ($schedules->where('is_active', true) as $schedule) {
            $this->assertFalse(
                DoctorSchedule::query()->overlapping(
                    $schedule->doctor_id,
                    $schedule->day_of_week,
                    substr((string) $schedule->start_time, 0, 5),
                    substr((string) $schedule->end_time, 0, 5),
                    $schedule->valid_from,
                    $schedule->valid_until,
                    $schedule->id,
                )->exists(),
                "Templat {$schedule->doctor->slug} hari {$schedule->day_of_week} beririsan.",
            );
        }
    }

    // --- sesi (F3-R7) --------------------------------------------------------------------------

    public function test_sessions_cover_today_through_the_window_only_for_eligible_doctors(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $this->assertSame(230, PracticeSession::query()->whereNotNull('doctor_schedule_id')->count(), '51 templat aktif x tanggalnya pada 5 Okt s.d. 4 Nov 2026.');
        $this->assertSame(1, PracticeSession::query()->whereNull('doctor_schedule_id')->count(), 'Hanya sesi tambahan dr. Teguh pada hari Senin ini.');
        $this->assertSame(0, PracticeSession::query()->where('session_date', '<', '2026-10-05')->count(), 'F3-R7: tidak ada sesi lampau.');
        $this->assertSame('2026-10-05', PracticeSession::query()->min('session_date'));
        $this->assertSame('2026-11-04', PracticeSession::query()->max('session_date'));
        $this->assertSame(0, PracticeSession::query()->whereIn('doctor_id', [$this->doctorId('eko-susanto'), $this->doctorId('lilis-marlina')])->count());
        $this->assertSame(0, PracticeSession::query()->where('status', SessionStatus::NeedsReview->value)->count());
    }

    public function test_sessions_copy_time_and_quota_from_their_template(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $mismatches = DB::table('practice_sessions as s')
            ->join('doctor_schedules as t', 't.id', '=', 's.doctor_schedule_id')
            ->where(function ($query): void {
                $query->whereColumn('s.start_time', '<>', 't.start_time')
                    ->orWhereColumn('s.end_time', '<>', 't.end_time')
                    ->orWhereColumn('s.quota', '<>', 't.quota')
                    ->orWhereColumn('s.doctor_id', '<>', 't.doctor_id')
                    ->orWhereColumn('s.clinic_id', '<>', 't.clinic_id');
            })
            ->count();

        $this->assertSame(0, $mismatches);
        $this->assertSame(0, PracticeSession::query()->where('booked_count', '>', 0)->count(), 'Appointment demo dibuat seeder fase 6/7.');
    }

    // --- pengecualian (13 §7.2) ----------------------------------------------------------------

    public function test_the_two_exceptions_are_created_and_cancel_their_sessions(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $creatorId = (int) User::query()->where('email', 'konten@unpamhospital.test')->value('id');
        $leave = ScheduleException::query()->where('type', ExceptionType::DoctorLeave->value)->sole();
        $holiday = ScheduleException::query()->where('type', ExceptionType::HospitalHoliday->value)->sole();

        $this->assertSame(2, ScheduleException::query()->count());
        $this->assertSame($this->doctorId('dimas-aryaputra'), $leave->doctor_id);
        $this->assertSame('2026-10-13', $leave->start_date->toDateString());
        $this->assertSame('2026-10-19', $leave->end_date->toDateString());
        $this->assertSame(7, (int) $leave->start_date->diffInDays($leave->end_date) + 1);
        $this->assertSame('Cuti tahunan (contoh)', $leave->reason);
        $this->assertSame($creatorId, (int) $leave->created_by);

        $this->assertNull($holiday->doctor_id);
        $this->assertSame('2026-10-21', $holiday->start_date->toDateString());
        $this->assertSame('2026-10-21', $holiday->end_date->toDateString());
        $this->assertSame('Contoh libur rumah sakit (bukan tanggal libur nasional)', $holiday->reason);
        $this->assertSame($creatorId, (int) $holiday->created_by);

        $leaveSessions = PracticeSession::query()->where('schedule_exception_id', $leave->id)->get();
        $holidaySessions = PracticeSession::query()->where('schedule_exception_id', $holiday->id)->get();

        $this->assertCount(3, $leaveSessions, 'Dimas: Selasa 13, Rabu 14, dan Jumat 16 Oktober.');
        $this->assertSame([$this->doctorId('dimas-aryaputra')], $leaveSessions->pluck('doctor_id')->unique()->values()->all());
        $this->assertCount(8, $holidaySessions, 'Delapan dokter praktik Rabu 21 Oktober.');
        $this->assertSame(8, PracticeSession::query()->where('session_date', '2026-10-21')->whereNotNull('doctor_schedule_id')->count());

        foreach ($leaveSessions->concat($holidaySessions) as $session) {
            $this->assertSame(SessionStatus::Cancelled, $session->status);
            $this->assertNotNull($session->status_reason);
        }

        $this->assertSame(11, PracticeSession::query()->where('status', SessionStatus::Cancelled->value)->count());
        $this->assertSame(2, AuditLog::query()->where('action', 'exception.created')->count());
    }

    public function test_cancelled_sessions_read_as_leave_and_holiday_through_the_shared_query(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $query = app(DoctorAvailabilityQuery::class);
        $leave = PracticeSession::query()->with(['doctor', 'clinic', 'exception'])->where('doctor_id', $this->doctorId('dimas-aryaputra'))->where('session_date', '2026-10-14')->sole();
        $holiday = PracticeSession::query()->with(['doctor', 'clinic', 'exception'])->where('doctor_id', $this->doctorId('rani-oktaviani'))->where('session_date', '2026-10-21')->sole();

        $this->assertSame(Availability::Leave, $query->forSession($leave)->availability);
        $this->assertSame(Availability::Holiday, $query->forSession($holiday)->availability);
    }

    public function test_the_holiday_moves_to_monday_when_sixteen_days_ahead_is_a_sunday(): void
    {
        $this->travelTo('2026-10-02 07:00:00'); // Jumat: today + 16 = Minggu 18 Oktober
        $this->seedPrerequisites();
        $this->seedSchedules();

        $holiday = ScheduleException::query()->where('type', ExceptionType::HospitalHoliday->value)->sole();
        $leave = ScheduleException::query()->where('type', ExceptionType::DoctorLeave->value)->sole();

        $this->assertSame('2026-10-19', $holiday->start_date->toDateString());
        $this->assertSame('2026-10-10', $leave->start_date->toDateString());
        $this->assertSame('2026-10-16', $leave->end_date->toDateString());
        $this->assertGreaterThan($leave->end_date->toDateString(), $holiday->start_date->toDateString(), 'Rentang tidak beririsan.');
    }

    // --- sesi tutup contoh dan sesi tambahan manual (13 §7.3, F3-R28) ---------------------------

    public function test_the_closed_example_session_is_the_first_open_session_of_dr_citra_from_today_plus_three(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $closed = PracticeSession::query()->where('status', SessionStatus::Closed->value)->sole();

        $this->assertSame($this->doctorId('citra-anindya-putri'), $closed->doctor_id);
        $this->assertSame('2026-10-10', $closed->session_date->toDateString(), 'Sabtu 10 Oktober adalah sesi dr. Citra pertama pada atau setelah today + 3 (8 Oktober).');
        $this->assertSame('Ditutup sementara (contoh)', $closed->status_reason);
        $this->assertTrue($closed->is_manually_modified);
        $this->assertNotNull($closed->doctor_schedule_id);

        $closed->load(['doctor', 'clinic', 'exception']);
        $this->assertSame(Availability::Closed, app(DoctorAvailabilityQuery::class)->forSession($closed)->availability);
    }

    public function test_the_extra_manual_session_of_dr_teguh_avoids_his_template_days(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $extra = PracticeSession::query()->whereNull('doctor_schedule_id')->sole();

        $this->assertSame($this->doctorId('teguh-wicaksono'), $extra->doctor_id);
        $this->assertSame('2026-10-07', $extra->session_date->toDateString(), 'today + 2 = Rabu, bukan hari templat dr. Teguh (Sen, Kam, Jum, Sab).');
        $this->assertSame('08:00:00', $extra->start_time);
        $this->assertSame('10:00:00', $extra->end_time);
        $this->assertSame(2, $extra->quota);
        $this->assertSame(0, $extra->booked_count);
        $this->assertSame(SessionStatus::Open, $extra->status);
        $this->assertSame('Sesi tambahan (contoh)', $extra->status_reason);
        $this->assertTrue($extra->is_manually_modified);
        $this->assertSame(1, PracticeSession::query()->where('doctor_id', $extra->doctor_id)->where('session_date', '2026-10-07')->count(), 'Tidak ada sesi hasil templat yang bentrok.');
    }

    public function test_the_extra_session_skips_forward_past_template_days(): void
    {
        $this->travelTo('2026-10-06 07:00:00'); // Selasa: today + 2 = Kamis (templat), Jumat (templat), Sabtu (templat), Minggu
        $this->seedPrerequisites();
        $this->seedSchedules();

        $extra = PracticeSession::query()->whereNull('doctor_schedule_id')->where('doctor_id', $this->doctorId('teguh-wicaksono'))->sole();

        $this->assertSame('2026-10-11', $extra->session_date->toDateString());
        $this->assertSame(7, $extra->session_date->isoWeekday());
    }

    // --- cadangan hari ini ---------------------------------------------------------------------

    public function test_a_sunday_gets_three_manual_sessions_for_today(): void
    {
        $this->travelTo('2026-10-04 07:00:00'); // Minggu: tidak ada templat hari Minggu
        $this->seedPrerequisites();
        $this->seedSchedules();

        $today = PracticeSession::query()->with('doctor')->where('session_date', '2026-10-04')->get()->sortBy('start_time')->values();

        $this->assertSame(0, PracticeSession::query()->where('session_date', '2026-10-04')->whereNotNull('doctor_schedule_id')->count());
        $this->assertSame(
            ['rani-oktaviani 08:00:00-11:00:00', 'sekar-ayuningtyas 13:00:00-16:00:00', 'laras-wulandari 16:00:00-19:00:00'],
            $today->map(fn (PracticeSession $session): string => $session->doctor->slug.' '.$session->start_time.'-'.$session->end_time)->all(),
        );
        $this->assertSame([20, 20, 20], $today->pluck('quota')->all());
        $this->assertSame(4, PracticeSession::query()->whereNull('doctor_schedule_id')->count(), 'Tiga cadangan hari ini ditambah sesi tambahan dr. Teguh (Selasa 6 Oktober).');
    }

    public function test_backup_sessions_that_already_ended_are_skipped(): void
    {
        $this->travelTo('2026-10-04 17:30:00'); // Minggu sore: 08.00-11.00 dan 13.00-16.00 sudah berakhir
        $this->seedPrerequisites();
        $this->seedSchedules();

        $today = PracticeSession::query()->with('doctor')->where('session_date', '2026-10-04')->get();

        $this->assertCount(1, $today);
        $this->assertSame('laras-wulandari', $today->first()->doctor->slug);
        $this->assertSame('16:00:00', $today->first()->start_time);
    }

    public function test_a_weekday_with_enough_sessions_gets_no_backup(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $openToday = PracticeSession::query()->where('session_date', '2026-10-05')->where('status', SessionStatus::Open->value)->count();

        $this->assertGreaterThanOrEqual(3, $openToday);
        $this->assertSame(0, PracticeSession::query()->where('session_date', '2026-10-05')->whereNull('doctor_schedule_id')->count());
    }

    // --- idempoten, guard, prasyarat -----------------------------------------------------------

    public function test_running_the_seeders_twice_changes_nothing(): void
    {
        $this->seedPrerequisites();
        $this->seedSchedules();

        $before = [
            DoctorSchedule::query()->count(),
            PracticeSession::query()->count(),
            ScheduleException::query()->count(),
            PracticeSession::query()->where('status', SessionStatus::Closed->value)->count(),
            PracticeSession::query()->whereNull('doctor_schedule_id')->count(),
            PracticeSession::query()->where('status', SessionStatus::Cancelled->value)->count(),
        ];

        $this->seedSchedules();

        $this->assertSame([52, 231, 2, 1, 1, 11], $before);
        $this->assertSame($before, [
            DoctorSchedule::query()->count(),
            PracticeSession::query()->count(),
            ScheduleException::query()->count(),
            PracticeSession::query()->where('status', SessionStatus::Closed->value)->count(),
            PracticeSession::query()->whereNull('doctor_schedule_id')->count(),
            PracticeSession::query()->where('status', SessionStatus::Cancelled->value)->count(),
        ]);
    }

    public function test_both_seeders_refuse_to_run_outside_local_and_testing(): void
    {
        foreach ([DoctorScheduleSeeder::class, ScheduleExceptionSeeder::class] as $seeder) {
            foreach (['production', 'staging'] as $environment) {
                $thrown = null;

                $this->inEnvironment($environment, function () use (&$thrown, $seeder): void {
                    try {
                        $this->app->make($seeder)->run();
                    } catch (RuntimeException $e) {
                        $thrown = $e;
                    }
                });

                $this->assertInstanceOf(RuntimeException::class, $thrown, "{$seeder} di {$environment}");
                $this->assertStringContainsString('DILARANG di APP_ENV='.$environment, $thrown->getMessage());
            }
        }

        $this->assertSame(0, DoctorSchedule::query()->count() + ScheduleException::query()->count() + PracticeSession::query()->count());
    }

    public function test_the_schedule_seeder_explains_a_missing_doctor(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $thrown = null;

        try {
            $this->app->make(DoctorScheduleSeeder::class)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('Jalankan DoctorSeeder lebih dulu.', $thrown->getMessage());
    }

    public function test_the_demo_seeder_runs_the_schedule_seeders_and_the_public_pages_show_the_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(52, DoctorSchedule::query()->count());
        $this->assertSame(2, ScheduleException::query()->count());
        $this->assertSame(231, PracticeSession::query()->count());
        $this->assertSame(16, Doctor::query()->publiclyVisible()->count());

        $this->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Minggu 5 Oktober – 11 Oktober 2026')
            ->assertSee('dr. Sekar Ayuningtyas, Sp.PD')
            ->assertSee('dr. Rani Oktaviani');

        // Cuti dr. Dimas: 3 sesi pada grid, 3 pada daftar bertumpuk, dan 1 pada legenda.
        $leaveWeek = $this->get(route('schedules.index', ['minggu' => '2026-10-12']))->assertOk()->getContent();
        $this->assertSame(7, substr_count($leaveWeek, 'data-availability="leave"'));

        // Libur rumah sakit 21 Oktober: 8 sesi pada grid, 8 pada daftar bertumpuk, dan 1 pada legenda.
        $holidayWeek = $this->get(route('schedules.index', ['minggu' => '2026-10-19']))->assertOk()->getContent();
        $this->assertSame(17, substr_count($holidayWeek, 'data-availability="holiday"'));

        $this->get(route('doctors.show', 'teguh-wicaksono'))->assertOk()->assertSee('datetime="2026-10-07"', false)->assertSee('08.00–10.00');
        $this->get(route('doctors.show', 'eko-susanto'))->assertNotFound();
        $this->get(route('doctors.show', 'lilis-marlina'))->assertNotFound();
        $this->get(route('doctors.index', ['tanggal' => '2026-10-07']))->assertOk()->assertSee('Jadwal terdekat');
    }

    private function inEnvironment(string $environment, Closure $callback): void
    {
        $original = $this->app['env'];
        $this->app['env'] = $environment;

        try {
            $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }
}
