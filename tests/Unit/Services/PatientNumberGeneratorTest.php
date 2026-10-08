<?php

namespace Tests\Unit\Services;

use App\Services\Patients\PatientNumberGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * F5-R10: nomor pasien = "P" + id profil dengan lebar minimal 6 digit (06/09/14). Murni, tanpa DB.
 */
class PatientNumberGeneratorTest extends TestCase
{
    /** @return array<string, array{0: int, 1: string}> */
    public static function formatCases(): array
    {
        return [
            'id 1' => [1, 'P000001'],
            'id 7' => [7, 'P000007'],
            'id 42' => [42, 'P000042'],
            'id 123' => [123, 'P000123'],
            'id terbesar enam digit' => [999999, 'P999999'],
            'id sejuta melebar menjadi tujuh digit' => [1000000, 'P1000000'],
            'id delapan digit' => [12345678, 'P12345678'],
        ];
    }

    #[DataProvider('formatCases')]
    public function test_format_pads_the_id_to_six_digits(int $id, string $expected): void
    {
        $this->assertSame($expected, PatientNumberGenerator::format($id));
    }

    public function test_numbers_below_one_million_are_always_seven_characters_and_sortable(): void
    {
        $previous = '';

        foreach ([1, 9, 10, 99, 100, 999, 1000, 99999, 100000, 999999] as $id) {
            $number = PatientNumberGenerator::format($id);

            $this->assertSame(7, strlen($number), "Panjang nomor untuk id {$id}");
            $this->assertMatchesRegularExpression('/^P\d{6}$/', $number);
            $this->assertTrue(strcmp($number, $previous) > 0, 'Urutan leksikografis mengikuti urutan id.');

            $previous = $number;
        }
    }

    public function test_the_longest_realistic_number_still_fits_the_ten_character_column(): void
    {
        // patient_profiles.patient_number = VARCHAR(10): "P" + sembilan digit.
        $this->assertSame(10, strlen(PatientNumberGenerator::format(999999999)));
    }
}
