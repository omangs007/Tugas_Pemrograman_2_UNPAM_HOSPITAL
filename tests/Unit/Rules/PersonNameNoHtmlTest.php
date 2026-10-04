<?php

namespace Tests\Unit\Rules;

use App\Rules\NoHtml;
use App\Rules\PersonName;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonNameNoHtmlTest extends TestCase
{
    /** @return array<string, array{0: mixed, 1: bool}> */
    public static function personNames(): array
    {
        return [
            'nama biasa' => ['Andi Pratama', true],
            'dengan titik' => ['dr. Siti', true],
            'apostrof lurus' => ["Siti Nur'aini", true],
            'apostrof tipografis' => ['Siti Nur’aini', true],
            'tanda hubung' => ['Nur-Aini', true],
            'huruf beraksen' => ['José Ñandú', true],
            'huruf non-Latin' => ['أحمد', true],
            'angka ditolak' => ['Andi 2', false],
            'koma ditolak' => ['Sp.A, dr', false],
            'tag ditolak' => ['<b>Andi</b>', false],
            'simbol ditolak' => ['Andi@Pratama', false],
            'garis bawah ditolak' => ['Andi_Pratama', false],
            'bukan string' => [['Andi'], false],
            'angka murni' => [12345, false],
        ];
    }

    #[DataProvider('personNames')]
    public function test_person_name(mixed $value, bool $passes): void
    {
        $validator = Validator::make(['name' => $value], ['name' => [new PersonName]]);

        $this->assertSame($passes, $validator->passes());
    }

    public function test_person_name_message_uses_the_attribute_label(): void
    {
        $validator = Validator::make(['name' => 'Andi 2'], ['name' => [new PersonName]], [], ['name' => 'Nama dokter']);

        $this->assertSame(
            'Nama dokter hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            $validator->errors()->first('name'),
        );
    }

    public function test_empty_values_are_left_to_required(): void
    {
        $this->assertTrue(Validator::make(['name' => ''], ['name' => [new PersonName]])->passes());
        $this->assertTrue(Validator::make([], ['name' => [new PersonName]])->passes());
        $this->assertFalse(Validator::make(['name' => ''], ['name' => ['required', new PersonName]])->passes());
    }

    /** @return array<string, array{0: mixed, 1: bool}> */
    public static function htmlValues(): array
    {
        return [
            'teks biasa' => ['Layanan kesehatan anak.', true],
            'perbandingan angka' => ['tekanan <90 dan >100', true],
            'kurang dari lalu huruf dan lebih dari (mirip tag)' => ['a < b dan c > d', false],
            'kurang dari diikuti angka' => ['nilai <5 dan >2', true],
            'tag paragraf' => ['<p>Halo</p>', false],
            'tag penutup' => ['teks </div>', false],
            'tag dengan spasi' => ['< script >alert(1)', false],
            'tag dengan atribut' => ['<img src=x onerror=alert(1)>', false],
            'komentar atau doctype' => ['<!-- komentar -->', false],
            'tag huruf besar' => ['<B>tebal</B>', false],
            'tag bukan string' => [['<b>'], false],
        ];
    }

    #[DataProvider('htmlValues')]
    public function test_no_html(mixed $value, bool $passes): void
    {
        $validator = Validator::make(['bio' => $value], ['bio' => [new NoHtml]]);

        $this->assertSame($passes, $validator->passes());
    }

    public function test_no_html_message_uses_the_attribute_label(): void
    {
        $validator = Validator::make(['bio' => '<b>x</b>'], ['bio' => [new NoHtml]], [], ['bio' => 'Profil lengkap']);

        $this->assertSame('Profil lengkap tidak boleh mengandung tag HTML.', $validator->errors()->first('bio'));
    }

    public function test_the_regex_constants_match_the_spec(): void
    {
        $this->assertSame('/^[\p{L}\s.\'’-]+$/u', PersonName::PATTERN);
        $this->assertSame('/<\s*\/?\s*[a-zA-Z!][^>]*>/', NoHtml::PATTERN);
    }
}
