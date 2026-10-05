<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Admin\DoctorScheduleRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-23 dan BR-07..BR-11: VAL-05 (form jadwal), JDW-06 (templat beririsan), Review Focus 2 (bertetangga,
 * beririsan, rentang berbeda, nonaktif, ubah tidak bentrok dengan dirinya sendiri), JDW-08(d) sisi Form Request.
 */
class DoctorScheduleRequestTest extends TestCase
{
    use InteractsWithFormRequests;
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Clinic $clinic;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 5 Oktober 2026 pukul 08.00 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));

        $this->clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->doctor = $this->createDoctor(['name' => 'Raka Wirawan'], $this->clinic);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'doctor_id' => (string) $this->doctor->id,
            'clinic_id' => (string) $this->clinic->id,
            'day_of_week' => '1',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'quota' => '20',
            'valid_from' => '2026-10-05',
            'valid_until' => '',
            'is_active' => '1',
            'notes' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, list<string>>
     */
    private function errorsFor(array $overrides = [], ?DoctorSchedule $schedule = null): array
    {
        return $this->validationErrors(
            DoctorScheduleRequest::class,
            $this->payload($overrides),
            null,
            $schedule === null ? [] : ['schedule' => $schedule],
        );
    }

    /**
     * Templat Senin 08.00-12.00 milik dokter uji, berlaku mulai 5 Okt 2026 tanpa batas akhir.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function existing(array $overrides = []): DoctorSchedule
    {
        return $this->createSchedule(array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-05',
            'valid_until' => null,
            'is_active' => true,
        ], $overrides));
    }

    public function test_a_valid_payload_passes_and_yields_model_attributes(): void
    {
        $request = $this->makeFormRequest(DoctorScheduleRequest::class, $this->payload(['notes' => 'Ruang 2']));
        $request->validateResolved();

        $this->assertSame([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'quota' => 20,
            'valid_from' => '2026-10-05',
            'valid_until' => null,
            'is_active' => true,
            'notes' => 'Ruang 2',
        ], $request->attributesForModel());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'dokter kosong' => [['doctor_id' => ''], 'doctor_id', 'Dokter wajib dipilih.'],
            'dokter tidak ada' => [['doctor_id' => '999999'], 'doctor_id', 'Dokter yang dipilih tidak tersedia.'],
            'dokter bukan angka' => [['doctor_id' => 'abc'], 'doctor_id', 'Dokter yang dipilih tidak tersedia.'],
            'poliklinik kosong' => [['clinic_id' => ''], 'clinic_id', 'Poliklinik wajib dipilih.'],
            'poliklinik tidak ada' => [['clinic_id' => '999999'], 'clinic_id', 'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.'],
            'hari kosong' => [['day_of_week' => ''], 'day_of_week', 'Hari wajib dipilih.'],
            'hari 8' => [['day_of_week' => '8'], 'day_of_week', 'Hari tidak valid.'],
            'hari 0' => [['day_of_week' => '0'], 'day_of_week', 'Hari tidak valid.'],
            'hari bukan angka' => [['day_of_week' => 'senin'], 'day_of_week', 'Hari tidak valid.'],
            'jam mulai kosong' => [['start_time' => ''], 'start_time', 'Jam mulai wajib diisi.'],
            'jam mulai format salah' => [['start_time' => '8.00'], 'start_time', 'Format jam mulai tidak valid. Gunakan format JJ:MM.'],
            'jam selesai kosong' => [['end_time' => ''], 'end_time', 'Jam selesai wajib diisi.'],
            'jam selesai format salah' => [['end_time' => '25:00'], 'end_time', 'Format jam selesai tidak valid. Gunakan format JJ:MM.'],
            'jam selesai sama dengan mulai' => [['start_time' => '08:00', 'end_time' => '08:00'], 'end_time', 'Jam selesai harus setelah jam mulai.'],
            'jam selesai sebelum mulai' => [['start_time' => '12:00', 'end_time' => '08:00'], 'end_time', 'Jam selesai harus setelah jam mulai.'],
            'kuota kosong' => [['quota' => ''], 'quota', 'Kuota wajib diisi.'],
            'kuota nol' => [['quota' => '0'], 'quota', 'Kuota minimal 1 dan maksimal 200.'],
            'kuota 201' => [['quota' => '201'], 'quota', 'Kuota minimal 1 dan maksimal 200.'],
            'kuota bukan angka bulat' => [['quota' => '12.5'], 'quota', 'Kuota harus berupa angka bulat.'],
            'kuota huruf' => [['quota' => 'dua puluh'], 'quota', 'Kuota harus berupa angka bulat.'],
            'berlaku mulai kosong' => [['valid_from' => ''], 'valid_from', 'Tanggal mulai berlaku wajib diisi.'],
            'berlaku mulai format salah' => [['valid_from' => '05/10/2026'], 'valid_from', 'Format tanggal tidak valid.'],
            'berlaku mulai di masa lalu' => [['valid_from' => '2026-10-04'], 'valid_from', 'Tanggal mulai berlaku tidak boleh di masa lalu.'],
            'berlaku sampai format salah' => [['valid_until' => '31-12-2026'], 'valid_until', 'Format tanggal tidak valid.'],
            'berlaku sampai sebelum mulai' => [['valid_from' => '2026-10-12', 'valid_until' => '2026-10-11'], 'valid_until', 'Tanggal berlaku sampai tidak boleh sebelum tanggal mulai berlaku.'],
            'status aktif tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'catatan terlalu panjang' => [['notes' => str_repeat('a', 256)], 'notes', 'Catatan maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_val_05_invalid_input_gets_the_indonesian_message(array $override, string $field, string $message): void
    {
        $this->assertContains($message, $this->errorsFor($override)[$field] ?? []);
    }

    public function test_valid_until_may_equal_valid_from_and_may_be_empty(): void
    {
        $this->assertSame([], $this->errorsFor(['valid_from' => '2026-10-12', 'valid_until' => '2026-10-12']));
        $this->assertSame([], $this->errorsFor(['valid_until' => '']));
    }

    public function test_quota_boundaries_one_and_two_hundred_are_accepted(): void
    {
        $this->assertSame([], $this->errorsFor(['quota' => '1']));
        $this->assertSame([], $this->errorsFor(['quota' => '200']));
    }

    public function test_clinic_must_be_active_and_bookable(): void
    {
        $inactive = $this->createClinic(['is_active' => false]);
        $support = $this->createClinic(['is_bookable' => false]);
        $message = 'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.';

        $this->assertContains($message, $this->errorsFor(['clinic_id' => (string) $inactive->id])['clinic_id'] ?? []);
        $this->assertContains($message, $this->errorsFor(['clinic_id' => (string) $support->id])['clinic_id'] ?? []);
    }

    public function test_archived_doctor_cannot_be_given_a_schedule_but_inactive_doctor_can(): void
    {
        $archived = $this->createDoctor(['status' => 'archived', 'archived_at' => now()->subDay()], $this->clinic);
        $inactive = $this->createDoctor(['status' => 'inactive'], $this->clinic);

        $this->assertContains(
            'Dokter yang dipilih tidak tersedia.',
            $this->errorsFor(['doctor_id' => (string) $archived->id])['doctor_id'] ?? [],
        );
        $this->assertSame([], $this->errorsFor(['doctor_id' => (string) $inactive->id]));
    }

    // --- JDW-06 dan Review Focus 2: irisan templat ---------------------------------------------

    public function test_jdw_06_overlapping_template_is_rejected_and_names_the_conflicting_template(): void
    {
        $this->existing();

        $errors = $this->errorsFor(['start_time' => '11:00', 'end_time' => '13:00']);

        $this->assertCount(1, $errors['start_time'] ?? []);
        $this->assertSame(
            'Jadwal bertabrakan dengan jadwal aktif lain milik dokter ini pada hari yang sama (Senin 08.00–12.00, berlaku 5 Okt 2026 – tanpa batas).',
            $errors['start_time'][0],
        );
    }

    public function test_adjacent_templates_are_accepted(): void
    {
        $this->existing();

        $this->assertSame([], $this->errorsFor(['start_time' => '12:00', 'end_time' => '15:00']));
        $this->assertSame([], $this->errorsFor(['start_time' => '05:00', 'end_time' => '08:00']));
    }

    public function test_jdw_06_non_overlapping_validity_range_is_accepted(): void
    {
        $this->existing(['valid_until' => '2026-10-31']);

        $this->assertSame([], $this->errorsFor(['valid_from' => '2026-11-02', 'start_time' => '11:00', 'end_time' => '13:00']));
        $this->assertNotSame([], $this->errorsFor(['valid_from' => '2026-10-26', 'start_time' => '11:00', 'end_time' => '13:00']));
    }

    public function test_a_template_that_contains_or_is_contained_by_another_overlaps(): void
    {
        $this->existing();

        $this->assertArrayHasKey('start_time', $this->errorsFor(['start_time' => '09:00', 'end_time' => '10:00']));
        $this->assertArrayHasKey('start_time', $this->errorsFor(['start_time' => '07:00', 'end_time' => '14:00']));
    }

    public function test_other_day_and_other_doctor_do_not_overlap(): void
    {
        $this->existing();
        $other = $this->createDoctor([], $this->clinic);

        $this->assertSame([], $this->errorsFor(['day_of_week' => '2', 'start_time' => '08:00', 'end_time' => '12:00']));
        $this->assertSame([], $this->errorsFor(['doctor_id' => (string) $other->id, 'start_time' => '08:00', 'end_time' => '12:00']));
    }

    public function test_overlap_is_checked_per_doctor_not_per_clinic_br_11(): void
    {
        $this->existing();
        $otherClinic = $this->createClinic(['name' => 'Poliklinik Bedah']);

        $this->assertArrayHasKey('start_time', $this->errorsFor([
            'clinic_id' => (string) $otherClinic->id,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]));
    }

    public function test_inactive_existing_template_is_not_a_conflict(): void
    {
        $this->existing(['is_active' => false]);

        $this->assertSame([], $this->errorsFor(['start_time' => '09:00', 'end_time' => '11:00']));
    }

    public function test_a_new_inactive_template_is_not_checked_for_overlap(): void
    {
        $this->existing();

        $this->assertSame([], $this->errorsFor(['is_active' => '0', 'start_time' => '09:00', 'end_time' => '11:00']));
    }

    public function test_updating_a_template_does_not_conflict_with_itself(): void
    {
        $schedule = $this->existing();

        $this->assertSame([], $this->errorsFor(['quota' => '30'], $schedule));
        $this->assertSame([], $this->errorsFor(['start_time' => '09:00', 'end_time' => '11:00'], $schedule));
    }

    public function test_updating_into_another_template_conflicts(): void
    {
        $schedule = $this->existing();
        $this->existing(['day_of_week' => 1, 'start_time' => '13:00:00', 'end_time' => '16:00:00']);

        $this->assertArrayHasKey('start_time', $this->errorsFor(['start_time' => '12:00', 'end_time' => '14:00'], $schedule));
    }

    public function test_valid_from_in_the_past_is_only_rejected_when_creating(): void
    {
        $schedule = $this->existing(['valid_from' => '2026-09-01']);

        $this->assertSame([], $this->errorsFor(['valid_from' => '2026-09-01'], $schedule));
        $this->assertContains(
            'Tanggal mulai berlaku tidak boleh di masa lalu.',
            $this->errorsFor(['valid_from' => '2026-09-01'])['valid_from'] ?? [],
        );
    }

    // --- Ubah: dokter tetap, ganti poliklinik --------------------------------------------------

    public function test_the_doctor_of_an_existing_template_cannot_be_changed(): void
    {
        $schedule = $this->existing();
        $other = $this->createDoctor([], $this->clinic);

        $errors = $this->errorsFor(['doctor_id' => (string) $other->id], $schedule);

        $this->assertContains(
            'Dokter pada templat tidak dapat diganti. Buat templat baru, lalu nonaktifkan templat lama.',
            $errors['doctor_id'] ?? [],
        );
    }

    public function test_jdw_08d_clinic_change_is_rejected_when_a_future_session_has_an_active_appointment(): void
    {
        $schedule = $this->existing();
        $session = $this->createSession([
            'doctor_schedule_id' => $schedule->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-12',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ]);
        $this->bookAppointment($session, AppointmentStatus::Confirmed);
        $otherClinic = $this->createClinic(['name' => 'Poliklinik Bedah']);

        $errors = $this->errorsFor(['clinic_id' => (string) $otherClinic->id], $schedule);

        $this->assertContains(
            'Poliklinik tidak dapat diganti karena masih ada sesi mendatang dengan pendaftaran aktif. Buat jadwal baru, lalu nonaktifkan jadwal lama.',
            $errors['clinic_id'] ?? [],
        );
    }

    public function test_clinic_change_is_allowed_when_only_cancelled_appointments_exist(): void
    {
        $schedule = $this->existing();
        $session = $this->createSession([
            'doctor_schedule_id' => $schedule->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-12',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
        $this->bookAppointment($session, AppointmentStatus::Cancelled);
        $otherClinic = $this->createClinic(['name' => 'Poliklinik Bedah']);

        $this->assertSame([], $this->errorsFor(['clinic_id' => (string) $otherClinic->id], $schedule));
    }

    // --- Otorisasi ------------------------------------------------------------------------------

    public function test_registration_officer_is_not_authorized_for_create_or_update(): void
    {
        $officer = $this->createStaff('registration-officer');

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(DoctorScheduleRequest::class, $this->payload(), $officer);
    }

    public function test_update_request_is_authorized_through_the_policy(): void
    {
        $schedule = $this->existing();
        $officer = $this->createStaff('registration-officer');

        $this->assertFalse($this->makeFormRequest(DoctorScheduleRequest::class, $this->payload(), $officer, ['schedule' => $schedule], 'PUT')->authorize());
        $this->assertTrue($this->makeFormRequest(DoctorScheduleRequest::class, $this->payload(), null, ['schedule' => $schedule], 'PUT')->authorize());
    }

    public function test_update_attributes_never_include_the_doctor(): void
    {
        $schedule = $this->existing();
        $request = $this->makeFormRequest(DoctorScheduleRequest::class, $this->payload(['quota' => '25']), null, ['schedule' => $schedule], 'PUT');
        $request->validateResolved();

        $attributes = $request->attributesForModel();

        $this->assertArrayNotHasKey('doctor_id', $attributes);
        $this->assertSame(25, $attributes['quota']);
    }
}
