<?php

namespace Tests\Feature\Support;

use App\Models\Article;
use App\Models\Doctor;
use App\Models\Facility;
use App\Support\LikePattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

/**
 * F4B-R26 pada MariaDB sungguhan: pola `LikePattern::contains()` cocok secara harfiah, dan pemakai lamanya
 * (Article::search, Doctor::search) tetap berperilaku sama setelah refaktor.
 */
class LikePatternDatabaseTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithMasterData;
    use RefreshDatabase;

    /** @return list<string> */
    private function facilityNames(string $term): array
    {
        return Facility::query()
            ->where('name', 'like', LikePattern::contains($term))
            ->orderBy('id')
            ->pluck('name')
            ->all();
    }

    public function test_percent_underscore_and_backslash_match_literally(): void
    {
        foreach (['Gizi 50% Diskon', 'Gizi 50 Diskon', 'Gizi_x', 'Gizi-x', 'A\\B', 'AxB'] as $name) {
            $this->createFacility(['name' => $name]);
        }

        $this->assertSame(['Gizi 50% Diskon'], $this->facilityNames('50%'));
        $this->assertSame(['Gizi_x'], $this->facilityNames('i_x'));
        $this->assertSame(['A\\B'], $this->facilityNames('A\\B'));
        $this->assertSame(['Gizi 50% Diskon', 'Gizi 50 Diskon'], $this->facilityNames('50'));
        $this->assertSame([], $this->facilityNames('tidak ada'));
    }

    public function test_an_unescaped_pattern_would_have_matched_more_rows(): void
    {
        $this->createFacility(['name' => 'Gizi 50% Diskon']);
        $this->createFacility(['name' => 'Gizi 50 Diskon']);
        $this->createFacility(['name' => 'Gizi 500']);

        $unescaped = Facility::query()->where('name', 'like', '%50%%')->count();

        $this->assertSame(3, $unescaped, 'Tanpa escape, persen menjadi wildcard.');
        $this->assertCount(1, $this->facilityNames('50%'));
    }

    public function test_an_empty_term_matches_every_row(): void
    {
        $this->createFacility();
        $this->createFacility();

        $this->assertCount(2, Facility::query()->where('name', 'like', LikePattern::contains(''))->get());
    }

    public function test_article_search_still_treats_special_characters_as_text(): void
    {
        $match = $this->createArticle(['title' => 'Diskon 50% pemeriksaan']);
        $this->createArticle(['title' => 'Diskon 50 pemeriksaan']);
        $this->createArticle(['title' => 'Diskon 500 pemeriksaan']);
        $under = $this->createArticle(['title' => 'Cek_rutin tahunan']);
        $this->createArticle(['title' => 'Cek-rutin tahunan']);

        $this->assertSame([$match->id], Article::query()->search('50%')->pluck('id')->all());
        $this->assertSame([$under->id], Article::query()->search('k_r')->pluck('id')->all());
        $this->assertSame(5, Article::query()->search('   ')->count(), 'Kata kunci kosong tanpa efek.');
    }

    public function test_doctor_search_still_treats_special_characters_as_text(): void
    {
        $underscore = $this->createDoctor(['name' => 'dr. Ana_Budi']);
        $this->createDoctor(['name' => 'dr. AnaXBudi']);
        $percent = $this->createDoctor(['name' => 'dr. Cahya 100%']);
        $this->createDoctor(['name' => 'dr. Cahya 1000']);

        $this->assertSame([$underscore->id], Doctor::query()->search('a_B')->pluck('doctors.id')->all());
        $this->assertSame([$percent->id], Doctor::query()->search('100%')->pluck('doctors.id')->all());
    }
}
