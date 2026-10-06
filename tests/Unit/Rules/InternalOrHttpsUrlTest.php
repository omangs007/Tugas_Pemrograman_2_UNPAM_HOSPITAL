<?php

namespace Tests\Unit\Rules;

use App\Rules\InternalOrHttpsUrl;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BR-124 / 12 §3.1: tautan tombol banner hanya path internal (diawali satu /) atau URL https.
 */
class InternalOrHttpsUrlTest extends TestCase
{
    private const MESSAGE = 'Tautan harus berupa alamat internal (diawali /) atau URL https.';

    /** @return array<string, array{0: mixed}> */
    public static function acceptedValues(): array
    {
        return [
            'path internal' => ['/dokter'],
            'path internal dengan query' => ['/jadwal-dokter?poli=x'],
            'path internal dengan fragmen' => ['/layanan/poliklinik-anak#jadwal'],
            'beranda' => ['/'],
            'path dengan segmen berparameter' => ['/artikel/judul-artikel-contoh'],
            'https' => ['https://contoh.test/a'],
            'https tanpa path' => ['https://contoh.test'],
            'https dengan query' => ['https://contoh.test/a?b=1&c=2'],
        ];
    }

    /** @return array<string, array{0: mixed}> */
    public static function rejectedValues(): array
    {
        return [
            'protokol relatif' => ['//evil.test'],
            'protokol relatif dengan path' => ['//evil.test/dokter'],
            'backslash setelah slash' => ['/\\evil.test'],
            'spasi di dalam path' => ['/dokter dua'],
            'baris baru di path' => ["/dokter\n/x"],
            'diawali spasi' => [' /dokter'],
            'http biasa' => ['http://x.test'],
            'javascript' => ['javascript:alert(1)'],
            'javascript berhuruf besar' => ['JavaScript:alert(1)'],
            'data uri' => ['data:text/html,x'],
            'tanpa slash' => ['dokter'],
            'https tanpa host' => ['https://'],
            'https dengan spasi pada host' => ['https://exa mple.test'],
            'ftp' => ['ftp://x.test'],
            'angka' => [123],
            'larik' => [['/dokter']],
            'boolean' => [true],
        ];
    }

    #[DataProvider('acceptedValues')]
    public function test_accepts_internal_paths_and_https_urls(mixed $value): void
    {
        $validator = Validator::make(['button_url' => $value], ['button_url' => [new InternalOrHttpsUrl]]);

        $this->assertTrue($validator->passes(), 'Seharusnya lolos: '.json_encode($value));
    }

    #[DataProvider('rejectedValues')]
    public function test_rejects_everything_else_with_the_standard_message(mixed $value): void
    {
        $validator = Validator::make(['button_url' => $value], ['button_url' => [new InternalOrHttpsUrl]]);

        $this->assertTrue($validator->fails(), 'Seharusnya ditolak: '.json_encode($value));
        $this->assertSame(self::MESSAGE, $validator->errors()->first('button_url'));
    }

    public function test_an_empty_value_is_left_to_nullable_and_required(): void
    {
        $nullable = Validator::make(['button_url' => ''], ['button_url' => ['nullable', new InternalOrHttpsUrl]]);
        $null = Validator::make(['button_url' => null], ['button_url' => ['nullable', new InternalOrHttpsUrl]]);
        $required = Validator::make(['button_url' => ''], ['button_url' => ['required', new InternalOrHttpsUrl]]);

        $this->assertTrue($nullable->passes());
        $this->assertTrue($null->passes());
        $this->assertTrue($required->fails());
        $this->assertSame([], array_filter(
            $required->errors()->get('button_url'),
            fn (string $message): bool => $message === self::MESSAGE,
        ), 'Nilai kosong ditolak oleh required, bukan oleh aturan ini.');
    }

    public function test_the_message_does_not_depend_on_the_attribute_label(): void
    {
        $validator = Validator::make(
            ['link' => 'dokter'],
            ['link' => [new InternalOrHttpsUrl]],
            [],
            ['link' => 'Tautan tombol'],
        );

        $this->assertSame(self::MESSAGE, $validator->errors()->first('link'));
    }
}
