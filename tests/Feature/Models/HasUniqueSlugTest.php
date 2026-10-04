<?php

namespace Tests\Feature\Models;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

/**
 * Review Focus 1: nama yang menghasilkan slug bentrok atau aneh (F2-R5).
 */
class HasUniqueSlugTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> */
    public static function nameToSlug(): array
    {
        return [
            'nama biasa' => ['Andi Pratama', 'andi-pratama'],
            'huruf besar dan spasi ganda' => ['  RANI   Oktaviani ', 'rani-oktaviani'],
            'apostrof dan titik' => ["Siti Nur'aini", 'siti-nuraini'],
            'apostrof tipografis' => ['Siti Nur’aini', 'siti-nuraini'],
            'gelar tertulis di nama' => ["dr. Siti Nur'aini, Sp.A", 'dr-siti-nuraini-spa'],
            'huruf beraksen' => ['José Ñandú', 'jose-nandu'],
            'umlaut dan cincin' => ['Müller Åström', 'muller-astrom'],
            'ligatur' => ['Ünal ß Æther', 'unal-ss-aether'],
            'angka' => ['Poliklinik 24 Jam', 'poliklinik-24-jam'],
            'garis bawah dan simbol' => ['Ahmad_Fauzi & Putra', 'ahmad-fauzi-putra'],
            'bahasa Arab ditransliterasi' => ['أحمد', 'ahmd'],
        ];
    }

    #[DataProvider('nameToSlug')]
    public function test_doctor_slug_is_derived_from_the_name(string $name, string $expected): void
    {
        $doctor = Doctor::factory()->create(['name' => $name, 'slug' => null]);

        $this->assertSame($expected, $doctor->slug);
        $this->assertSame($expected, $doctor->fresh()->slug);
    }

    public function test_two_doctors_with_the_same_name_get_numbered_slugs(): void
    {
        $clinic = $this->createClinic();

        $first = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama']);
        $second = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama']);
        $third = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama']);
        $other = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama Putra']);

        $this->assertSame('andi-pratama', $first->slug);
        $this->assertSame('andi-pratama-2', $second->slug);
        $this->assertSame('andi-pratama-3', $third->slug);
        $this->assertSame('andi-pratama-putra', $other->slug);
    }

    public function test_a_literal_number_suffix_in_a_name_does_not_break_numbering(): void
    {
        $clinic = $this->createClinic();

        $a = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi 2']);
        $b = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi']);
        $c = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi']);
        $d = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi']);

        $this->assertSame('budi-2', $a->slug);
        $this->assertSame('budi', $b->slug);
        $this->assertSame('budi-3', $c->slug, '"budi-2" sudah dipakai oleh nama "Budi 2", jadi penomoran lanjut ke -3.');
        $this->assertSame('budi-4', $d->slug);
    }

    public function test_slug_collision_is_case_insensitive_like_the_unique_index(): void
    {
        $specialty = Specialty::factory()->create(['name' => 'Anak', 'slug' => 'ANAK']);
        $next = Specialty::factory()->create(['name' => 'Anak', 'slug' => null]);

        $this->assertSame('ANAK', $specialty->slug);
        $this->assertSame('anak-2', $next->slug);
    }

    public function test_names_without_usable_characters_get_a_non_empty_fallback_slug(): void
    {
        foreach (['東京', '!!!', '---', '   '] as $name) {
            $doctor = Doctor::factory()->create(['name' => $name, 'slug' => null]);

            $this->assertMatchesRegularExpression('/^doctor-[a-z0-9]{6}$/', $doctor->slug, "Nama {$name}");
        }

        $this->assertMatchesRegularExpression('/^specialty-[a-z0-9]{6}$/', Specialty::factory()->create(['name' => '東京', 'slug' => null])->slug);
        $this->assertMatchesRegularExpression('/^clinic-[a-z0-9]{6}$/', Clinic::factory()->create(['name' => '!!!', 'slug' => null])->slug);
    }

    public function test_slug_is_not_changed_when_the_name_is_edited(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Poliklinik Anak', 'slug' => null]);

        $clinic->update(['name' => 'Poliklinik Anak dan Remaja']);

        $this->assertSame('poliklinik-anak', $clinic->fresh()->slug);

        $doctor = Doctor::factory()->create(['name' => 'Laras Wulandari']);
        $original = $doctor->slug;
        $doctor->update(['name' => 'Laras Wulandari Putri']);

        $this->assertSame($original, $doctor->fresh()->slug);
    }

    public function test_an_explicit_slug_is_kept_and_a_blank_slug_is_generated(): void
    {
        $explicit = Specialty::factory()->create(['name' => 'Penyakit Dalam', 'slug' => 'internis']);
        $blank = Specialty::factory()->create(['name' => 'Penyakit Dalam', 'slug' => '']);
        $spaces = Specialty::factory()->create(['name' => 'Jantung', 'slug' => '  ']);

        $this->assertSame('internis', $explicit->slug);
        $this->assertSame('penyakit-dalam', $blank->slug);
        $this->assertSame('jantung', $spaces->slug);
    }

    public function test_slug_is_generated_for_models_created_without_the_factory(): void
    {
        $specialty = Specialty::create(['name' => 'Kulit dan Kelamin']);
        $clinic = Clinic::create([
            'name' => 'Poliklinik Kulit dan Kelamin', 'code' => 'kk', 'type' => 'poliklinik',
            'short_description' => 'Layanan kulit dan kelamin.', 'location' => 'Gedung B Lt. 2',
        ]);
        $doctor = Doctor::create(['clinic_id' => $clinic->id, 'name' => 'Citra Anindya Putri', 'gender' => 'P']);

        $this->assertSame('kulit-dan-kelamin', $specialty->slug);
        $this->assertSame('poliklinik-kulit-dan-kelamin', $clinic->slug);
        $this->assertSame('citra-anindya-putri', $doctor->slug);
    }

    public function test_slug_columns_are_limited_to_the_column_length_with_numbering_inside_the_limit(): void
    {
        foreach ([[Specialty::class, 120], [Clinic::class, 140], [Doctor::class, 170]] as [$class, $max]) {
            $slug = $class::uniqueSlug(str_repeat('a', 300));

            $this->assertSame($max, strlen($slug), "{$class}: dipotong tepat pada panjang kolom.");
        }

        // 100 huruf "Æ" (batas kolom nama) menjadi 200 karakter "ae" setelah transliterasi.
        $name = str_repeat('Æ', 100);

        $first = Specialty::factory()->create(['name' => $name]);
        $second = Specialty::factory()->create(['name' => $name]);
        $third = Specialty::factory()->create(['name' => $name]);

        $this->assertSame(str_repeat('ae', 60), $first->slug);
        $this->assertSame(str_repeat('ae', 59).'-2', $second->slug);
        $this->assertSame(str_repeat('ae', 59).'-3', $third->slug);
        $this->assertSame(120, strlen($second->slug));
    }

    public function test_truncation_never_leaves_a_dangling_hyphen_before_the_suffix(): void
    {
        // Pemotongan pada 120 karakter jatuh tepat pada tanda hubung ("a" x 119, "-", "bb").
        $source = str_repeat('a', 119).' bb';

        $first = Specialty::uniqueSlug($source);
        Specialty::factory()->create(['name' => 'Lain', 'slug' => $first]);
        $second = Specialty::uniqueSlug($source);

        $this->assertSame(str_repeat('a', 119), $first);
        $this->assertSame(str_repeat('a', 118).'-2', $second);
    }

    public function test_unique_slug_can_ignore_the_row_itself(): void
    {
        $specialty = Specialty::factory()->create(['name' => 'Anak', 'slug' => null]);

        $this->assertSame('anak-2', Specialty::uniqueSlug('Anak'));
        $this->assertSame('anak', Specialty::uniqueSlug('Anak', $specialty->id));
    }

    public function test_slugs_are_unique_per_table_not_across_models(): void
    {
        $specialty = Specialty::factory()->create(['name' => 'Umum', 'slug' => null]);
        $clinic = Clinic::factory()->create(['name' => 'Umum', 'slug' => null]);

        $this->assertSame('umum', $specialty->slug);
        $this->assertSame('umum', $clinic->slug);
    }

    public function test_the_unique_index_is_still_the_last_line_of_defence(): void
    {
        Specialty::factory()->create(['name' => 'Anak', 'slug' => 'anak']);

        $this->expectException(QueryException::class);

        Specialty::factory()->create(['name' => 'Lain', 'slug' => 'anak']);
    }

    public function test_factories_no_longer_define_a_slug_so_duplicate_names_are_numbered(): void
    {
        $specialties = Specialty::factory()->count(3)->sequence(['name' => 'Saraf'])->create();

        $this->assertSame(['saraf', 'saraf-2', 'saraf-3'], $specialties->pluck('slug')->all());
    }

    public function test_slug_max_length_is_defined_per_model_from_the_data_dictionary(): void
    {
        $lengths = [];

        foreach ([Specialty::class, Clinic::class, Doctor::class] as $class) {
            $method = new ReflectionMethod($class, 'slugMaxLength');
            $lengths[$class] = $method->invoke(new $class);
        }

        $this->assertSame([Specialty::class => 120, Clinic::class => 140, Doctor::class => 170], $lengths);
    }
}
