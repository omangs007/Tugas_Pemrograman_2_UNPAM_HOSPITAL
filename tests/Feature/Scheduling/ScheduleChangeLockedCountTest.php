<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Hitungan appointment per sesi pada jalur propagasi harus pembacaan terkunci (FOR UPDATE), agar tidak memakai
 * snapshot REPEATABLE READ transaksi pemanggil.
 */
class ScheduleChangeLockedCountTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    private ScheduleChangeService $service;

    private Actor $actor;

    private Doctor $doctor;

    private PracticeSession $session;

    private DoctorSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->service = $this->app->make(ScheduleChangeService::class);
        $this->actor = Actor::staff(User::factory()->staff()->create());
        $clinic = Clinic::factory()->create();
        $this->doctor = Doctor::factory()->withSpecialties()->create(['clinic_id' => $clinic->id]);

        $this->schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-12',
            'valid_until' => '2026-10-18',
            'is_active' => true,
        ])->fresh();

        $this->session = $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic->id,
            'doctor_schedule_id' => $this->schedule->id,
            'session_date' => '2026-10-12',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'status' => SessionStatus::Open,
            'is_manually_modified' => false,
        ]);
        $this->bookAppointment($this->session->fresh(), AppointmentStatus::Confirmed);
    }

    /**
     * @param  array<int, array<string, mixed>>  $log
     */
    private function assertAppointmentCountsAreLocked(array $log): void
    {
        $counts = array_values(array_filter(
            array_column($log, 'query'),
            fn (string $sql): bool => str_contains($sql, 'from `appointments`') && str_contains($sql, 'count(*)'),
        ));

        $this->assertNotEmpty($counts, 'Tidak ada query hitung appointment yang tercatat.');

        foreach ($counts as $sql) {
            $this->assertMatchesRegularExpression('/for update\s*$/i', $sql, "Hitungan appointment tidak terkunci: {$sql}");
        }
    }

    public function test_template_change_counts_appointments_with_a_locking_read(): void
    {
        $before = $this->schedule->getOriginal();
        $this->schedule->update(['start_time' => '09:00:00', 'end_time' => '13:00:00']);

        DB::enableQueryLog();
        $this->service->applyTemplateChange($this->schedule, $before, $this->actor);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertAppointmentCountsAreLocked($log);
        $this->assertSame(SessionStatus::NeedsReview, $this->session->fresh()->status);
    }

    public function test_exception_counts_appointments_with_a_locking_read(): void
    {
        $leave = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-12',
        ]);

        DB::enableQueryLog();
        $this->service->applyException($leave, null, $this->actor);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertAppointmentCountsAreLocked($log);
        $this->assertSame(SessionStatus::NeedsReview, $this->session->fresh()->status);
    }

    public function test_doctor_inactive_counts_appointments_with_a_locking_read(): void
    {
        DB::enableQueryLog();
        $this->service->applyDoctorInactive($this->doctor, $this->actor);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertAppointmentCountsAreLocked($log);
        $this->assertSame(SessionStatus::NeedsReview, $this->session->fresh()->status);
    }
}
