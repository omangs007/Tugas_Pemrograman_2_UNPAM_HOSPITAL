<?php

namespace Tests\Feature\Console;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F7A-R7, BKG-16: `php artisan appointments:mark-no-show {--date=}` dan penjadwalnya. Waktu tes: Selasa
 * 2026-10-06 12.30 WIB (sesi 09.00-12.00 hari ini sudah berakhir). Tes SYSTEM_BUSY dan retry massal milik T11.
 */
class MarkNoShowAppointmentsTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    private const NOTHING = 'Tidak ada pendaftaran yang perlu ditandai tidak hadir.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-06 12:30:00'));
        $this->seed(SettingsSeeder::class);
    }

    // ------------------------------------------------------------------ pembantu

    /** Sesi tambahan manual dengan nama poliklinik dan dokter yang diketahui (untuk keluaran per sesi). */
    private function sessionAt(string $date, string $start, string $end, string $clinic, string $doctor, string $state = 'open'): PracticeSession
    {
        $factory = PracticeSession::factory()->manual();
        $factory = $state === 'needs_review'
            ? $factory->needsReview(['source' => PracticeSession::SOURCE_EXCEPTION, 'exception_id' => 1])
            : $factory;

        $session = $factory->create([
            'session_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'quota' => 20,
        ]);

        Clinic::query()->whereKey($session->clinic_id)->firstOrFail()->forceFill(['name' => $clinic])->save();
        Doctor::query()->whereKey($session->doctor_id)->firstOrFail()->forceFill(['name' => $doctor])->save();

        return $session;
    }

    private function appointment(PracticeSession $session, AppointmentStatus $status): Appointment
    {
        return $this->bookAppointment($session, $status, ['patient_profile_id' => PatientProfile::factory()->withAccount()->create()->id]);
    }

    private function statusOf(Appointment $appointment): AppointmentStatus
    {
        return $appointment->fresh()->status;
    }

    // ------------------------------------------------------------------ tanpa --date

    public function test_without_the_date_option_it_marks_every_ended_session_and_prints_each_session_and_a_summary(): void
    {
        $s1 = $this->sessionAt('2026-10-05', '13:00:00', '16:00:00', 'Poliklinik Anak', 'dr. Budi Santoso');
        $a1 = $this->appointment($s1, AppointmentStatus::Pending);
        $a2 = $this->appointment($s1, AppointmentStatus::Confirmed);
        $s2 = $this->sessionAt('2026-10-06', '09:00:00', '12:00:00', 'Poliklinik Gigi', 'drg. Sari Dewi');
        $a3 = $this->appointment($s2, AppointmentStatus::Confirmed);
        $a4 = $this->appointment($s2, AppointmentStatus::CheckedIn);
        $s3 = $this->sessionAt('2026-10-06', '13:00:00', '15:00:00', 'Poliklinik Mata', 'dr. Joko Widodo');
        $a5 = $this->appointment($s3, AppointmentStatus::Confirmed);

        $this->artisan('appointments:mark-no-show')
            ->expectsOutputToContain('- 05/10/2026 13.00–16.00 Poliklinik Anak, dr. Budi Santoso: 2 pendaftaran.')
            ->expectsOutputToContain('- 06/10/2026 09.00–12.00 Poliklinik Gigi, drg. Sari Dewi: 1 pendaftaran.')
            ->expectsOutputToContain('3 pendaftaran pada 2 sesi ditandai tidak hadir.')
            ->doesntExpectOutputToContain('Poliklinik Mata')
            ->assertExitCode(0);

        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($a1));
        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($a2));
        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($a3));
        $this->assertSame(AppointmentStatus::CheckedIn, $this->statusOf($a4));
        $this->assertSame(AppointmentStatus::Confirmed, $this->statusOf($a5));

        $histories = AppointmentStatusHistory::query()->get();
        $this->assertCount(3, $histories);

        foreach ($histories as $history) {
            $this->assertSame(AppointmentStatus::NoShow, $history->to_status);
            $this->assertSame(ActorType::System, $history->actor_type);
            $this->assertNull($history->actor_user_id);
        }

        $audits = AuditLog::query()->where('action', 'appointment.status_changed')->get();
        $this->assertCount(3, $audits);

        foreach ($audits as $audit) {
            $this->assertSame(ActorType::System, $audit->actor_type);
            $this->assertNull($audit->user_id);
        }
    }

    public function test_a_second_run_is_idempotent_and_prints_the_nothing_to_mark_message(): void
    {
        $session = $this->sessionAt('2026-10-06', '09:00:00', '12:00:00', 'Poliklinik Anak', 'dr. Budi Santoso');
        $this->appointment($session, AppointmentStatus::Confirmed);

        $this->artisan('appointments:mark-no-show')
            ->expectsOutputToContain('1 pendaftaran pada 1 sesi ditandai tidak hadir.')
            ->assertExitCode(0);

        $this->artisan('appointments:mark-no-show')
            ->expectsOutputToContain(self::NOTHING)
            ->assertExitCode(0);

        $this->assertSame(1, AppointmentStatusHistory::query()->count());
    }

    // ------------------------------------------------------------------ dengan --date

    public function test_the_date_option_limits_processing_to_that_date(): void
    {
        $yesterday = $this->sessionAt('2026-10-05', '13:00:00', '16:00:00', 'Poliklinik Anak', 'dr. Budi Santoso');
        $a1 = $this->appointment($yesterday, AppointmentStatus::Confirmed);
        $today = $this->sessionAt('2026-10-06', '09:00:00', '12:00:00', 'Poliklinik Gigi', 'drg. Sari Dewi');
        $a2 = $this->appointment($today, AppointmentStatus::Pending);

        $this->artisan('appointments:mark-no-show', ['--date' => '2026-10-05'])
            ->expectsOutputToContain('- 05/10/2026 13.00–16.00 Poliklinik Anak, dr. Budi Santoso: 1 pendaftaran.')
            ->expectsOutputToContain('1 pendaftaran pada 1 sesi ditandai tidak hadir.')
            ->doesntExpectOutputToContain('Poliklinik Gigi')
            ->assertExitCode(0);

        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($a1));
        $this->assertSame(AppointmentStatus::Pending, $this->statusOf($a2));
    }

    public function test_today_is_an_accepted_date_and_only_ended_sessions_of_today_are_processed(): void
    {
        $ended = $this->sessionAt('2026-10-06', '09:00:00', '12:00:00', 'Poliklinik Gigi', 'drg. Sari Dewi');
        $a1 = $this->appointment($ended, AppointmentStatus::Confirmed);
        $running = $this->sessionAt('2026-10-06', '13:00:00', '15:00:00', 'Poliklinik Mata', 'dr. Joko Widodo');
        $a2 = $this->appointment($running, AppointmentStatus::Confirmed);

        $this->artisan('appointments:mark-no-show', ['--date' => '2026-10-06'])
            ->expectsOutputToContain('- 06/10/2026 09.00–12.00 Poliklinik Gigi, drg. Sari Dewi: 1 pendaftaran.')
            ->expectsOutputToContain('1 pendaftaran pada 1 sesi ditandai tidak hadir.')
            ->doesntExpectOutputToContain('Poliklinik Mata')
            ->assertExitCode(0);

        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($a1));
        $this->assertSame(AppointmentStatus::Confirmed, $this->statusOf($a2));
    }

    /** @return array<string, array{string}> */
    public static function invalidDates(): array
    {
        return [
            'huruf' => ['abc'],
            'bulan dan hari di luar rentang' => ['2026-13-40'],
            'format Indonesia' => ['05/10/2026'],
            'tanpa nol di depan' => ['2026-1-5'],
            'kosong' => [''],
            'tanggal tidak ada di kalender' => ['2026-02-30'],
            'dengan jam' => ['2026-10-05 10:00'],
            'tahun dua digit' => ['26-10-05'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_a_malformed_date_option_is_rejected_with_exit_code_one_and_changes_nothing(string $value): void
    {
        $session = $this->sessionAt('2026-10-05', '13:00:00', '16:00:00', 'Poliklinik Anak', 'dr. Budi Santoso');
        $appointment = $this->appointment($session, AppointmentStatus::Pending);

        $this->artisan('appointments:mark-no-show', ['--date' => $value])
            ->expectsOutputToContain('Opsi --date harus berupa tanggal dengan format YYYY-MM-DD.')
            ->assertExitCode(1);

        $this->assertSame(AppointmentStatus::Pending, $this->statusOf($appointment));
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }

    /** @return array<string, array{string}> */
    public static function futureDates(): array
    {
        return [
            'besok' => ['2026-10-07'],
            'jauh di depan' => ['2099-01-01'],
        ];
    }

    #[DataProvider('futureDates')]
    public function test_a_date_after_today_is_rejected_with_exit_code_one_and_changes_nothing(string $value): void
    {
        $session = $this->sessionAt('2026-10-05', '13:00:00', '16:00:00', 'Poliklinik Anak', 'dr. Budi Santoso');
        $appointment = $this->appointment($session, AppointmentStatus::Confirmed);

        $this->artisan('appointments:mark-no-show', ['--date' => $value])
            ->expectsOutputToContain('Opsi --date tidak boleh tanggal setelah hari ini.')
            ->assertExitCode(1);

        $this->assertSame(AppointmentStatus::Confirmed, $this->statusOf($appointment));
    }

    // ------------------------------------------------------------------ tanpa sesi

    public function test_with_nothing_to_mark_it_says_so_and_exits_with_zero(): void
    {
        $this->artisan('appointments:mark-no-show')
            ->expectsOutputToContain(self::NOTHING)
            ->assertExitCode(0);

        $this->artisan('appointments:mark-no-show', ['--date' => '2026-10-01'])
            ->expectsOutputToContain(self::NOTHING)
            ->assertExitCode(0);
    }

    public function test_sessions_without_pending_or_confirmed_appointments_and_needs_review_sessions_are_ignored(): void
    {
        $onlyCheckedIn = $this->sessionAt('2026-10-06', '09:00:00', '12:00:00', 'Poliklinik Gigi', 'drg. Sari Dewi');
        $checkedIn = $this->appointment($onlyCheckedIn, AppointmentStatus::CheckedIn);
        $review = $this->sessionAt('2026-10-05', '09:00:00', '12:00:00', 'Poliklinik Anak', 'dr. Budi Santoso', 'needs_review');
        $reviewed = $this->appointment($review, AppointmentStatus::Confirmed);

        $this->artisan('appointments:mark-no-show')
            ->expectsOutputToContain(self::NOTHING)
            ->doesntExpectOutputToContain('Poliklinik')
            ->assertExitCode(0);

        $this->assertSame(AppointmentStatus::CheckedIn, $this->statusOf($checkedIn));
        $this->assertSame(AppointmentStatus::Confirmed, $this->statusOf($reviewed));
        $this->assertSame(0, Appointment::query()->where('status', 'no_show')->count());
    }

    // ------------------------------------------------------------------ jadwal

    public function test_the_scheduler_runs_the_command_daily_at_half_past_eleven_pm_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'appointments:mark-no-show'));

        $this->assertCount(1, $events);

        $event = $events->first();
        $this->assertSame('30 23 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_the_session_generation_schedule_is_untouched(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'sessions:generate'));

        $this->assertCount(1, $events);
        $this->assertSame('5 0 * * *', $events->first()->expression);
    }
}
