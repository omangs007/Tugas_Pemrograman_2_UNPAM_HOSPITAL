<?php

namespace Tests\Unit\Requests;

use App\Enums\PaymentType;
use App\Http\Requests\Patient\ReviewAppointmentRequest;
use App\Http\Requests\Patient\StoreAppointmentRequest;
use App\Models\InsurancePartner;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Scheduling\PracticeSessionGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * V-10/V-11 (F6-R21): normalisasi BPJS/polis/keluhan, isian BPJS dari profil, `save_bpjs`, bentuk `BookingData`, URL
 * pengalihan, dan otorisasi. Aturan per field diuji lewat HTTP di BookingSubmissionTest (VAL-03).
 */
class ReviewAppointmentRequestTest extends TestCase
{
    use InteractsWithFormRequests;
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00');
        Cache::put(PracticeSessionGenerator::markerKey(CarbonImmutable::today()), true, CarbonImmutable::today()->endOfDay());
    }

    private function openSession(): PracticeSession
    {
        return $this->createSession(['session_date' => '2026-10-06', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function data(PracticeSession $session, array $overrides = []): array
    {
        return $overrides + ['practice_session_id' => (string) $session->id, 'payment_type' => 'umum'];
    }

    /** @param  array<string, mixed>  $data */
    private function validated(array $data, User $user, string $class = ReviewAppointmentRequest::class): ReviewAppointmentRequest
    {
        $request = $this->makeFormRequest($class, $data, $user);
        $request->validateResolved();

        return $request;
    }

    // --- normalisasi -------------------------------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function bpjsInputs(): array
    {
        return [
            'tanpa spasi' => ['0009900000011', '0009900000011'],
            'spasi' => ['000 9900 000 011', '0009900000011'],
            'tanda hubung' => ['0009-9000-00011', '0009900000011'],
            'spasi dan hubung' => [' 000-99 00000-011 ', '0009900000011'],
        ];
    }

    #[DataProvider('bpjsInputs')]
    public function test_bpjs_numbers_lose_spaces_and_hyphens_before_validation(string $input, string $expected): void
    {
        $user = $this->createPatient();
        $request = $this->validated($this->data($this->openSession(), ['payment_type' => 'bpjs', 'bpjs_number' => $input]), $user);

        $this->assertSame($expected, $request->validated('bpjs_number'));
        $this->assertTrue($request->bpjsTyped());
        $this->assertSame($expected, $request->toBookingData()->paymentReference);
    }

    public function test_policy_numbers_lose_spaces_but_keep_hyphens_and_slashes(): void
    {
        $user = $this->createPatient();
        $partner = InsurancePartner::factory()->create();
        $request = $this->validated($this->data($this->openSession(), ['payment_type' => 'asuransi', 'insurance_partner_id' => (string) $partner->id, 'policy_number' => ' AB 12/3-4 ']), $user);

        $this->assertSame('AB12/3-4', $request->validated('policy_number'));
        $this->assertSame('AB12/3-4', $request->toBookingData()->paymentReference);
        $this->assertSame($partner->id, $request->toBookingData()->insurancePartnerId);
    }

    public function test_the_complaint_is_trimmed_and_blank_becomes_null(): void
    {
        $user = $this->createPatient();
        $session = $this->openSession();

        $this->assertSame('Pusing sejak pagi.', $this->validated($this->data($session, ['complaint' => "  Pusing sejak pagi.\n"]), $user)->toBookingData()->complaint);
        $this->assertNull($this->validated($this->data($session, ['complaint' => '   ']), $user)->toBookingData()->complaint);
        $this->assertNull($this->validated($this->data($session), $user)->toBookingData()->complaint);
    }

    public function test_a_general_payment_carries_no_reference_and_no_partner(): void
    {
        $user = $this->createPatient();
        $data = $this->validated($this->data($this->openSession()), $user)->toBookingData();

        $this->assertSame(PaymentType::Umum, $data->paymentType);
        $this->assertNull($data->paymentReference);
        $this->assertNull($data->insurancePartnerId);
        $this->assertFalse($data->saveBpjs);
    }

    // --- BPJS dari profil dan save_bpjs (F6-R21) -----------------------------------------------

    public function test_an_empty_bpjs_field_is_filled_from_the_stored_profile_number(): void
    {
        $user = $this->createPatient([], ['bpjs_number' => self::BPJS]);
        $request = $this->validated($this->data($this->openSession(), ['payment_type' => 'bpjs', 'save_bpjs' => '1']), $user);

        $this->assertSame(self::BPJS, $request->validated('bpjs_number'));
        $this->assertFalse($request->bpjsTyped(), 'Nomor berasal dari profil, bukan diketik pasien.');
        $this->assertSame(self::BPJS, $request->toBookingData()->paymentReference);
        $this->assertFalse($request->toBookingData()->saveBpjs, 'Tidak ada yang perlu disimpan: nomor sudah tersimpan.');
    }

    public function test_a_typed_bpjs_number_wins_over_the_stored_one(): void
    {
        $user = $this->createPatient([], ['bpjs_number' => self::BPJS]);
        $request = $this->validated($this->data($this->openSession(), ['payment_type' => 'bpjs', 'bpjs_number' => '0001234567890']), $user);

        $this->assertSame('0001234567890', $request->toBookingData()->paymentReference);
        $this->assertTrue($request->bpjsTyped());
    }

    public function test_without_a_stored_number_an_empty_bpjs_field_is_a_validation_error(): void
    {
        $user = $this->createPatient();

        try {
            $this->validated($this->data($this->openSession(), ['payment_type' => 'bpjs']), $user);
            $this->fail('Nomor BPJS kosong tanpa nomor tersimpan harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Nomor BPJS wajib diisi untuk pembayaran BPJS.'], $exception->errors()['bpjs_number']);
        }
    }

    public function test_the_stored_number_is_never_used_for_other_payment_types(): void
    {
        $user = $this->createPatient([], ['bpjs_number' => self::BPJS]);
        $request = $this->validated($this->data($this->openSession()), $user);

        $this->assertNull($request->validated('bpjs_number') ?? null);
        $this->assertNull($request->toBookingData()->paymentReference);
    }

    /** @return array<string, array{array<string, mixed>, bool}> */
    public static function saveBpjsCases(): array
    {
        return [
            'diketik dan dicentang' => [['bpjs_number' => '0009900000011', 'save_bpjs' => '1'], true],
            'diketik dan benar' => [['bpjs_number' => '0009900000011', 'save_bpjs' => true], true],
            'diketik tanpa centang' => [['bpjs_number' => '0009900000011'], false],
            'diketik dengan nol' => [['bpjs_number' => '0009900000011', 'save_bpjs' => '0'], false],
        ];
    }

    /** @param  array<string, mixed>  $extra */
    #[DataProvider('saveBpjsCases')]
    public function test_save_bpjs_only_applies_to_a_typed_bpjs_number(array $extra, bool $expected): void
    {
        $user = $this->createPatient();

        $this->assertSame($expected, $this->validated($this->data($this->openSession(), ['payment_type' => 'bpjs'] + $extra), $user)->toBookingData()->saveBpjs);
    }

    public function test_save_bpjs_is_ignored_for_other_payment_types(): void
    {
        $user = $this->createPatient();

        $this->assertFalse($this->validated($this->data($this->openSession(), ['save_bpjs' => '1']), $user)->toBookingData()->saveBpjs);
    }

    // --- Store = Review ------------------------------------------------------------------------

    public function test_the_store_request_has_the_same_rules_normalisation_and_precheck(): void
    {
        $user = $this->createPatient();
        $session = $this->openSession();

        $this->assertInstanceOf(ReviewAppointmentRequest::class, new StoreAppointmentRequest);
        $this->assertSame(
            '0009900000011',
            $this->validated($this->data($session, ['payment_type' => 'bpjs', 'bpjs_number' => '000 9900-000011']), $user, StoreAppointmentRequest::class)->validated('bpjs_number'),
        );

        $this->expectException(ValidationException::class);
        $this->validated($this->data($session, ['payment_type' => 'bpjs', 'bpjs_number' => '12']), $user, StoreAppointmentRequest::class);
    }

    // --- pengalihan dan precheck ---------------------------------------------------------------

    public function test_a_validation_failure_redirects_to_the_wizard_with_the_session_id(): void
    {
        $user = $this->createPatient();
        $session = $this->openSession();

        foreach ([ReviewAppointmentRequest::class, StoreAppointmentRequest::class] as $class) {
            try {
                $this->validated($this->data($session, ['payment_type' => 'bpjs', 'bpjs_number' => '123']), $user, $class);
                $this->fail('Harus ditolak.');
            } catch (ValidationException $exception) {
                $this->assertSame(route('patient.appointments.create', ['sesi' => (string) $session->id]), $exception->redirectTo, $class);
            }
        }
    }

    /** @return array<string, array{mixed}> */
    public static function unusableSessionIds(): array
    {
        return [
            'kosong' => [null],
            'teks' => ['abc'],
            'larik' => [['1']],
            'nol' => ['0'],
            'negatif' => ['-5'],
            'terlalu panjang' => ['1234567890123456789'],
            'desimal' => ['1.5'],
        ];
    }

    #[DataProvider('unusableSessionIds')]
    public function test_an_unusable_session_id_redirects_to_the_start_of_the_wizard(mixed $id): void
    {
        $user = $this->createPatient();

        try {
            $this->validated(['practice_session_id' => $id, 'payment_type' => 'umum'], $user);
            $this->fail('Harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(route('patient.appointments.create'), $exception->redirectTo);
            $this->assertArrayHasKey('practice_session_id', $exception->errors());
        }
    }

    public function test_the_precheck_failure_is_an_error_on_the_session_field_with_the_booking_message(): void
    {
        $user = $this->createPatient();
        $full = $this->createSession(['session_date' => '2026-10-06', 'quota' => 1, 'booked_count' => 1]);

        try {
            $this->validated($this->data($full), $user);
            $this->fail('Sesi penuh harus ditolak oleh precheck.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('booking.errors.QUOTA_FULL')], $exception->errors()['practice_session_id']);
            $this->assertSame(route('patient.appointments.create', ['sesi' => (string) $full->id]), $exception->redirectTo);
        }
    }

    public function test_the_precheck_is_skipped_while_other_fields_are_invalid(): void
    {
        $user = $this->createPatient();
        $full = $this->createSession(['session_date' => '2026-10-06', 'quota' => 1, 'booked_count' => 1]);

        try {
            $this->validated($this->data($full, ['payment_type' => 'bpjs', 'bpjs_number' => '1']), $user);
            $this->fail('Harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bpjs_number', $exception->errors());
            $this->assertArrayNotHasKey('practice_session_id', $exception->errors(), 'Galat per field didahulukan; precheck baru berjalan bila field lain valid.');
        }
    }

    // --- otorisasi -----------------------------------------------------------------------------

    public function test_authorization_requires_an_active_patient_with_an_active_profile(): void
    {
        $session = $this->openSession();

        foreach ([$this->createPatient([], ['is_active' => false]), $this->createStaff('registration-officer')] as $user) {
            try {
                $this->validated($this->data($session), $user);
                $this->fail('Harus ditolak oleh otorisasi.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
