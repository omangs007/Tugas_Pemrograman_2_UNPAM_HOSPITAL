<?php

namespace Tests\Feature\Admin;

use App\Enums\ExceptionType;
use App\Http\Requests\Admin\ScheduleExceptionRequest;
use App\Models\Doctor;
use App\Models\ScheduleException;
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
 * 12 V-25 dan BR-23..BR-26: JDW-18 (kombinasi jenis vs dokter, sisi Form Request) dan F3-R21
 * (ubah: `start_date` yang tidak berubah boleh tetap di masa lalu).
 */
class ScheduleExceptionRequestTest extends TestCase
{
    use InteractsWithFormRequests;
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 5 Oktober 2026 pukul 08.00 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));

        $this->doctor = $this->createDoctor(['name' => 'Raka Wirawan']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'doctor_leave',
            'doctor_id' => (string) $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-09',
            'reason' => 'Cuti tahunan',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, list<string>>
     */
    private function errorsFor(array $overrides = [], ?ScheduleException $exception = null): array
    {
        return $this->validationErrors(
            ScheduleExceptionRequest::class,
            $this->payload($overrides),
            null,
            $exception === null ? [] : ['exception' => $exception],
        );
    }

    public function test_a_valid_payload_passes_and_yields_model_attributes(): void
    {
        $request = $this->makeFormRequest(ScheduleExceptionRequest::class, $this->payload());
        $request->validateResolved();

        $this->assertSame([
            'type' => 'doctor_leave',
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-09',
            'reason' => 'Cuti tahunan',
        ], $request->attributesForModel());
    }

    // --- JDW-18: kombinasi jenis vs dokter (BR-25) ----------------------------------------------

    public function test_jdw_18_doctor_leave_without_a_doctor_is_rejected(): void
    {
        $this->assertContains(
            'Pilih dokter untuk cuti dokter.',
            $this->errorsFor(['type' => 'doctor_leave', 'doctor_id' => ''])['doctor_id'] ?? [],
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function holidayTypes(): array
    {
        return ['libur rumah sakit' => ['hospital_holiday'], 'libur nasional' => ['national_holiday']];
    }

    #[DataProvider('holidayTypes')]
    public function test_jdw_18_holidays_with_a_doctor_are_rejected_and_without_a_doctor_accepted(string $type): void
    {
        $this->assertContains(
            'Libur rumah sakit atau libur nasional berlaku untuk semua dokter. Kosongkan pilihan dokter.',
            $this->errorsFor(['type' => $type])['doctor_id'] ?? [],
        );

        $this->assertSame([], $this->errorsFor(['type' => $type, 'doctor_id' => '']));
    }

    public function test_jdw_18_other_type_is_accepted_with_and_without_a_doctor(): void
    {
        $this->assertSame([], $this->errorsFor(['type' => 'other']));
        $this->assertSame([], $this->errorsFor(['type' => 'other', 'doctor_id' => '']));
    }

    public function test_jdw_18_doctor_leave_with_a_doctor_is_accepted(): void
    {
        $this->assertSame([], $this->errorsFor(['type' => 'doctor_leave']));
    }

    public function test_global_exception_attributes_carry_a_null_doctor(): void
    {
        $request = $this->makeFormRequest(ScheduleExceptionRequest::class, $this->payload(['type' => 'hospital_holiday', 'doctor_id' => '']));
        $request->validateResolved();

        $this->assertNull($request->attributesForModel()['doctor_id']);
    }

    // --- Field lain ------------------------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'jenis kosong' => [['type' => ''], 'type', 'Jenis pengecualian wajib dipilih.'],
            'jenis tidak valid' => [['type' => 'libur-bebas'], 'type', 'Jenis pengecualian tidak valid.'],
            'dokter tidak ada' => [['doctor_id' => '999999'], 'doctor_id', 'Dokter yang dipilih tidak tersedia.'],
            'mulai kosong' => [['start_date' => ''], 'start_date', 'Tanggal mulai wajib diisi.'],
            'mulai format salah' => [['start_date' => '07/10/2026'], 'start_date', 'Format tanggal tidak valid.'],
            'mulai di masa lalu' => [['start_date' => '2026-10-04', 'end_date' => '2026-10-06'], 'start_date', 'Tanggal mulai tidak boleh di masa lalu.'],
            'selesai kosong' => [['end_date' => ''], 'end_date', 'Tanggal selesai wajib diisi.'],
            'selesai format salah' => [['end_date' => 'besok'], 'end_date', 'Format tanggal tidak valid.'],
            'selesai sebelum mulai' => [['start_date' => '2026-10-09', 'end_date' => '2026-10-08'], 'end_date', 'Tanggal selesai tidak boleh sebelum tanggal mulai.'],
            'rentang 366 hari' => [['start_date' => '2026-10-05', 'end_date' => '2027-10-06'], 'end_date', 'Rentang pengecualian maksimal 365 hari.'],
            'alasan kosong' => [['reason' => ''], 'reason', 'Alasan wajib diisi.'],
            'alasan terlalu pendek' => [['reason' => 'ab'], 'reason', 'Alasan minimal 3 karakter.'],
            'alasan terlalu panjang' => [['reason' => str_repeat('a', 256)], 'reason', 'Alasan maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_gets_the_indonesian_message(array $override, string $field, string $message): void
    {
        $this->assertContains($message, $this->errorsFor($override)[$field] ?? []);
    }

    public function test_start_today_and_a_range_of_exactly_365_days_are_accepted(): void
    {
        $this->assertSame([], $this->errorsFor(['start_date' => '2026-10-05', 'end_date' => '2026-10-05']));
        $this->assertSame([], $this->errorsFor(['start_date' => '2026-10-05', 'end_date' => '2027-10-05']));
    }

    public function test_archived_doctor_is_not_selectable_for_leave(): void
    {
        $archived = $this->createDoctor(['status' => 'archived', 'archived_at' => now()->subDay()]);

        $this->assertContains(
            'Dokter yang dipilih tidak tersedia.',
            $this->errorsFor(['doctor_id' => (string) $archived->id])['doctor_id'] ?? [],
        );
    }

    // --- F3-R21: ubah dengan start_date di masa lalu ---------------------------------------------

    public function test_f3_r21_update_may_keep_an_unchanged_start_date_in_the_past(): void
    {
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'reason' => 'Cuti panjang',
        ]);

        $this->assertSame([], $this->errorsFor(['start_date' => '2026-10-01', 'end_date' => '2026-10-08'], $exception));
    }

    public function test_f3_r21_update_that_changes_start_date_to_the_past_is_rejected(): void
    {
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-10',
        ]);

        $errors = $this->errorsFor(['start_date' => '2026-10-02', 'end_date' => '2026-10-10'], $exception);

        $this->assertContains('Tanggal mulai tidak boleh di masa lalu.', $errors['start_date'] ?? []);
    }

    public function test_creating_with_a_past_start_date_that_equals_another_rows_start_is_still_rejected(): void
    {
        $this->createException(['start_date' => '2026-10-01', 'end_date' => '2026-10-10']);

        $this->assertContains(
            'Tanggal mulai tidak boleh di masa lalu.',
            $this->errorsFor(['start_date' => '2026-10-01', 'end_date' => '2026-10-10'])['start_date'] ?? [],
        );
    }

    // --- Otorisasi -------------------------------------------------------------------------------

    public function test_registration_officer_is_not_authorized(): void
    {
        $officer = $this->createStaff('registration-officer');

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(ScheduleExceptionRequest::class, $this->payload(), $officer);
    }
}
