<?php

namespace Tests\Unit\Services\Booking;

use App\Enums\PaymentType;
use App\Services\Booking\BookingData;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * F6-R13/F6-R21/F6-R22: pembawa data pendaftaran yang sudah tervalidasi (BPJS atau polis dipetakan ke satu
 * `paymentReference`, tanpa normalisasi: itu tugas Form Request). Murni, tanpa DB.
 */
class BookingDataTest extends TestCase
{
    public function test_constructor_defaults_are_empty(): void
    {
        $data = new BookingData(PaymentType::Umum);

        $this->assertSame(PaymentType::Umum, $data->paymentType);
        $this->assertNull($data->paymentReference);
        $this->assertNull($data->insurancePartnerId);
        $this->assertNull($data->complaint);
        $this->assertFalse($data->saveBpjs);
        $this->assertNull($data->paymentReferenceLast4());
    }

    public function test_properties_are_readonly(): void
    {
        $data = new BookingData(PaymentType::Bpjs, '0009900000011');

        $this->expectException(Error::class);

        $data->paymentReference = '0000000000000'; // @phpstan-ignore-line
    }

    public function test_from_validated_maps_a_bpjs_booking(): void
    {
        $data = BookingData::fromValidated([
            'practice_session_id' => 5,
            'payment_type' => 'bpjs',
            'bpjs_number' => '0009900000011',
            'complaint' => 'Batuk berdahak',
            'save_bpjs' => '1',
        ]);

        $this->assertSame(PaymentType::Bpjs, $data->paymentType);
        $this->assertSame('0009900000011', $data->paymentReference);
        $this->assertNull($data->insurancePartnerId);
        $this->assertSame('Batuk berdahak', $data->complaint);
        $this->assertTrue($data->saveBpjs);
        $this->assertSame('0011', $data->paymentReferenceLast4());
    }

    public function test_from_validated_maps_an_insurance_booking_with_the_policy_number(): void
    {
        $data = BookingData::fromValidated([
            'payment_type' => 'asuransi',
            'insurance_partner_id' => '7',
            'policy_number' => 'POL/2026-123456',
        ]);

        $this->assertSame(PaymentType::Asuransi, $data->paymentType);
        $this->assertSame('POL/2026-123456', $data->paymentReference);
        $this->assertSame(7, $data->insurancePartnerId);
        $this->assertSame('3456', $data->paymentReferenceLast4());
    }

    public function test_from_validated_accepts_an_enum_instance_and_integer_partner_id(): void
    {
        $data = BookingData::fromValidated(['payment_type' => PaymentType::Asuransi, 'insurance_partner_id' => 3]);

        $this->assertSame(PaymentType::Asuransi, $data->paymentType);
        $this->assertSame(3, $data->insurancePartnerId);
        $this->assertNull($data->paymentReference);
    }

    public function test_from_validated_maps_a_cash_booking_without_extras(): void
    {
        $data = BookingData::fromValidated(['payment_type' => 'umum']);

        $this->assertSame(PaymentType::Umum, $data->paymentType);
        $this->assertNull($data->paymentReference);
        $this->assertNull($data->insurancePartnerId);
        $this->assertNull($data->complaint);
        $this->assertFalse($data->saveBpjs);
    }

    public function test_from_validated_keeps_a_stray_reference_so_that_the_rules_can_reject_it(): void
    {
        // INVALID_PAYMENT (F.5 #8) untuk "umum dengan referensi" hanya dapat muncul bila nilainya tidak dibuang di sini.
        $data = BookingData::fromValidated(['payment_type' => 'umum', 'bpjs_number' => '0009900000011']);

        $this->assertSame('0009900000011', $data->paymentReference);
    }

    public function test_from_validated_prefers_the_bpjs_number_over_the_policy_number(): void
    {
        $data = BookingData::fromValidated([
            'payment_type' => 'bpjs',
            'bpjs_number' => '0009900000011',
            'policy_number' => 'POL-1',
        ]);

        $this->assertSame('0009900000011', $data->paymentReference);
    }

    /** @return array<string, array{0: mixed, 1: ?string}> */
    public static function textCases(): array
    {
        return [
            'teks biasa' => ['Nyeri dada', 'Nyeri dada'],
            'dipangkas' => ["  Nyeri dada \n", 'Nyeri dada'],
            'baris baru di dalam dipertahankan' => ["Nyeri dada\nSejak pagi", "Nyeri dada\nSejak pagi"],
            'kosong' => ['', null],
            'hanya spasi' => ["  \t ", null],
            'null' => [null, null],
            'larik diabaikan' => [['x'], null],
        ];
    }

    #[DataProvider('textCases')]
    public function test_from_validated_trims_the_complaint_and_turns_blank_into_null(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, BookingData::fromValidated(['payment_type' => 'umum', 'complaint' => $input])->complaint);
    }

    /** @return array<string, array{0: mixed, 1: ?int}> */
    public static function partnerIdCases(): array
    {
        return [
            'angka' => [7, 7],
            'teks angka' => ['7', 7],
            'kosong' => ['', null],
            'null' => [null, null],
            'nol' => [0, null],
            'teks nol' => ['0', null],
            'negatif' => [-3, null],
            'bukan angka' => ['abc', null],
            'larik' => [[1], null],
            'desimal' => ['7.5', null],
        ];
    }

    #[DataProvider('partnerIdCases')]
    public function test_from_validated_only_keeps_positive_integer_partner_ids(mixed $input, ?int $expected): void
    {
        $data = BookingData::fromValidated(['payment_type' => 'asuransi', 'insurance_partner_id' => $input]);

        $this->assertSame($expected, $data->insurancePartnerId);
    }

    /** @return array<string, array{0: mixed, 1: bool}> */
    public static function saveBpjsCases(): array
    {
        return [
            'teks 1' => ['1', true],
            'benar' => [true, true],
            'on' => ['on', true],
            'teks 0' => ['0', false],
            'salah' => [false, false],
            'null' => [null, false],
            'teks acak' => ['mungkin', false],
        ];
    }

    #[DataProvider('saveBpjsCases')]
    public function test_from_validated_casts_save_bpjs_to_a_boolean(mixed $input, bool $expected): void
    {
        $this->assertSame($expected, BookingData::fromValidated(['payment_type' => 'bpjs', 'save_bpjs' => $input])->saveBpjs);
    }

    public function test_from_validated_rejects_an_unknown_or_missing_payment_type(): void
    {
        $this->expectException(ValueError::class);

        BookingData::fromValidated(['payment_type' => 'tunai']);
    }

    public function test_from_validated_rejects_a_missing_payment_type(): void
    {
        $this->expectException(ValueError::class);

        BookingData::fromValidated([]);
    }

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function last4Cases(): array
    {
        return [
            'bpjs 13 digit' => ['0009900000011', '0011'],
            'polis campuran' => ['AB/12-3456', '3456'],
            'tepat empat karakter' => ['1234', '1234'],
            'lebih pendek dari empat' => ['123', '123'],
            'kosong' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('last4Cases')]
    public function test_payment_reference_last4_is_the_last_four_characters(?string $reference, ?string $expected): void
    {
        $this->assertSame($expected, (new BookingData(PaymentType::Bpjs, $reference))->paymentReferenceLast4());
    }
}
