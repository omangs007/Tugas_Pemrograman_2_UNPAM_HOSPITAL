<?php

namespace Tests\Feature\Models;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class HasMediaTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->media = app(MediaService::class);
    }

    private function photo(Doctor $doctor, string $alt = 'Foto dokter'): Media
    {
        return $this->media->store(ImageFixtures::jpeg(800, 800), 'photo', $doctor, ['alt_text' => $alt]);
    }

    private function gallery(Clinic $clinic, string $alt): Media
    {
        return $this->media->store(ImageFixtures::jpeg(900, 600), 'gallery', $clinic, ['alt_text' => $alt]);
    }

    private function cover(Clinic $clinic): Media
    {
        return $this->media->store(ImageFixtures::jpeg(1200, 800), 'cover', $clinic, ['alt_text' => 'Cover']);
    }

    private function assertFilesExist(Media $media): void
    {
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    private function assertFilesMissing(Media $media): void
    {
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertMissing($media->thumb_path);
    }

    public function test_media_relation_is_a_morph_many_ordered_by_sort_order_then_id(): void
    {
        $clinic = $this->createClinic();
        $first = $this->gallery($clinic, 'Satu');
        $second = $this->gallery($clinic, 'Dua');
        $third = $this->gallery($clinic, 'Tiga');
        $this->media->reorder($clinic, 'gallery', [$third->id, $first->id, $second->id]);

        $this->assertInstanceOf(MorphMany::class, $clinic->media());
        $this->assertSame([$third->id, $first->id, $second->id], $clinic->media()->pluck('id')->all());

        $this->assertSame([$third->id, $first->id, $second->id], Clinic::query()->with('media')->find($clinic->id)->media->pluck('id')->all());
    }

    public function test_relation_uses_the_class_name_as_model_type_and_does_not_leak_across_owners(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor();
        $clinicCover = $this->cover($clinic);
        $doctorPhoto = $this->photo($doctor);

        $this->assertSame(Clinic::class, $clinicCover->model_type);
        $this->assertSame(Doctor::class, $doctorPhoto->model_type);

        // Id yang sama pada tipe berbeda tidak boleh tertukar: baris media dokter berpura-pura milik id klinik.
        DB::table('media')->where('id', $doctorPhoto->id)->update(['model_id' => $clinic->id]);

        $this->assertSame([$clinicCover->id], $clinic->media()->pluck('id')->all());
        $this->assertSame([$doctorPhoto->id], DB::table('media')->where('model_type', Doctor::class)->pluck('id')->all());
    }

    public function test_first_media_and_media_in_use_the_loaded_relation_without_queries(): void
    {
        $clinic = $this->createClinic();
        $cover = $this->cover($clinic);
        $galleryOne = $this->gallery($clinic, 'Satu');
        $galleryTwo = $this->gallery($clinic, 'Dua');

        $loaded = Clinic::query()->with('media')->findOrFail($clinic->id);

        DB::enableQueryLog();

        $this->assertTrue($loaded->firstMedia('cover')->is($cover));
        $this->assertSame([$galleryOne->id, $galleryTwo->id], $loaded->mediaIn('gallery')->pluck('id')->all());
        $this->assertNull($loaded->firstMedia('photo'));
        $this->assertCount(0, $loaded->mediaIn('photo'));
        $this->assertSame([], DB::getQueryLog(), 'Relasi media sudah dimuat: tidak boleh ada query tambahan.');

        DB::disableQueryLog();
    }

    public function test_first_media_and_media_in_query_directly_when_the_relation_is_not_loaded(): void
    {
        $clinic = $this->createClinic();
        $other = $this->createClinic();
        $this->cover($clinic);
        $this->gallery($clinic, 'Satu');
        $this->gallery($other, 'Milik lain');

        $fresh = Clinic::query()->findOrFail($clinic->id);

        $this->assertFalse($fresh->relationLoaded('media'));
        $this->assertSame('Cover', $fresh->firstMedia('cover')->alt_text);
        $this->assertSame(['Satu'], $fresh->mediaIn('gallery')->pluck('alt_text')->all());
        $this->assertFalse($fresh->relationLoaded('media'), 'Pembacaan langsung tidak memuat relasi.');
    }

    public function test_listing_several_owners_with_eager_loaded_media_is_safe_in_strict_mode(): void
    {
        $withPhoto = $this->createDoctor(['name' => 'Berfoto']);
        $this->photo($withPhoto);
        $this->createDoctor(['name' => 'Tanpa Foto']);
        $this->createDoctor(['name' => 'Tanpa Foto Juga']);

        $doctors = Doctor::query()->with('media')->orderBy('id')->get();

        DB::enableQueryLog();
        $urls = $doctors->map(fn (Doctor $doctor): string => $doctor->photo_url)->all();
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withPhoto->photo_url, $urls[0]);
        $this->assertSame(asset('images/placeholders/doctor.svg'), $urls[1]);
        $this->assertSame(asset('images/placeholders/doctor.svg'), $urls[2]);
    }

    public function test_listing_without_eager_loading_does_not_trigger_lazy_loading_violations_but_queries_per_row(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        DB::enableQueryLog();
        $doctors->each(fn (Doctor $doctor): string => $doctor->photo_url);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queries, 'Tanpa with(media), setiap baris melakukan satu query (bukan lazy loading).');
    }

    public function test_media_url_returns_full_and_thumb_urls_of_the_first_media(): void
    {
        $doctor = $this->createDoctor();
        $photo = $this->photo($doctor);
        $doctor = Doctor::query()->with('media')->findOrFail($doctor->id);

        $this->assertSame(Storage::disk('public')->url($photo->directory.'/'.$photo->file_name), $doctor->mediaUrl('photo'));
        $this->assertSame(Storage::disk('public')->url($photo->thumb_path), $doctor->mediaUrl('photo', 'thumb'));
        $this->assertSame($doctor->mediaUrl('photo'), $doctor->photo_url);
        $this->assertSame($doctor->mediaUrl('photo', 'thumb'), $doctor->photo_thumb_url);
        $this->assertNotSame($doctor->photo_url, $doctor->photo_thumb_url);
    }

    public function test_media_url_falls_back_to_the_given_or_generic_placeholder(): void
    {
        $doctor = Doctor::query()->with('media')->findOrFail($this->createDoctor()->id);
        $clinic = Clinic::query()->with('media')->findOrFail($this->createClinic()->id);

        $this->assertSame(asset('images/placeholders/doctor.svg'), $doctor->photo_url);
        $this->assertSame(asset('images/placeholders/doctor.svg'), $doctor->photo_thumb_url);
        $this->assertSame(asset('images/placeholders/clinic.svg'), $clinic->cover_url);
        $this->assertSame(asset('images/placeholders/facility.svg'), $clinic->mediaUrl('gallery', 'full', 'images/placeholders/facility.svg'));
        $this->assertSame(asset('images/placeholders/gallery.svg'), $clinic->mediaUrl('gallery'));
    }

    public function test_media_url_rejects_an_unknown_variant(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createClinic()->mediaUrl('cover', 'besar');
    }

    public function test_cover_url_uses_the_clinic_cover(): void
    {
        $clinic = $this->createClinic();
        $cover = $this->cover($clinic);
        $this->gallery($clinic, 'Galeri');

        $clinic = Clinic::query()->with('media')->findOrFail($clinic->id);

        $this->assertSame(Storage::disk('public')->url($cover->directory.'/'.$cover->file_name), $clinic->cover_url);
    }

    public function test_deleting_the_owner_removes_media_rows_and_files(): void
    {
        $clinic = $this->createClinic();
        $other = $this->createClinic();
        $cover = $this->cover($clinic);
        $gallery = $this->gallery($clinic, 'Galeri');
        $othersGallery = $this->gallery($other, 'Milik lain');

        $clinic->delete();

        $this->assertDatabaseMissing('clinics', ['id' => $clinic->id]);
        foreach ([$cover, $gallery] as $media) {
            $this->assertNull(Media::query()->find($media->id));
            $this->assertFilesMissing($media);
        }

        $this->assertNotNull(Media::query()->find($othersGallery->id));
        $this->assertFilesExist($othersGallery);
        $this->assertSame(2, AuditLog::query()->where('action', 'media.deleted')->count());
    }

    public function test_a_failed_owner_delete_keeps_rows_files_and_audit(): void
    {
        $clinic = $this->createClinic();
        $cover = $this->cover($clinic);
        $this->createDoctor([], $clinic);

        try {
            $clinic->delete();
            $this->fail('FK doctors.clinic_id RESTRICT seharusnya menolak penghapusan.');
        } catch (QueryException) {
            // diharapkan
        }

        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
        $this->assertNotNull(Media::query()->find($cover->id), 'Baris media ikut di-rollback.');
        $this->assertFilesExist($cover);
        $this->assertSame(0, AuditLog::query()->where('action', 'media.deleted')->count());
    }

    public function test_has_media_on_doctor_is_available_through_relation_loading_errors_in_strict_mode(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $doctors->each(fn (Doctor $doctor) => $doctor->media);
    }
}
