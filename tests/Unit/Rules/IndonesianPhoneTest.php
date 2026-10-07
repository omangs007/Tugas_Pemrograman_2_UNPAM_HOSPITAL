<?php

namespace Tests\Unit\Rules;

use App\Rules\IndonesianPhone;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Nomor seluler Indonesia (F4B-R34, 12 Asumsi 1): spasi, tanda hubung, titik, dan kurung dibuang lebih dulu; hasilnya
 * harus cocok `^(\+62|62|0)8[0-9]{7,12}$`. Nilai kosong diserahkan ke `nullable`/`required`.
 */
class IndonesianPhoneTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool}> */
    public static function numbers(): array
    {
        return [
            'format nol dasar' => ['081234567890', true],
            'dengan tanda hubung' => ['0812-3456-7890', true],
            'format plus enam dua' => ['+62 812 3456 7890', true],
            'format enam dua tanpa plus' => ['6281234567890', true],
            'dengan kurung dan titik' => ['(0812) 3456.7890', true],
            'terpendek (tujuh digit sesudah 8)' => ['08'.'1234567', true],
            'satu digit lebih pendek ditolak' => ['08'.'123456', false],
            'terpanjang (dua belas digit sesudah 8)' => ['08'.'123456789012', true],
            'telepon rumah ditolak' => ['021555', false],
            'telepon rumah panjang ditolak' => ['02155512345', false],
            'terlalu pendek' => ['08123', false],
            'terlalu panjang' => ['08'.'1234567890123', false],
            'huruf ditolak' => ['0812abc4567', false],
            'kode negara lain ditolak' => ['+1 555 123 4567', false],
            'bukan seluler (tujuh)' => ['07123456789', false],
            'plus tanpa enam dua' => ['+812345678901', false],
            'tag ditolak' => ['<b>0812345678</b>', false],
        ];
    }

    #[DataProvider('numbers')]
    public function test_validation(string $value, bool $passes): void
    {
        $validator = Validator::make(['phone' => $value], ['phone' => [new IndonesianPhone]]);

        $this->assertSame($passes, $validator->passes(), $value);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function normalized(): array
    {
        return [
            'tanda hubung' => ['0812-3456-7890', '081234567890'],
            'spasi dan plus' => ['+62 812 3456 7890', '+6281234567890'],
            'kurung dan titik' => ['(0812) 3456.7890', '081234567890'],
            'sudah bersih' => ['6281234567890', '6281234567890'],
            'banyak pemisah' => [' 0812 - 3456 . 7890 ', '081234567890'],
        ];
    }

    #[DataProvider('normalized')]
    public function test_normalize_strips_separators_only(string $input, string $expected): void
    {
        $this->assertSame($expected, IndonesianPhone::normalize($input));
    }

    public function test_the_longest_valid_number_fits_the_varchar_20_column(): void
    {
        $longest = IndonesianPhone::normalize('+62 8'.'123456789012');

        $this->assertSame(16, strlen($longest));
        $this->assertLessThanOrEqual(20, strlen($longest));
        $this->assertTrue(Validator::make(['phone' => $longest], ['phone' => [new IndonesianPhone]])->passes());
    }

    public function test_the_message_is_the_exact_indonesian_text(): void
    {
        $validator = Validator::make(['phone' => '021555'], ['phone' => [new IndonesianPhone]]);

        $this->assertSame(
            'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.',
            $validator->errors()->first('phone'),
        );
        $this->assertSame($validator->errors()->first('phone'), IndonesianPhone::MESSAGE);
    }

    public function test_non_string_values_fail_without_an_error(): void
    {
        foreach ([['0812345678'], 8123456789, 8.5, true] as $value) {
            $validator = Validator::make(['phone' => $value], ['phone' => [new IndonesianPhone]]);

            $this->assertTrue($validator->fails(), var_export($value, true));
            $this->assertSame(IndonesianPhone::MESSAGE, $validator->errors()->first('phone'));
        }
    }

    public function test_empty_values_are_left_to_nullable_and_required(): void
    {
        $this->assertTrue(Validator::make(['phone' => ''], ['phone' => ['nullable', new IndonesianPhone]])->passes());
        $this->assertTrue(Validator::make(['phone' => null], ['phone' => ['nullable', new IndonesianPhone]])->passes());
        $this->assertTrue(Validator::make([], ['phone' => ['nullable', new IndonesianPhone]])->passes());
        $this->assertFalse(Validator::make(['phone' => ''], ['phone' => ['required', new IndonesianPhone]])->passes());
    }

    public function test_the_rule_is_a_final_strict_class(): void
    {
        $this->assertTrue((new \ReflectionClass(IndonesianPhone::class))->isFinal());
        $this->assertStringContainsString('declare(strict_types=1);', (string) file_get_contents(app_path('Rules/IndonesianPhone.php')));
    }
}
