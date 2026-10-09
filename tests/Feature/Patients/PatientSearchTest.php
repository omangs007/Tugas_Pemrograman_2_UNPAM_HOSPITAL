<?php

namespace Tests\Feature\Patients;

use App\Models\PatientProfile;
use App\Services\Patients\PatientSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F7A-R16 (empat jenis istilah, urutan pengenalan), BR-102 (`similarTo`), WLK-02 sisi pencarian. Karakter `%`, `_`, `\`
 * diperlakukan sebagai teks (LikePattern). Tidak ada Form Request di sini (T5).
 */
class PatientSearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(): PatientSearch
    {
        return $this->app->make(PatientSearch::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function profile(string $name, array $attributes = []): PatientProfile
    {
        return PatientProfile::factory()->create($attributes + ['full_name' => $name]);
    }

    /**
     * Nama lengkap hasil pencarian, urut id.
     *
     * @return list<string>
     */
    private function names(string $term): array
    {
        return $this->search()->apply(PatientProfile::query(), $term)->orderBy('id')->pluck('full_name')->all();
    }

    // --- istilah kosong ---

    public function test_an_empty_or_blank_term_leaves_the_query_untouched(): void
    {
        $this->profile('Ani Wulandari');
        $this->profile('Budi Hartono');

        foreach (['', '   ', "\t\n"] as $term) {
            $query = PatientProfile::query();
            $result = $this->search()->apply($query, $term);

            $this->assertSame($query, $result);
            $this->assertSame(PatientProfile::query()->toSql(), $result->toSql(), 'Istilah kosong tidak menambah kondisi.');
            $this->assertSame(2, $result->count());
        }
    }

    // --- (1) nomor pasien ---

    public function test_a_patient_number_term_is_recognised_in_all_its_forms(): void
    {
        $this->profile('Ani Wulandari', ['patient_number' => 'P000012']);
        $this->profile('Budi Hartono', ['patient_number' => 'P000123']);
        $this->profile('Citra Dewi', ['patient_number' => 'P123456']);
        $this->profile('Dedi Kurnia', ['patient_number' => 'P1234567']);

        $this->assertSame(['Ani Wulandari'], $this->names('P000012'));
        $this->assertSame(['Ani Wulandari'], $this->names('p000012'));
        $this->assertSame(['Ani Wulandari'], $this->names('p12'));
        $this->assertSame(['Ani Wulandari'], $this->names('12'));
        $this->assertSame(['Ani Wulandari'], $this->names('000012'));
        $this->assertSame(['Budi Hartono'], $this->names('  123  '));
        $this->assertSame(['Citra Dewi'], $this->names('123456'));
        $this->assertSame(['Dedi Kurnia'], $this->names('P1234567'));
    }

    public function test_seven_digits_without_the_p_prefix_are_not_a_patient_number(): void
    {
        $this->profile('Dedi Kurnia', ['patient_number' => 'P1234567']);

        $this->assertSame([], $this->names('1234567'), 'Tujuh digit tanpa P jatuh ke pencarian nama.');
        $this->assertSame([], $this->names('P12345678'), 'Delapan digit bukan nomor pasien dan bukan nama.');
    }

    public function test_a_short_digit_string_is_a_patient_number_and_never_a_phone_prefix(): void
    {
        $this->profile('Gita Lestari', ['phone' => '081234567890', 'patient_number' => 'P000050']);

        $this->assertSame([], $this->names('08123'), 'Lima digit dibaca sebagai nomor P008123.');
    }

    // --- (2) tanggal lahir ---

    public function test_a_birth_date_term_in_day_month_year_matches_the_exact_date(): void
    {
        $this->profile('Eka Putri', ['birth_date' => '1985-07-21']);
        $this->profile('Fajar Nugroho', ['birth_date' => '1985-07-22']);

        $this->assertSame(['Eka Putri'], $this->names('21/07/1985'));
        $this->assertSame(['Fajar Nugroho'], $this->names(' 22/07/1985 '));
        $this->assertSame([], $this->names('23/07/1985'));
    }

    public function test_an_impossible_or_differently_formatted_date_falls_back_to_the_name_search(): void
    {
        $this->profile('Eka Putri', ['birth_date' => '1985-03-03']);

        $this->assertSame([], $this->names('31/02/1985'), 'Tanggal tidak sah tidak digulirkan menjadi 03/03/1985.');
        $this->assertSame([], $this->names('1985-03-03'));
        $this->assertSame([], $this->names('3/3/1985'));
    }

    // --- (3) telepon ---

    public function test_a_valid_phone_term_matches_by_normalised_prefix(): void
    {
        $this->profile('Gita Lestari', ['phone' => '081234567890']);
        $this->profile('Hana Putri', ['phone' => '0812345678901']);
        $this->profile('Indra Wijaya', ['phone' => '081999999999']);
        $this->profile('Joko Susilo', ['phone' => '+6281234567890']);

        $this->assertSame(['Gita Lestari', 'Hana Putri'], $this->names('081234567890'));
        $this->assertSame(['Gita Lestari', 'Hana Putri'], $this->names('0812-3456-7890'));
        $this->assertSame(['Gita Lestari', 'Hana Putri'], $this->names('(0812) 3456 7890'));
        $this->assertSame(['Joko Susilo'], $this->names('+62 812-3456-7890'));
        $this->assertSame(['Indra Wijaya'], $this->names('081999999999'));
        $this->assertSame([], $this->names('081555555555'));
    }

    // --- (4) nama ---

    public function test_a_name_term_matches_a_substring_case_insensitively(): void
    {
        $this->profile('Budi Santoso');
        $this->profile('Ahmad Budiman');
        $this->profile('Citra Dewi');

        $this->assertSame(['Budi Santoso', 'Ahmad Budiman'], $this->names('budi'));
        $this->assertSame(['Citra Dewi'], $this->names('DEWI'));
        $this->assertSame(['Budi Santoso'], $this->names('  santoso '));
        $this->assertSame([], $this->names('tidak ada'));
    }

    public function test_like_wildcards_in_the_term_are_literal_text(): void
    {
        $this->profile('Ani Wulandari');
        $this->profile('A_i Uji');
        $this->profile('100% Sehat');
        $this->profile('Bu\\di Uji');

        $this->assertSame(['A_i Uji'], $this->names('A_i'), 'Garis bawah bukan wildcard satu karakter.');
        $this->assertSame(['A_i Uji'], $this->names('_'));
        $this->assertSame(['100% Sehat'], $this->names('%'));
        $this->assertSame(['100% Sehat'], $this->names('100%'));
        $this->assertSame([], $this->names('A%i'), 'Persen bukan wildcard.');
        $this->assertSame(['Bu\\di Uji'], $this->names('\\'));
    }

    public function test_markup_in_the_term_is_just_text(): void
    {
        $this->profile('Ani Wulandari');

        $this->assertSame([], $this->names('<script>alert(1)</script>'));
        $this->assertSame([], $this->names("' OR 1=1 --"));
    }

    public function test_the_term_is_cut_at_one_hundred_characters(): void
    {
        $this->profile(str_repeat('a', 100).'yyy');

        $this->assertCount(1, $this->names(str_repeat('a', 100).'zzz'), 'Sisa istilah setelah 100 karakter dibuang.');
    }

    public function test_the_term_is_scrubbed_of_invalid_utf8(): void
    {
        $this->profile('Ani Wulandari');

        // Urutan UTF-8 rusak dibersihkan (mb_scrub), bukan menjadi galat basis data; hasilnya hanya daftar.
        $this->assertIsArray($this->names("Ani\xC3\x28"));
        $this->assertSame(['Ani Wulandari'], $this->names('Ani'));
    }

    // --- komposisi ---

    public function test_it_composes_with_other_constraints_of_the_query(): void
    {
        $this->profile('Budi Aktif');
        $this->profile('Budi Nonaktif', ['is_active' => false]);

        $result = $this->search()->apply(PatientProfile::query()->where('is_active', true), 'budi');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame(['Budi Aktif'], $result->pluck('full_name')->all());
    }

    // --- similarTo (BR-102) ---

    public function test_similar_matches_the_same_name_and_birth_date_ignoring_case_and_spaces(): void
    {
        $match = $this->profile('Budi Santoso', ['birth_date' => '1985-07-21', 'phone' => '081111111111']);
        $this->profile('Budi Santoso', ['birth_date' => '1985-07-22', 'phone' => '081222222222']);
        $this->profile('Budi Hartono', ['birth_date' => '1985-07-21', 'phone' => '081333333333']);

        $result = $this->search()->similarTo('  budi SANTOSO ', '1985-07-21', '081999999999');

        $this->assertSame([$match->id], $result->modelKeys());
    }

    public function test_similar_matches_the_same_normalised_phone_regardless_of_name_and_date(): void
    {
        $match = $this->profile('Orang Lain', ['birth_date' => '1970-01-01', 'phone' => '081234567890']);
        $this->profile('Tidak Mirip', ['birth_date' => '1970-01-01', 'phone' => '081234567891']);

        $result = $this->search()->similarTo('Budi Santoso', '1985-07-21', '0812-3456-7890');

        $this->assertSame([$match->id], $result->modelKeys());
    }

    public function test_similar_returns_active_profiles_only(): void
    {
        $this->profile('Budi Santoso', ['birth_date' => '1985-07-21', 'phone' => '081234567890', 'is_active' => false]);

        $this->assertCount(0, $this->search()->similarTo('Budi Santoso', '1985-07-21', '081234567890'));
    }

    public function test_similar_excludes_the_ignored_profile(): void
    {
        $self = $this->profile('Budi Santoso', ['birth_date' => '1985-07-21', 'phone' => '081234567890']);
        $twin = $this->profile('Budi Santoso', ['birth_date' => '1985-07-21', 'phone' => '081555555555']);

        $result = $this->search()->similarTo('Budi Santoso', '1985-07-21', '081234567890', $self->id);

        $this->assertSame([$twin->id], $result->modelKeys());
    }

    public function test_similar_returns_at_most_five_profiles_ordered_by_id(): void
    {
        $ids = [];

        for ($i = 0; $i < 7; $i++) {
            $ids[] = $this->profile("Kembar {$i}", ['phone' => '081234567890'])->id;
        }

        $result = $this->search()->similarTo('Siapa Saja', '1990-01-01', '081234567890');

        $this->assertSame(array_slice($ids, 0, 5), $result->modelKeys());
    }

    public function test_similar_includes_profiles_with_and_without_an_account(): void
    {
        $walkIn = $this->profile('Budi Santoso', ['birth_date' => '1985-07-21', 'phone' => '081111111111']);
        $online = PatientProfile::factory()->withAccount()->create(['full_name' => 'Budi Santoso', 'birth_date' => '1985-07-21', 'phone' => '081222222222']);

        $result = $this->search()->similarTo('Budi Santoso', '1985-07-21', '081999999999');

        $this->assertSame([$walkIn->id, $online->id], $result->modelKeys());
    }

    public function test_similar_never_returns_everyone_when_the_inputs_are_blank(): void
    {
        $this->profile('Ani Wulandari');
        $this->profile('Budi Hartono');

        $this->assertCount(0, $this->search()->similarTo('  ', '1985-07-21', ''));
        $this->assertCount(0, $this->search()->similarTo('Ani Wulandari', '', ''));
        $this->assertCount(0, $this->search()->similarTo('', '', ''));
    }
}
