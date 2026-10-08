<?php

namespace Tests\Unit\Services\Booking;

use App\Services\Booking\BookingCodeGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * F6-R5 dan BKG-17: kode booking `UPH-XXXX-XXXX` dari alfabet Crockford Base32 (tanpa I, L, O, U).
 * Murni, tanpa DB.
 */
class BookingCodeGeneratorTest extends TestCase
{
    public function test_constants_follow_the_blueprint(): void
    {
        $this->assertSame('UPH-', BookingCodeGenerator::PREFIX);
        $this->assertSame('0123456789ABCDEFGHJKMNPQRSTVWXYZ', BookingCodeGenerator::ALPHABET);
        $this->assertSame('/^UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', BookingCodeGenerator::PATTERN);
    }

    public function test_the_alphabet_has_thirty_two_unique_symbols_without_ambiguous_letters(): void
    {
        $symbols = str_split(BookingCodeGenerator::ALPHABET);

        $this->assertCount(32, $symbols);
        $this->assertCount(32, array_unique($symbols));

        foreach (['I', 'L', 'O', 'U'] as $ambiguous) {
            $this->assertNotContains($ambiguous, $symbols, "Huruf {$ambiguous} tidak boleh ada di alfabet.");
        }
    }

    public function test_the_pattern_character_class_matches_the_alphabet_for_every_ascii_character(): void
    {
        for ($byte = 0; $byte < 128; $byte++) {
            $character = chr($byte);

            $this->assertSame(
                str_contains(BookingCodeGenerator::ALPHABET, $character),
                preg_match('/^[0-9A-HJKMNP-TV-Z]$/D', $character) === 1,
                'Karakter ASCII '.$byte.' harus diperlakukan sama oleh ALPHABET dan PATTERN.',
            );
        }
    }

    public function test_generate_produces_valid_unique_codes_bkg_17(): void
    {
        $codes = [];

        for ($i = 0; $i < 1000; $i++) {
            $code = BookingCodeGenerator::generate();

            $this->assertSame(13, strlen($code));
            $this->assertMatchesRegularExpression(BookingCodeGenerator::PATTERN, $code);
            $this->assertTrue(BookingCodeGenerator::isValid($code));

            $codes[] = $code;
        }

        $this->assertCount(1000, array_unique($codes), '1000 kode harus unik (40 bit acak).');
    }

    public function test_generate_uses_every_symbol_of_the_alphabet(): void
    {
        $seen = [];

        for ($i = 0; $i < 3000; $i++) {
            foreach (str_split(str_replace(['UPH-', '-'], '', BookingCodeGenerator::generate())) as $symbol) {
                $seen[$symbol] = true;
            }
        }

        $this->assertEqualsCanonicalizing(str_split(BookingCodeGenerator::ALPHABET), array_keys($seen));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function validityCases(): array
    {
        return [
            'kode sah' => ['UPH-ABCD-EFGH', true],
            'kode sah dengan angka' => ['UPH-0123-4567', true],
            'kode sah dengan huruf akhir alfabet' => ['UPH-VWXY-Z9ZZ', true],
            'huruf kecil' => ['uph-abcd-efgh', false],
            'tanpa pemisah' => ['UPHABCDEFGH', false],
            'tanpa awalan' => ['ABCD-EFGH', false],
            'hanya satu tanda hubung' => ['UPH-ABCDEFGH', false],
            'terlalu pendek' => ['UPH-ABC-EFGH', false],
            'terlalu panjang' => ['UPH-ABCDE-EFGH', false],
            'huruf I dilarang' => ['UPH-ABCI-EFGH', false],
            'huruf L dilarang' => ['UPH-ABCD-EFGL', false],
            'huruf O dilarang' => ['UPH-OBCD-EFGH', false],
            'huruf U dilarang' => ['UPH-ABCD-UFGH', false],
            'spasi di depan' => [' UPH-ABCD-EFGH', false],
            'spasi di belakang' => ['UPH-ABCD-EFGH ', false],
            'newline di akhir (hazard $ PCRE)' => ["UPH-ABCD-EFGH\n", false],
            'null byte' => ["UPH-ABCD-EFG\0", false],
            'angka non-ASCII' => ["UPH-ABCD-EFG\u{0663}", false],
            'kosong' => ['', false],
        ];
    }

    #[DataProvider('validityCases')]
    public function test_is_valid_only_accepts_the_canonical_form(string $code, bool $expected): void
    {
        $this->assertSame($expected, BookingCodeGenerator::isValid($code));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function normalizeCases(): array
    {
        return [
            'sudah kanonik' => ['UPH-ABCD-EFGH', 'UPH-ABCD-EFGH'],
            'huruf kecil' => ['uph-abcd-efgh', 'UPH-ABCD-EFGH'],
            'spasi di tepi' => ['  UPH-ABCD-EFGH  ', 'UPH-ABCD-EFGH'],
            'newline di tepi' => ["UPH-ABCD-EFGH\n", 'UPH-ABCD-EFGH'],
            'spasi di dalam' => ['UPH-AB CD-EF GH', 'UPH-ABCD-EFGH'],
            'tab di dalam' => ["UPH\t-ABCD-EFGH", 'UPH-ABCD-EFGH'],
            'delapan karakter tanpa pemisah' => ['ABCDEFGH', 'UPH-ABCD-EFGH'],
            'delapan karakter berspasi huruf kecil' => ['abcd efgh', 'UPH-ABCD-EFGH'],
            'sebelas karakter dengan awalan tanpa pemisah' => ['UPHABCDEFGH', 'UPH-ABCD-EFGH'],
            'sebelas karakter huruf kecil' => ['uphabcdefgh', 'UPH-ABCD-EFGH'],
            'O menjadi 0 dan I atau L menjadi 1' => ['UPH-OILO-I1L1', 'UPH-0110-1111'],
            'delapan karakter dengan O I L' => ['OILOI1L1', 'UPH-0110-1111'],
            'kosong' => ['', ''],
            'terlalu pendek dibiarkan' => ['ABC', 'ABC'],
            'sembilan karakter dibiarkan' => ['ABCDEFGHJ', 'ABCDEFGHJ'],
            'sebagian pemisah tidak diperbaiki' => ['UPH-ABCDEFGH', 'UPH-ABCDEFGH'],
            'kelebihan karakter dibiarkan' => ['UPH-ABCD-EFGHJ', 'UPH-ABCD-EFGHJ'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function test_normalize_cleans_scanner_and_typing_noise(string $input, string $expected): void
    {
        $this->assertSame($expected, BookingCodeGenerator::normalize($input));
    }

    public function test_normalize_leaves_generated_codes_untouched_and_repairs_their_mangled_forms(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = BookingCodeGenerator::generate();

            $this->assertSame($code, BookingCodeGenerator::normalize($code));
            $this->assertSame($code, BookingCodeGenerator::normalize(strtolower($code)));
            $this->assertSame($code, BookingCodeGenerator::normalize(str_replace('-', '', $code)));
            $this->assertSame($code, BookingCodeGenerator::normalize(substr(str_replace('-', '', $code), 3)));
            $this->assertTrue(BookingCodeGenerator::isValid(BookingCodeGenerator::normalize(' '.$code."\n")));
        }
    }
}
