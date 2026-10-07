<?php

namespace Tests\Feature\Facilities;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

/**
 * Model Facility (09 §5.2, F4B-R4, F4B-R5): scope tayang, slug otomatis, sanitasi `description`, fallback SEO.
 */
class FacilityModelTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_fillable_and_casts_follow_the_blueprint(): void
    {
        $this->assertSame(
            ['name', 'slug', 'category', 'short_description', 'description', 'is_active', 'sort_order', 'meta_title', 'meta_description'],
            (new Facility)->getFillable(),
        );

        $casts = (new Facility)->getCasts();

        $this->assertSame(FacilityCategory::class, $casts['category']);
        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('integer', $casts['sort_order']);
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        Facility::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_casts_and_database_defaults(): void
    {
        $created = Facility::query()->create(['name' => 'Apotek', 'category' => 'penunjang', 'short_description' => 'Layanan penyiapan obat.']);
        $facility = Facility::query()->findOrFail($created->id);

        $this->assertSame(FacilityCategory::Penunjang, $facility->category);
        $this->assertTrue($facility->is_active);
        $this->assertSame(0, $facility->sort_order);
        $this->assertNull($facility->description);
        $this->assertNull($facility->meta_title);
        $this->assertNull($facility->meta_description);
        $this->assertSame('apotek', $facility->slug);
    }

    public function test_factory_states(): void
    {
        $plain = $this->createFacility();
        $medis = Facility::factory()->category(FacilityCategory::Medis)->create();
        $inactive = Facility::factory()->inactive()->create();

        $this->assertSame(FacilityCategory::Umum, $plain->category);
        $this->assertTrue($plain->is_active);
        $this->assertSame(FacilityCategory::Medis, $medis->category);
        $this->assertFalse($inactive->is_active);
        $this->assertNotSame($plain->name, $medis->name);
    }

    public function test_active_and_ordered_scopes_sort_by_order_then_name_then_id(): void
    {
        $b = $this->createFacility(['name' => 'B Ruang', 'sort_order' => 1]);
        $a = $this->createFacility(['name' => 'A Ruang', 'sort_order' => 1]);
        $c = $this->createFacility(['name' => 'C Ruang', 'sort_order' => 0]);
        $off = Facility::factory()->inactive()->create(['name' => 'Z Ruang', 'sort_order' => 0]);
        $same1 = $this->createFacility(['name' => 'Sama', 'sort_order' => 2]);
        $same2 = $this->createFacility(['name' => 'Sama', 'sort_order' => 2]);

        $this->assertSame([$c->id, $off->id, $a->id, $b->id, $same1->id, $same2->id], Facility::query()->ordered()->pluck('id')->all());
        $this->assertSame([$c->id, $a->id, $b->id, $same1->id, $same2->id], Facility::query()->active()->ordered()->pluck('id')->all());
        $this->assertSame([$off->id], Facility::query()->where('is_active', false)->pluck('id')->all());
    }

    public function test_of_category_scope(): void
    {
        $medis = Facility::factory()->category(FacilityCategory::Medis)->create();
        $penunjangA = Facility::factory()->category(FacilityCategory::Penunjang)->create();
        $penunjangB = Facility::factory()->category(FacilityCategory::Penunjang)->create();
        $umum = Facility::factory()->category(FacilityCategory::Umum)->create();

        $this->assertSame([$medis->id], Facility::query()->ofCategory(FacilityCategory::Medis)->pluck('id')->all());
        $this->assertSame([$penunjangA->id, $penunjangB->id], Facility::query()->ofCategory(FacilityCategory::Penunjang)->orderBy('id')->pluck('id')->all());
        $this->assertSame([$umum->id], Facility::query()->ofCategory(FacilityCategory::Umum)->pluck('id')->all());
    }

    public function test_slug_is_derived_from_the_name_and_numbered_on_collision(): void
    {
        $first = $this->createFacility(['name' => 'Instalasi Gawat Darurat']);
        $second = $this->createFacility(['name' => 'Instalasi Gawat Darurat']);
        $third = $this->createFacility(['name' => 'Instalasi Gawat Darurat']);
        $explicit = $this->createFacility(['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium']);

        $this->assertSame('instalasi-gawat-darurat', $first->slug);
        $this->assertSame('instalasi-gawat-darurat-2', $second->slug);
        $this->assertSame('instalasi-gawat-darurat-3', $third->slug);
        $this->assertSame('fasilitas-laboratorium', $explicit->slug);
        $this->assertSame(170, strlen(Facility::uniqueSlug(str_repeat('a', 400))));
    }

    public function test_slug_does_not_change_when_the_name_is_edited(): void
    {
        $facility = $this->createFacility(['name' => 'Musala']);

        $facility->update(['name' => 'Musala dan Ruang Wudu']);

        $this->assertSame('Musala dan Ruang Wudu', $facility->fresh()->name);
        $this->assertSame('musala', $facility->fresh()->slug);
    }

    public function test_description_is_sanitized_on_save(): void
    {
        $facility = $this->createFacility([
            'description' => '<p>Ruang <strong>tunggu</strong> nyaman.</p><script>alert(1)</script><a href="javascript:alert(2)" onclick="x()">Klik</a>',
        ]);

        $stored = (string) $facility->fresh()->description;

        $this->assertStringContainsString('<p>Ruang <strong>tunggu</strong> nyaman.</p>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertSame(['description'], $facility->richTextAttributes());
    }

    public function test_blank_rich_text_is_stored_as_null(): void
    {
        foreach (['<p><br></p>', '<p>&nbsp;</p>', '', '   '] as $blank) {
            $facility = $this->createFacility(['description' => $blank]);

            $this->assertNull($facility->fresh()->description, 'Kosong visual: '.var_export($blank, true));
        }

        $filled = $this->createFacility(['description' => '<p>Isi.</p>']);
        $filled->update(['description' => '<p><br></p>']);

        $this->assertNull($filled->fresh()->description);
    }

    public function test_seo_title_falls_back_from_meta_title_to_name(): void
    {
        $this->assertSame('Judul SEO Khusus', $this->createFacility(['name' => 'Apotek', 'meta_title' => 'Judul SEO Khusus'])->seo_title);
        $this->assertSame('Apotek', $this->createFacility(['name' => 'Apotek', 'meta_title' => ''])->seo_title);
        $this->assertSame('Apotek', $this->createFacility(['name' => 'Apotek', 'meta_title' => null])->seo_title);
    }

    public function test_seo_description_falls_back_from_meta_description_to_short_description(): void
    {
        $this->assertSame('Deskripsi meta.', $this->createFacility(['short_description' => 'Ringkas.', 'meta_description' => 'Deskripsi meta.'])->seo_description);
        $this->assertSame('Ringkas.', $this->createFacility(['short_description' => 'Ringkas.', 'meta_description' => ''])->seo_description);
        $this->assertSame('Ringkas.', $this->createFacility(['short_description' => 'Ringkas.', 'meta_description' => null])->seo_description);
    }

    public function test_cover_url_uses_the_placeholder_without_a_cover_and_the_media_url_with_one(): void
    {
        $withCover = $this->createFacility(['name' => 'Dengan Cover']);
        $withoutCover = $this->createFacility(['name' => 'Tanpa Cover']);
        $this->createFacility(['name' => 'Pengisi']);

        $media = $this->createMediaRow([
            'model_type' => Facility::class,
            'model_id' => $withCover->id,
            'collection' => 'cover',
            'alt_text' => 'Foto contoh Dengan Cover',
        ]);

        $loaded = Facility::query()->with('media')->orderBy('id')->get();

        $this->assertSame($media->url, $loaded[0]->cover_url);
        $this->assertSame(asset('images/placeholders/facility.svg'), $loaded[1]->cover_url);
        $this->assertSame($withCover->id, $loaded[0]->id);
        $this->assertSame($withoutCover->id, $loaded[1]->id);
    }

    public function test_listing_without_eager_loading_media_violates_strict_mode(): void
    {
        $this->createFacility();
        $this->createFacility();
        $this->createFacility();

        $facilities = Facility::query()->orderBy('id')->get();

        $this->expectException(LazyLoadingViolationException::class);

        $facilities->first()->cover_url;
    }

    public function test_deleting_a_facility_removes_its_media_rows_and_files(): void
    {
        $facility = $this->createFacility();
        $media = app(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'cover.jpg'), 'cover', $facility, ['alt_text' => 'Foto contoh']);

        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);

        $facility->delete();

        $this->assertSame(0, Media::query()->count());
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }
}
