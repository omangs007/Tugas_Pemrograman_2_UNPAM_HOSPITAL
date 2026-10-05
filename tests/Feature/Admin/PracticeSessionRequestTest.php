<?php

namespace Tests\Feature\Admin;

use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Http\Requests\Admin\PracticeSessionRequest;
use App\Http\Requests\Admin\UpdateSessionStatusRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
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
 * 12 V-24 dan BR-29..BR-35: sesi tambahan manual, ubah sesi (JDW-07 sisi Form Request), dan status sesi.
 * Aturan yang juga mengikat Super Admin: sesi lampau tidak diubah (F3-R27), kuota tidak di bawah booked_count.
 */
class PracticeSessionRequestTest extends TestCase
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

        // Senin, 5 Oktober 2026 pukul 10.00 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $this->clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->doctor = $this->createDoctor(['name' => 'Raka Wirawan'], $this->clinic);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'doctor_id' => (string) $this->doctor->id,
            'clinic_id' => (string) $this->clinic->id,
            'session_date' => '2026-10-08',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'quota' => '10',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, list<string>>
     */
    private function createErrors(array $overrides = []): array
    {
        return $this->validationErrors(PracticeSessionRequest::class, $this->createPayload($overrides));
    }

    /**
     * Sesi dr. Raka pada 8 Okt 2026 pukul 08.00-12.00 (mendatang), kuota 10, sudah terisi 5.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function upcomingSession(array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-08',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 10,
            'booked_count' => 5,
            'last_queue_number' => 5,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function updateErrors(PracticeSession $session, array $data): array
    {
        return $this->validationErrors(
            PracticeSessionRequest::class,
            array_merge(['start_time' => '08:00', 'end_time' => '12:00', 'quota' => '10'], $data),
            null,
            ['session' => $session],
            'PUT',
        );
    }

    // --- Tambah sesi manual ------------------------------------------------------------------------

    public function test_a_valid_manual_session_passes_and_yields_six_attributes(): void
    {
        $request = $this->makeFormRequest(PracticeSessionRequest::class, $this->createPayload());
        $request->validateResolved();

        $this->assertSame([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-08',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'quota' => 10,
        ], $request->sessionAttributes());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidCreateInputs(): array
    {
        return [
            'dokter kosong' => [['doctor_id' => ''], 'doctor_id', 'Dokter wajib dipilih.'],
            'dokter tidak ada' => [['doctor_id' => '999999'], 'doctor_id', 'Dokter yang dipilih tidak tersedia.'],
            'poliklinik kosong' => [['clinic_id' => ''], 'clinic_id', 'Poliklinik wajib dipilih.'],
            'poliklinik tidak ada' => [['clinic_id' => '999999'], 'clinic_id', 'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.'],
            'tanggal kosong' => [['session_date' => ''], 'session_date', 'Tanggal sesi wajib diisi.'],
            'tanggal di masa lalu' => [['session_date' => '2026-10-04'], 'session_date', 'Tanggal sesi tidak boleh di masa lalu.'],
            'tanggal lebih dari 365 hari' => [['session_date' => '2027-10-06'], 'session_date', 'Tanggal sesi maksimal 365 hari ke depan.'],
            'jam mulai kosong' => [['start_time' => ''], 'start_time', 'Jam mulai wajib diisi.'],
            'jam mulai format salah' => [['start_time' => '1pm'], 'start_time', 'Format jam mulai tidak valid. Gunakan format JJ:MM.'],
            'jam selesai kosong' => [['end_time' => ''], 'end_time', 'Jam selesai wajib diisi.'],
            'jam selesai tidak setelah mulai' => [['start_time' => '15:00', 'end_time' => '15:00'], 'end_time', 'Jam selesai harus setelah jam mulai.'],
            'kuota kosong' => [['quota' => ''], 'quota', 'Kuota wajib diisi.'],
            'kuota nol' => [['quota' => '0'], 'quota', 'Kuota minimal 1 dan maksimal 200.'],
            'kuota 201' => [['quota' => '201'], 'quota', 'Kuota minimal 1 dan maksimal 200.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidCreateInputs')]
    public function test_invalid_create_input_gets_the_indonesian_message(array $override, string $field, string $message): void
    {
        $this->assertContains($message, $this->createErrors($override)[$field] ?? []);
    }

    public function test_date_boundaries_today_and_exactly_365_days_ahead_are_accepted(): void
    {
        $this->assertSame([], $this->createErrors(['session_date' => '2026-10-05', 'start_time' => '14:00', 'end_time' => '16:00']));
        $this->assertSame([], $this->createErrors(['session_date' => '2027-10-05']));
    }

    public function test_manual_session_requires_an_active_doctor(): void
    {
        $inactive = $this->createDoctor(['status' => 'inactive'], $this->clinic);
        $archived = $this->createDoctor(['status' => 'archived', 'archived_at' => now()->subDay()], $this->clinic);

        foreach ([$inactive, $archived] as $doctor) {
            $this->assertContains(
                'Dokter yang dipilih tidak tersedia.',
                $this->createErrors(['doctor_id' => (string) $doctor->id])['doctor_id'] ?? [],
            );
        }
    }

    public function test_manual_session_clinic_must_be_active_and_bookable(): void
    {
        $inactive = $this->createClinic(['is_active' => false]);
        $support = $this->createClinic(['is_bookable' => false]);

        foreach ([$inactive, $support] as $clinic) {
            $this->assertContains(
                'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.',
                $this->createErrors(['clinic_id' => (string) $clinic->id])['clinic_id'] ?? [],
            );
        }
    }

    public function test_date_inside_a_doctor_leave_is_rejected_with_the_reason(): void
    {
        $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-09',
            'reason' => 'Cuti tahunan',
        ]);

        $this->assertContains(
            'Tanggal ini berada dalam pengecualian jadwal (Cuti tahunan). Hapus atau ubah pengecualian terlebih dahulu.',
            $this->createErrors(['session_date' => '2026-10-08'])['session_date'] ?? [],
        );
        $this->assertSame([], $this->createErrors(['session_date' => '2026-10-10']));
    }

    public function test_date_inside_a_hospital_holiday_or_another_doctors_leave(): void
    {
        $other = $this->createDoctor([], $this->clinic);
        $this->createException(['type' => ExceptionType::DoctorLeave, 'doctor_id' => $other->id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08', 'reason' => 'Cuti dokter lain']);

        $this->assertSame([], $this->createErrors(['session_date' => '2026-10-08']), 'Cuti dokter lain tidak memblokir dokter ini.');

        $this->createException(['type' => ExceptionType::HospitalHoliday, 'doctor_id' => null, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08', 'reason' => 'Hari ulang tahun RS']);

        $this->assertContains(
            'Tanggal ini berada dalam pengecualian jadwal (Hari ulang tahun RS). Hapus atau ubah pengecualian terlebih dahulu.',
            $this->createErrors(['session_date' => '2026-10-08'])['session_date'] ?? [],
        );
    }

    public function test_overlapping_session_of_the_same_doctor_is_rejected_but_adjacent_and_cancelled_are_not(): void
    {
        $this->upcomingSession(['booked_count' => 0, 'last_queue_number' => 0, 'start_time' => '13:00:00', 'end_time' => '16:00:00']);
        $this->upcomingSession([
            'booked_count' => 0,
            'last_queue_number' => 0,
            'status' => SessionStatus::Cancelled,
            'start_time' => '17:00:00',
            'end_time' => '19:00:00',
        ]);

        $overlap = 'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.';

        $this->assertContains($overlap, $this->createErrors(['start_time' => '15:00', 'end_time' => '17:00'])['start_time'] ?? []);
        $this->assertSame([], $this->createErrors(['start_time' => '16:00', 'end_time' => '17:00']), 'Bersebelahan diterima.');
        $this->assertSame([], $this->createErrors(['start_time' => '17:30', 'end_time' => '18:30']), 'Sesi batal tidak memblokir.');
        $this->assertSame([], $this->createErrors(['session_date' => '2026-10-09', 'start_time' => '13:00', 'end_time' => '16:00']), 'Tanggal lain diterima.');
    }

    public function test_overlap_is_per_doctor(): void
    {
        $this->upcomingSession(['booked_count' => 0, 'last_queue_number' => 0, 'start_time' => '13:00:00', 'end_time' => '16:00:00']);
        $other = $this->createDoctor([], $this->clinic);

        $this->assertSame([], $this->createErrors(['doctor_id' => (string) $other->id, 'start_time' => '13:00', 'end_time' => '16:00']));
    }

    // --- Ubah sesi: JDW-07 dan sesi lampau --------------------------------------------------------

    public function test_jdw_07_quota_below_booked_count_is_rejected_and_equal_accepted(): void
    {
        $session = $this->upcomingSession();

        $this->assertContains(
            'Kuota tidak boleh kurang dari jumlah pendaftaran yang sudah ada (5).',
            $this->updateErrors($session, ['quota' => '4'])['quota'] ?? [],
        );
        $this->assertSame([], $this->updateErrors($session, ['quota' => '5']));
        $this->assertSame([], $this->updateErrors($session, ['quota' => '30']));
    }

    public function test_updating_the_times_of_a_session_does_not_conflict_with_itself(): void
    {
        $session = $this->upcomingSession();

        $this->assertSame([], $this->updateErrors($session, ['start_time' => '09:00', 'end_time' => '11:00']));
    }

    public function test_updating_into_another_session_of_the_same_doctor_conflicts(): void
    {
        $session = $this->upcomingSession();
        $this->upcomingSession(['booked_count' => 0, 'last_queue_number' => 0, 'start_time' => '13:00:00', 'end_time' => '16:00:00']);

        $this->assertContains(
            'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.',
            $this->updateErrors($session, ['start_time' => '12:00', 'end_time' => '14:00'])['start_time'] ?? [],
        );
    }

    public function test_update_attributes_only_carry_times_and_quota_even_if_the_form_posts_more(): void
    {
        $session = $this->upcomingSession();
        $other = $this->createDoctor([], $this->clinic);
        $request = $this->makeFormRequest(PracticeSessionRequest::class, [
            'doctor_id' => (string) $other->id,
            'session_date' => '2026-11-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'quota' => '8',
        ], null, ['session' => $session], 'PUT');
        $request->validateResolved();

        $this->assertSame(['start_time' => '09:00', 'end_time' => '11:00', 'quota' => 8], $request->sessionAttributes());
    }

    public function test_f3_r27_an_ended_session_cannot_be_updated_even_by_super_admin(): void
    {
        $ended = $this->upcomingSession(['session_date' => '2026-10-05', 'start_time' => '07:00:00', 'end_time' => '09:00:00']);
        $superAdmin = $this->createStaff('super-admin');

        $errors = $this->validationErrors(
            PracticeSessionRequest::class,
            ['start_time' => '07:00', 'end_time' => '09:00', 'quota' => '10'],
            $superAdmin,
            ['session' => $ended],
            'PUT',
        );

        $this->assertContains('Sesi yang sudah lewat tidak dapat diubah.', $errors['start_time'] ?? []);
    }

    public function test_a_session_that_ends_later_today_can_still_be_updated(): void
    {
        $running = $this->upcomingSession(['session_date' => '2026-10-05', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

        $this->assertSame([], $this->updateErrors($running, ['start_time' => '09:00', 'end_time' => '13:00']));
    }

    public function test_registration_officer_is_not_authorized_to_create_or_update_sessions(): void
    {
        $officer = $this->createStaff('registration-officer');
        $session = $this->upcomingSession();

        $this->assertFalse($this->makeFormRequest(PracticeSessionRequest::class, $this->createPayload(), $officer)->authorize());
        $this->assertFalse($this->makeFormRequest(PracticeSessionRequest::class, [], $officer, ['session' => $session], 'PUT')->authorize());

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(PracticeSessionRequest::class, $this->createPayload(), $officer);
    }

    // --- Status sesi ---------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function statusErrors(PracticeSession $session, array $data): array
    {
        return $this->validationErrors(UpdateSessionStatusRequest::class, $data, null, ['session' => $session], 'PATCH');
    }

    public function test_status_open_and_closed_need_no_reason(): void
    {
        $session = $this->upcomingSession();

        $this->assertSame([], $this->statusErrors($session, ['status' => 'closed']));
        $this->assertSame([], $this->statusErrors($session, ['status' => 'open', 'reason' => '']));
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidStatusInputs(): array
    {
        return [
            'status kosong' => [[], 'status', 'Status sesi wajib dipilih.'],
            'status tidak valid' => [['status' => 'hancur'], 'status', 'Status sesi tidak valid.'],
            'needs_review tidak dapat dipilih' => [['status' => 'needs_review'], 'status', 'Status sesi tidak valid.'],
            'batalkan tanpa alasan' => [['status' => 'cancelled'], 'reason', 'Alasan pembatalan sesi wajib diisi.'],
            'batalkan alasan kosong' => [['status' => 'cancelled', 'reason' => '   '], 'reason', 'Alasan pembatalan sesi wajib diisi.'],
            'alasan terlalu pendek' => [['status' => 'cancelled', 'reason' => 'abcd'], 'reason', 'Alasan minimal 5 karakter.'],
            'alasan terlalu panjang' => [['status' => 'cancelled', 'reason' => 'aaaaa'.str_repeat('a', 251)], 'reason', 'Alasan maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('invalidStatusInputs')]
    public function test_invalid_status_input_gets_the_indonesian_message(array $data, string $field, string $message): void
    {
        $this->assertContains($message, $this->statusErrors($this->upcomingSession(), $data)[$field] ?? []);
    }

    public function test_cancelling_with_a_five_character_reason_is_accepted_and_trimmed(): void
    {
        $session = $this->upcomingSession();
        $request = $this->makeFormRequest(UpdateSessionStatusRequest::class, ['status' => 'cancelled', 'reason' => '  Dokter sakit  '], null, ['session' => $session], 'PATCH');
        $request->validateResolved();

        $this->assertSame(SessionStatus::Cancelled, $request->targetStatus());
        $this->assertSame('Dokter sakit', $request->reason());
    }

    public function test_f3_r27_status_of_an_ended_session_cannot_be_changed_even_by_super_admin(): void
    {
        $ended = $this->upcomingSession(['session_date' => '2026-10-05', 'start_time' => '07:00:00', 'end_time' => '09:00:00']);
        $superAdmin = $this->createStaff('super-admin');

        $errors = $this->validationErrors(UpdateSessionStatusRequest::class, ['status' => 'closed'], $superAdmin, ['session' => $ended], 'PATCH');

        $this->assertContains('Sesi yang sudah lewat tidak dapat diubah.', $errors['status'] ?? []);
        $this->assertSame('Sesi yang sudah lewat tidak dapat diubah.', session('error'));
    }

    public function test_registration_officer_cannot_change_session_status(): void
    {
        $officer = $this->createStaff('registration-officer');
        $session = $this->upcomingSession();

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(UpdateSessionStatusRequest::class, ['status' => 'closed'], $officer, ['session' => $session], 'PATCH');
    }
}
