<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Admin\ClinicRequest;
use App\Models\Clinic;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Titik sambung fase 2 yang diisi fase 3 (F3-R29): penghapusan poliklinik yang dirujuk jadwal, sesi, atau
 * appointment (BR-17, F2-R18) dan penguncian `code` poliklinik setelah ada appointment (BR-13, I-31).
 */
class ClinicSchedulingCarryTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const SCHEDULE_REASON = 'Poliklinik ini masih dipakai oleh jadwal atau data pendaftaran sehingga tidak dapat dihapus. Nonaktifkan saja.';

    private const DOCTOR_REASON = 'Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    /**
     * Poliklinik tanpa dokter yang menjadi tempat praktik dokter dari poliklinik lain, sehingga hanya
     * jadwal/sesi/appointment yang merujuknya.
     */
    private function clinicUsedOnlyBySchedules(): Clinic
    {
        $clinic = $this->createClinic();
        $schedule = DoctorSchedule::factory()->create(['clinic_id' => $clinic->id]);

        $this->assertSame(0, $clinic->doctors()->count());
        $this->assertSame($clinic->id, $schedule->clinic_id);

        return $clinic;
    }

    public function test_a_clinic_with_only_a_schedule_cannot_be_deleted(): void
    {
        $clinic = $this->clinicUsedOnlyBySchedules();

        $this->assertSame(self::SCHEDULE_REASON, $clinic->deletionBlockedReason());
    }

    public function test_a_clinic_with_only_a_session_cannot_be_deleted(): void
    {
        $clinic = $this->createClinic();
        PracticeSession::factory()->manual()->create(['clinic_id' => $clinic->id]);

        $this->assertSame(self::SCHEDULE_REASON, $clinic->deletionBlockedReason());
    }

    public function test_a_clinic_with_only_an_appointment_cannot_be_deleted(): void
    {
        $clinic = $this->createClinic();
        $session = PracticeSession::factory()->manual()->create(['clinic_id' => $clinic->id]);
        $this->bookAppointment($session, AppointmentStatus::Cancelled);

        $this->assertSame(self::SCHEDULE_REASON, $clinic->deletionBlockedReason());
        $this->assertSame(1, $clinic->appointments()->count());
    }

    public function test_the_doctor_message_takes_precedence_and_an_unused_clinic_stays_deletable(): void
    {
        $withDoctor = $this->createDoctor()->clinic()->first();
        $unused = $this->createClinic();

        $this->assertSame(self::DOCTOR_REASON, $withDoctor->deletionBlockedReason());
        $this->assertNull($unused->deletionBlockedReason());
    }

    #[DataProvider('deleters')]
    public function test_the_delete_route_refuses_a_clinic_used_by_a_schedule_even_for_super_admin(string $role): void
    {
        $clinic = $this->clinicUsedOnlyBySchedules();

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.clinics.destroy', $clinic))
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('error', self::SCHEDULE_REASON);

        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
    }

    public function test_clinic_has_appointments_reads_the_appointments_table(): void
    {
        $clinic = $this->createClinic();
        $method = new ReflectionMethod(ClinicRequest::class, 'clinicHasAppointments');

        $this->assertTrue($method->isProtected());
        $this->assertFalse($method->invoke(new ClinicRequest, $clinic));

        $session = PracticeSession::factory()->manual()->create(['clinic_id' => $clinic->id]);
        $this->assertFalse($method->invoke(new ClinicRequest, $clinic), 'Sesi tanpa appointment belum mengunci kode.');

        $this->bookAppointment($session, AppointmentStatus::Cancelled);

        $this->assertTrue($method->invoke(new ClinicRequest, $clinic));
        $this->assertFalse($method->invoke(new ClinicRequest, $this->createClinic()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Clinic $clinic, string $code): array
    {
        return [
            'name' => $clinic->name,
            'code' => $code,
            'type' => 'poliklinik',
            'is_bookable' => '1',
            'short_description' => 'Layanan contoh untuk pengujian.',
            'location' => 'Gedung A Lt. 1',
            'is_active' => '1',
            'sort_order' => '0',
        ];
    }

    public function test_the_clinic_code_is_locked_once_an_appointment_exists_but_stays_editable_before(): void
    {
        $clinic = $this->createClinic(['code' => 'ANK']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.clinics.update', $clinic), $this->payload($clinic, 'ANL'))
            ->assertSessionHasNoErrors();
        $this->assertSame('ANL', $clinic->fresh()->code);

        $session = PracticeSession::factory()->manual()->create(['clinic_id' => $clinic->id]);
        $this->bookAppointment($session);

        $this->put(route('admin.clinics.update', $clinic), $this->payload($clinic, 'ZZZ'))
            ->assertSessionHasErrors(['code' => 'Kode poliklinik tidak dapat diubah karena sudah dipakai pada nomor antrean.']);
        $this->assertSame('ANL', $clinic->fresh()->code);

        $this->put(route('admin.clinics.update', $clinic), $this->payload($clinic, 'ANL'))
            ->assertSessionHasNoErrors();
    }
}
