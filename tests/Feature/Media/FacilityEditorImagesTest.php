<?php

namespace Tests\Feature\Media;

use App\Models\Facility;
use App\Models\Media;
use App\Models\RoomCategory;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\TestCase;

/**
 * D-55 / SEK-08 / MED-07: gambar editor `facilities.description` dan `room_categories.description` terikat ke
 * pemilik saat disimpan, dilepas bila tidak dirujuk lagi, dan `media:prune` tidak menghapus yang masih dipakai.
 */
class FacilityEditorImagesTest extends TestCase
{
    use InteractsWithFacilities;
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->service = app(MediaService::class);
    }

    private function editorImage(): Media
    {
        return $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, ['alt_text' => 'Gambar editor']);
    }

    private function html(Media ...$images): string
    {
        $imgs = array_map(fn (Media $media): string => '<img src="/storage/'.$media->directory.'/'.$media->file_name.'" alt="x">', $images);

        return '<p>Deskripsi yang cukup panjang '.implode(' ', $imgs).'</p>';
    }

    private function age(Media $media, int $hours): void
    {
        $at = now()->subHours($hours);
        DB::table('media')->where('id', $media->id)->update(['created_at' => $at, 'updated_at' => $at]);
    }

    private function assertFilesExist(Media $media): void
    {
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
    }

    private function assertFilesMissing(Media $media): void
    {
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
    }

    public function test_the_reference_columns_include_facilities_and_room_categories(): void
    {
        $this->assertSame(['description'], MediaService::EDITOR_REFERENCE_COLUMNS['facilities']);
        $this->assertSame(['description'], MediaService::EDITOR_REFERENCE_COLUMNS['room_categories']);
    }

    public function test_saving_a_facility_binds_the_referenced_editor_image_to_it(): void
    {
        $image = $this->editorImage();
        $other = $this->editorImage();

        $facility = $this->createFacility(['description' => $this->html($image)]);

        $image->refresh();
        $this->assertSame(Facility::class, $image->model_type);
        $this->assertSame($facility->id, $image->model_id);
        $this->assertNull($other->fresh()->model_id);
        $this->assertCount(1, Facility::query()->with('media')->findOrFail($facility->id)->mediaIn('editor'));
    }

    public function test_saving_a_room_category_binds_the_referenced_editor_image_to_it(): void
    {
        $image = $this->editorImage();

        $room = $this->createRoomCategory(['description' => $this->html($image)]);

        $image->refresh();
        $this->assertSame(RoomCategory::class, $image->model_type);
        $this->assertSame($room->id, $image->model_id);
    }

    public function test_an_image_owned_by_a_facility_is_not_taken_over_by_a_room_category(): void
    {
        $image = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($image)]);

        $this->createRoomCategory(['description' => $this->html($image)]);

        $this->assertSame(Facility::class, $image->fresh()->model_type);
        $this->assertSame($facility->id, $image->fresh()->model_id);
    }

    public function test_removing_the_image_from_the_description_releases_it(): void
    {
        $inFacility = $this->editorImage();
        $inRoom = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($inFacility)]);
        $room = $this->createRoomCategory(['description' => $this->html($inRoom)]);

        $facility->update(['description' => '<p>Tanpa gambar lagi.</p>']);
        $room->update(['description' => '<p>Tanpa gambar lagi.</p>']);

        $this->assertNull($inFacility->fresh()->model_id);
        $this->assertNull($inFacility->fresh()->model_type);
        $this->assertNull($inRoom->fresh()->model_id);
    }

    public function test_updating_other_attributes_does_not_release_the_image(): void
    {
        $image = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($image)]);

        $facility->update(['short_description' => 'Ringkasan baru.']);

        $this->assertSame($facility->id, $image->fresh()->model_id);
    }

    public function test_prune_skips_ownerless_images_still_referenced_by_a_facility_or_room_description(): void
    {
        $inFacility = $this->editorImage();
        $inRoom = $this->editorImage();
        $unreferenced = $this->editorImage();

        // Baris lama sebelum pengikatan diterapkan: pemilik NULL walau dirujuk (jalur yang melewati event model).
        $this->createFacility()->forceFill(['description' => $this->html($inFacility)])->saveQuietly();
        $this->createRoomCategory()->forceFill(['description' => $this->html($inRoom)])->saveQuietly();

        foreach ([$inFacility, $inRoom, $unreferenced] as $media) {
            $this->age($media, 30);
        }

        DB::table('media')->whereIn('id', [$inFacility->id, $inRoom->id])->update(['model_type' => null, 'model_id' => null]);

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanRows);
        $this->assertNotNull($inFacility->fresh());
        $this->assertNotNull($inRoom->fresh());
        $this->assertNull($unreferenced->fresh());
        $this->assertFilesExist($inFacility);
        $this->assertFilesExist($inRoom);
        $this->assertFilesMissing($unreferenced);
    }

    public function test_deleting_a_facility_or_room_category_deletes_its_editor_images_and_cover_files(): void
    {
        $image = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($image)]);
        $cover = $this->service->store(ImageFixtures::jpeg(900, 600, 'cover.jpg'), 'cover', $facility, ['alt_text' => 'Foto contoh']);
        $roomImage = $this->editorImage();
        $room = $this->createRoomCategory(['description' => $this->html($roomImage)]);

        $facility->delete();
        $room->delete();

        $this->assertSame(0, Media::query()->count());
        $this->assertFilesMissing($image);
        $this->assertFilesMissing($cover);
        $this->assertFilesMissing($roomImage);
    }
}
