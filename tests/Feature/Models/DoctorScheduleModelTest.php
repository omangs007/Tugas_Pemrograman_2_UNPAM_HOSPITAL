<?php

namespace Tests\Feature\Models;

use App\Enums\AppointmentStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 11 §5.3 dan F3-R15/V-23: templat jadwal mingguan.
 */
class DoctorScheduleModelTest extends TestCase
{
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 08:00:00');
    }

    public function test_casts_and_defaults(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 3, 'quota' => 15, 'valid_from' => '2026-10-05', 'valid_until' => '2026-12-31'])->fresh();

        $this->assertSame(3, $schedule->day_of_week);
        $this->assertSame(15, $schedule->quota);
        $this->assertTrue($schedule->is_active);
        $this->assertSame('2026-10-05', $schedule->valid_from->toDateString());
        $this->assertSame('2026-12-31', $schedule->valid_until->toDateString());
        $this->assertSame('08:00:00', $schedule->start_time);
        $this->assertSame('12:00:00', $schedule->end_time);
        $this->assertNull($schedule->notes);
    }

    public function test_the_audit_columns_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new DoctorSchedule(['created_by' => 1]);
    }

    public function test_relations(): void
    {
        $staff = $this->createStaff('super-admin');
        $this->actingAs($staff);
        $schedule = $this->createSchedule();
        $session = PracticeSession::factory()->create([
            'doctor_id' => $schedule->doctor_id, 'clinic_id' => $schedule->clinic_id, 'doctor_schedule_id' => $schedule->id,
        ]);

        $schedule = DoctorSchedule::query()->with(['doctor', 'clinic', 'sessions', 'creator', 'updater'])->findOrFail($schedule->id);

        $this->assertInstanceOf(Doctor::class, $schedule->doctor);
        $this->assertInstanceOf(Clinic::class, $schedule->clinic);
        $this->assertSame($schedule->doctor->clinic_id, $schedule->clinic_id);
        $this->assertSame([$session->id], $schedule->sessions->pluck('id')->all());
        $this->assertSame($staff->id, $schedule->creator->id);
        $this->assertSame($staff->id, $schedule->updater->id);
        $this->assertSame([$schedule->id], $schedule->doctor->schedules()->pluck('id')->all());
        $this->assertSame([$schedule->id], $schedule->clinic->schedules()->pluck('id')->all());
    }

    public function test_created_by_and_updated_by_come_from_the_authenticated_user(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('content-admin');

        $this->actingAs($first);
        $schedule = $this->createSchedule();
        $this->assertSame($first->id, $schedule->fresh()->created_by);
        $this->assertSame($first->id, $schedule->fresh()->updated_by);

        $this->actingAs($second);
        $schedule->update(['quota' => 25]);

        $this->assertSame($first->id, $schedule->fresh()->created_by);
        $this->assertSame($second->id, $schedule->fresh()->updated_by);
    }

    public function test_audit_columns_stay_null_without_an_authenticated_user(): void
    {
        $schedule = $this->createSchedule();
        $schedule->update(['quota' => 25]);

        $fresh = $schedule->fresh();
        $this->assertNull($fresh->created_by);
        $this->assertNull($fresh->updated_by);
    }

    public function test_active_scope(): void
    {
        $active = $this->createSchedule();
        $inactive = DoctorSchedule::factory()->inactive()->create();

        $this->assertSame([$active->id], DoctorSchedule::query()->active()->pluck('id')->all());
        $this->assertFalse($inactive->is_active);
    }

    public function test_on_day_scope(): void
    {
        $monday = $this->createSchedule(['day_of_week' => 1]);
        $sunday = $this->createSchedule(['day_of_week' => 7]);

        $this->assertSame([$monday->id], DoctorSchedule::query()->onDay(1)->pluck('id')->all());
        $this->assertSame([$sunday->id], DoctorSchedule::query()->onDay(7)->pluck('id')->all());
        $this->assertSame([], DoctorSchedule::query()->onDay(3)->pluck('id')->all());
    }

    public function test_effective_on_scope_includes_both_boundaries_and_open_ended_templates(): void
    {
        $bounded = $this->createSchedule(['valid_from' => '2026-10-10', 'valid_until' => '2026-10-20']);
        $openEnded = $this->createSchedule(['valid_from' => '2026-10-15', 'valid_until' => null]);

        $ids = fn (string $date): array => DoctorSchedule::query()->effectiveOn($date)->orderBy('id')->pluck('id')->all();

        $this->assertSame([], $ids('2026-10-09'));
        $this->assertSame([$bounded->id], $ids('2026-10-10'));
        $this->assertSame([$bounded->id, $openEnded->id], $ids('2026-10-15'));
        $this->assertSame([$bounded->id, $openEnded->id], $ids('2026-10-20'));
        $this->assertSame([$openEnded->id], $ids('2026-10-21'));
        $this->assertSame([$openEnded->id], DoctorSchedule::query()->effectiveOn(CarbonImmutable::parse('2027-01-01'))->pluck('id')->all());
    }

    public function test_day_name_and_time_range_accessors(): void
    {
        $schedule = $this->createSchedule(['day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '12:30:00']);
        $sunday = $this->createSchedule(['day_of_week' => 7, 'start_time' => '13:05:00', 'end_time' => '15:00:00']);

        $this->assertSame('Senin', $schedule->day_name);
        $this->assertSame('08.00–12.30', $schedule->time_range);
        $this->assertSame('Minggu', $sunday->day_name);
        $this->assertSame('13.05–15.00', $sunday->time_range);
        $this->assertSame(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'], array_values(DoctorSchedule::DAY_NAMES));
    }

    // --- scopeOverlapping (V-23, Review Focus 2) ---

    private function existing(array $overrides = []): DoctorSchedule
    {
        return $this->createSchedule(array_merge([
            'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '12:00:00',
            'valid_from' => '2026-10-05', 'valid_until' => null,
        ], $overrides));
    }

    private function overlaps(DoctorSchedule $existing, string $start, string $end, string $from = '2026-10-05', ?string $until = null, ?int $ignoreId = null, ?int $day = null): bool
    {
        return DoctorSchedule::query()
            ->overlapping(
                $existing->doctor_id,
                $day ?? 1,
                $start,
                $end,
                CarbonImmutable::parse($from),
                $until === null ? null : CarbonImmutable::parse($until),
                $ignoreId,
            )
            ->exists();
    }

    public function test_overlapping_detects_intersecting_times_on_the_same_day(): void
    {
        $existing = $this->existing();

        $this->assertTrue($this->overlaps($existing, '11:00', '13:00'));
        $this->assertTrue($this->overlaps($existing, '07:00', '09:00'));
        $this->assertTrue($this->overlaps($existing, '09:00', '10:00'));
        $this->assertTrue($this->overlaps($existing, '08:00', '12:00'));
        $this->assertTrue($this->overlaps($existing, '07:00', '13:00'));
        $this->assertTrue($this->overlaps($existing, '11:59:59', '14:00:00'));
    }

    public function test_adjacent_times_do_not_overlap(): void
    {
        $existing = $this->existing();

        $this->assertFalse($this->overlaps($existing, '12:00', '15:00'));
        $this->assertFalse($this->overlaps($existing, '05:00', '08:00'));
    }

    public function test_a_template_of_another_day_or_doctor_does_not_overlap(): void
    {
        $existing = $this->existing();
        $otherDoctor = $this->createSchedule(['day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);

        $this->assertFalse($this->overlaps($existing, '08:00', '12:00', day: 2));
        $this->assertSame(
            [$existing->id],
            DoctorSchedule::query()->overlapping($existing->doctor_id, 1, '08:00', '12:00', CarbonImmutable::parse('2026-10-05'), null)->pluck('id')->all(),
        );
        $this->assertNotSame($existing->doctor_id, $otherDoctor->doctor_id);
    }

    public function test_an_inactive_template_is_not_counted_as_overlapping(): void
    {
        $existing = $this->existing();
        $existing->update(['is_active' => false]);

        $this->assertFalse($this->overlaps($existing, '08:00', '12:00'));
    }

    public function test_the_template_itself_is_excluded_with_ignore_id(): void
    {
        $existing = $this->existing();

        $this->assertTrue($this->overlaps($existing, '08:00', '12:00'));
        $this->assertFalse($this->overlaps($existing, '08:00', '12:00', ignoreId: $existing->id));
    }

    public function test_overlapping_considers_the_validity_ranges(): void
    {
        $bounded = $this->existing(['valid_from' => '2026-10-05', 'valid_until' => '2026-10-31']);

        // Rentang baru dimulai sehari setelah rentang lama berakhir: tidak beririsan.
        $this->assertFalse($this->overlaps($bounded, '08:00', '12:00', '2026-11-01', null));
        // Rentang baru berakhir sehari sebelum rentang lama mulai: tidak beririsan.
        $this->assertFalse($this->overlaps($bounded, '08:00', '12:00', '2026-09-01', '2026-10-04'));
        // Bersinggungan pada satu hari (31 Okt): beririsan.
        $this->assertTrue($this->overlaps($bounded, '08:00', '12:00', '2026-10-31', null));
        $this->assertTrue($this->overlaps($bounded, '08:00', '12:00', '2026-09-01', '2026-10-05'));
        // Rentang baru tanpa batas akhir yang mulai sebelum rentang lama berakhir: beririsan.
        $this->assertTrue($this->overlaps($bounded, '08:00', '12:00', '2026-10-01', null));
    }

    public function test_an_open_ended_existing_template_overlaps_any_later_range(): void
    {
        $openEnded = $this->existing(['valid_from' => '2026-10-05', 'valid_until' => null]);

        $this->assertTrue($this->overlaps($openEnded, '08:00', '12:00', '2027-06-01', '2027-06-30'));
        $this->assertFalse($this->overlaps($openEnded, '08:00', '12:00', '2026-09-01', '2026-10-04'));
    }

    // --- Hapus dan appointment mendatang ---

    public function test_deletion_is_allowed_without_sessions_or_with_sessions_that_never_had_appointments(): void
    {
        $schedule = $this->createSchedule();
        $this->assertNull($schedule->deletionBlockedReason());

        $this->createSession(['doctor_id' => $schedule->doctor_id, 'clinic_id' => $schedule->clinic_id, 'doctor_schedule_id' => $schedule->id]);

        $this->assertNull($schedule->deletionBlockedReason());
    }

    public function test_deletion_is_blocked_by_any_appointment_even_a_cancelled_one(): void
    {
        $schedule = $this->createSchedule();
        $session = $this->createSession(['doctor_id' => $schedule->doctor_id, 'clinic_id' => $schedule->clinic_id, 'doctor_schedule_id' => $schedule->id]);
        $this->bookAppointment($session, AppointmentStatus::Cancelled);

        $this->assertSame(
            'Jadwal ini sudah memiliki sesi dengan pendaftaran dan hanya dapat dinonaktifkan.',
            $schedule->deletionBlockedReason(),
        );
    }

    public function test_has_upcoming_active_appointments(): void
    {
        $schedule = $this->createSchedule();
        $make = fn (string $date, string $start = '08:00:00', string $end = '12:00:00'): PracticeSession => $this->createSession([
            'doctor_id' => $schedule->doctor_id, 'clinic_id' => $schedule->clinic_id,
            'doctor_schedule_id' => $schedule->id, 'session_date' => $date, 'start_time' => $start, 'end_time' => $end,
        ]);

        $this->assertFalse($schedule->hasUpcomingActiveAppointments());

        $this->bookAppointment($make('2026-10-12'), AppointmentStatus::Cancelled);
        $this->bookAppointment($make('2026-10-13'), AppointmentStatus::Completed);
        $this->assertFalse($schedule->hasUpcomingActiveAppointments());

        $this->bookAppointment($make('2026-10-05', '06:00:00', '07:00:00'), AppointmentStatus::Confirmed);
        $this->assertFalse($schedule->hasUpcomingActiveAppointments(), 'Sesi hari ini yang sudah berakhir tidak dihitung.');

        $this->bookAppointment($make('2026-10-20'), AppointmentStatus::Pending);
        $this->assertTrue($schedule->hasUpcomingActiveAppointments());
    }
}
