<?php

namespace Tests\Feature\Doctors;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\DoctorStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Doctors\DoctorService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Titik sambung F2-R8 yang diisi fase 3 (F3-R15, F3-R29): arsip dokter ditolak bila masih ada appointment
 * aktif pada sesi yang belum berakhir. Berlaku juga untuk Super Admin (CRD-03 dan JDW-16 bagian arsip).
 */
class DoctorArchiveAppointmentsTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const REFUSAL_ONE = 'Dokter masih memiliki 1 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function bookFor(Doctor $doctor, AppointmentStatus $status, array $session = []): Appointment
    {
        $practiceSession = PracticeSession::factory()->manual()->create(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => '2026-10-06',
        ], $session));

        return $this->bookAppointment($practiceSession, $status);
    }

    private function actor(): Actor
    {
        return Actor::staff($this->createStaff('super-admin'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function managers(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    public function test_archiving_a_doctor_without_appointments_is_allowed_by_the_service(): void
    {
        $doctor = $this->createDoctor();

        $archived = app(DoctorService::class)->archive($doctor, 'Pindah tugas', $this->actor());

        $this->assertSame(DoctorStatus::Archived, $archived->status);
        $this->assertNull(app(DoctorService::class)->archiveBlockedReason($doctor));
    }

    public function test_an_active_appointment_on_a_future_session_blocks_the_archive_with_the_specified_message(): void
    {
        $doctor = $this->createDoctor();
        $this->bookFor($doctor, AppointmentStatus::Confirmed);

        $this->assertSame(self::REFUSAL_ONE, app(DoctorService::class)->archiveBlockedReason($doctor));

        try {
            app(DoctorService::class)->archive($doctor, null, $this->actor());
            $this->fail('Arsip seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(self::REFUSAL_ONE, $e->errors()['status'][0]);
        }

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
        $this->assertNull($doctor->fresh()->archived_at);
    }

    public function test_each_active_status_blocks_and_the_message_counts_them(): void
    {
        $doctor = $this->createDoctor();
        $this->bookFor($doctor, AppointmentStatus::Pending);
        $this->bookFor($doctor, AppointmentStatus::Confirmed);
        $this->bookFor($doctor, AppointmentStatus::CheckedIn, ['session_date' => '2026-10-05', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

        $this->assertStringStartsWith('Dokter masih memiliki 3 pendaftaran aktif', (string) app(DoctorService::class)->archiveBlockedReason($doctor));
    }

    public function test_finished_cancelled_and_past_appointments_do_not_block_the_archive(): void
    {
        $doctor = $this->createDoctor();
        $this->bookFor($doctor, AppointmentStatus::Cancelled);
        $this->bookFor($doctor, AppointmentStatus::Completed);
        $this->bookFor($doctor, AppointmentStatus::NoShow);
        $this->bookFor($doctor, AppointmentStatus::Confirmed, ['session_date' => '2026-10-04', 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
        $this->bookFor($doctor, AppointmentStatus::Confirmed, ['session_date' => '2026-10-05', 'start_time' => '06:00:00', 'end_time' => '10:00:00']);

        $this->assertNull(app(DoctorService::class)->archiveBlockedReason($doctor), 'Sesi hari ini yang tepat berakhir pukul 10.00 sudah berakhir.');
        $this->assertSame(DoctorStatus::Archived, app(DoctorService::class)->archive($doctor, null, $this->actor())->status);
    }

    public function test_appointments_of_another_doctor_do_not_block(): void
    {
        $doctor = $this->createDoctor();
        $other = $this->createDoctor();
        $this->bookFor($other, AppointmentStatus::Confirmed);

        $this->assertNull(app(DoctorService::class)->archiveBlockedReason($doctor));
        $this->assertSame(self::REFUSAL_ONE, app(DoctorService::class)->archiveBlockedReason($other));
    }

    public function test_the_block_is_lifted_when_the_appointment_is_cancelled(): void
    {
        $doctor = $this->createDoctor();
        $appointment = $this->bookFor($doctor, AppointmentStatus::Confirmed);
        $this->assertNotNull(app(DoctorService::class)->archiveBlockedReason($doctor));

        $appointment->forceFill(['status' => AppointmentStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by_type' => ActorType::Staff])->save();

        $this->assertNull(app(DoctorService::class)->archiveBlockedReason($doctor));
    }

    #[DataProvider('managers')]
    public function test_the_archive_route_refuses_even_for_super_admin_and_keeps_the_doctor_active(string $role): void
    {
        $doctor = $this->createDoctor();
        $this->bookFor($doctor, AppointmentStatus::Confirmed);

        $this->actingAs($this->createStaff($role))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.archive', $doctor), ['reason' => 'Pindah tugas'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('error', self::REFUSAL_ONE);

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'doctor.archived']);
    }

    #[DataProvider('managers')]
    public function test_the_archive_route_succeeds_once_no_active_future_appointment_remains(string $role): void
    {
        $doctor = $this->createDoctor();
        // Sesi lampau (4 Okt): tidak disentuh applyDoctorInactive (T8), sehingga flash tetap tanpa ringkasan laporan.
        $this->bookFor($doctor, AppointmentStatus::Completed, ['session_date' => '2026-10-04']);

        $this->actingAs($this->createStaff($role))
            ->patch(route('admin.doctors.archive', $doctor))
            ->assertSessionHas('success', 'Dokter diarsipkan.');

        $this->assertSame(DoctorStatus::Archived, $doctor->fresh()->status);
    }
}
