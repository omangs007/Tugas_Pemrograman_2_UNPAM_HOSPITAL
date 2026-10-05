<?php

namespace Tests\Feature\Models;

use App\Enums\ExceptionType;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 11 §5.3, 12 BR-21 (pemilihan pengecualian), F3-R10.
 */
class ScheduleExceptionModelTest extends TestCase
{
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 08:00:00');
    }

    public function test_casts_and_relations(): void
    {
        $staff = $this->createStaff('super-admin');
        $this->actingAs($staff);
        $doctor = Doctor::factory()->create();
        $exception = $this->createException([
            'doctor_id' => $doctor->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-14', 'reason' => 'Seminar',
        ]);
        $session = PracticeSession::factory()->cancelled()->create([
            'doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'schedule_exception_id' => $exception->id,
        ]);

        $exception = ScheduleException::query()->with(['doctor', 'creator', 'sessions'])->findOrFail($exception->id);

        $this->assertSame(ExceptionType::DoctorLeave, $exception->type);
        $this->assertSame('2026-10-12', $exception->start_date->toDateString());
        $this->assertSame('2026-10-14', $exception->end_date->toDateString());
        $this->assertSame($doctor->id, $exception->doctor->id);
        $this->assertSame($staff->id, $exception->creator->id);
        $this->assertSame([$session->id], $exception->sessions->pluck('id')->all());
        $this->assertSame([$exception->id], $doctor->exceptions()->pluck('id')->all());
    }

    public function test_global_exception_has_no_doctor_relation(): void
    {
        $exception = ScheduleException::factory()->hospitalHoliday()->create();

        $this->assertNull($exception->fresh()->doctor_id);
        $this->assertNull(ScheduleException::query()->with('doctor')->findOrFail($exception->id)->doctor);
    }

    public function test_created_by_is_set_on_create_only_from_the_authenticated_user(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('content-admin');

        $anonymous = $this->createException();
        $this->assertNull($anonymous->fresh()->created_by);

        $this->actingAs($first);
        $exception = $this->createException();
        $this->assertSame($first->id, $exception->fresh()->created_by);

        $this->actingAs($second);
        $exception->update(['reason' => 'Diubah']);

        $this->assertSame($first->id, $exception->fresh()->created_by);
    }

    public function test_created_by_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new ScheduleException(['created_by' => 1]);
    }

    public function test_covering_scope_includes_both_ends(): void
    {
        $exception = $this->createException(['start_date' => '2026-10-12', 'end_date' => '2026-10-14']);

        $ids = fn (string $date): array => ScheduleException::query()->covering(CarbonImmutable::parse($date))->pluck('id')->all();

        $this->assertSame([], $ids('2026-10-11'));
        $this->assertSame([$exception->id], $ids('2026-10-12'));
        $this->assertSame([$exception->id], $ids('2026-10-13'));
        $this->assertSame([$exception->id], $ids('2026-10-14'));
        $this->assertSame([], $ids('2026-10-15'));
    }

    public function test_for_doctor_scope_returns_global_and_that_doctors_exceptions_only(): void
    {
        $doctor = Doctor::factory()->create();
        $other = Doctor::factory()->create();
        $own = $this->createException(['doctor_id' => $doctor->id]);
        $global = ScheduleException::factory()->nationalHoliday()->create();
        $foreign = $this->createException(['doctor_id' => $other->id]);

        $ids = ScheduleException::query()->forDoctor($doctor->id)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$own->id, $global->id], $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_upcoming_scope_keeps_exceptions_that_end_today_or_later(): void
    {
        $past = $this->createException(['start_date' => '2026-09-20', 'end_date' => '2026-10-04']);
        $endsToday = $this->createException(['start_date' => '2026-10-01', 'end_date' => '2026-10-05']);
        $future = $this->createException(['start_date' => '2026-10-20', 'end_date' => '2026-10-21']);

        $ids = ScheduleException::query()->upcoming()->orderBy('id')->pluck('id')->all();

        $this->assertSame([$endsToday->id, $future->id], $ids);
        $this->assertNotContains($past->id, $ids);
    }

    // --- pickFor (BR-21, F3-R10) ---

    private function pick(int $doctorId, string $date): ?int
    {
        return ScheduleException::pickFor(ScheduleException::query()->get(), $doctorId, CarbonImmutable::parse($date))?->id;
    }

    public function test_pick_for_returns_null_when_nothing_covers_the_date_or_applies_to_the_doctor(): void
    {
        $doctor = Doctor::factory()->create();
        $other = Doctor::factory()->create();
        $this->createException(['doctor_id' => $other->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-14']);

        $this->assertNull($this->pick($doctor->id, '2026-10-13'), 'Cuti dokter lain tidak berlaku.');
        $this->assertNull($this->pick($other->id, '2026-10-15'), 'Di luar rentang.');
        $this->assertNull($this->pick($other->id, '2026-10-11'), 'Di luar rentang.');
    }

    public function test_pick_for_prefers_the_doctor_specific_exception_over_a_global_one_even_with_a_larger_id(): void
    {
        $doctor = Doctor::factory()->create();
        $global = ScheduleException::factory()->hospitalHoliday()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-14']);
        $leave = $this->createException(['doctor_id' => $doctor->id, 'start_date' => '2026-10-13', 'end_date' => '2026-10-13']);

        $this->assertGreaterThan($global->id, $leave->id);
        $this->assertSame($leave->id, $this->pick($doctor->id, '2026-10-13'));
        $this->assertSame($global->id, $this->pick($doctor->id, '2026-10-12'));
    }

    public function test_pick_for_takes_the_smallest_id_among_equals(): void
    {
        $doctor = Doctor::factory()->create();
        $firstGlobal = ScheduleException::factory()->hospitalHoliday()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        ScheduleException::factory()->nationalHoliday()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        $firstLeave = $this->createException(['doctor_id' => $doctor->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        $this->createException(['doctor_id' => $doctor->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12']);

        $this->assertSame($firstLeave->id, $this->pick($doctor->id, '2026-10-12'));

        ScheduleException::query()->where('doctor_id', $doctor->id)->delete();

        $this->assertSame($firstGlobal->id, $this->pick($doctor->id, '2026-10-12'));
    }

    public function test_pick_for_uses_the_given_collection_without_querying(): void
    {
        $doctor = Doctor::factory()->create();
        $exception = $this->createException(['doctor_id' => $doctor->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        $collection = ScheduleException::query()->get();
        $exception->delete();

        $picked = ScheduleException::pickFor($collection, $doctor->id, CarbonImmutable::parse('2026-10-12'));

        $this->assertSame($exception->id, $picked?->id);
    }

    // --- Accessor ---

    public function test_is_global_and_range_label(): void
    {
        $doctor = Doctor::factory()->create();
        $leave = $this->createException(['doctor_id' => $doctor->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-14']);
        $holiday = ScheduleException::factory()->hospitalHoliday()->create(['start_date' => '2026-10-17', 'end_date' => '2026-10-17']);

        $this->assertFalse($leave->is_global);
        $this->assertTrue($holiday->is_global);
        $this->assertSame('12 Oktober 2026 – 14 Oktober 2026', $leave->range_label);
        $this->assertSame('17 Oktober 2026', $holiday->range_label);
    }
}
