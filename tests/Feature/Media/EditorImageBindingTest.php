<?php

namespace Tests\Feature\Media;

use App\Models\Clinic;
use App\Models\Media;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F1 final review fase 2 (08 §5.2): gambar editor diikat ke pemilik saat formulir disimpan,
 * dilepas bila tidak dirujuk lagi, dan `media:prune` tidak menghapus yang masih dipakai.
 */
class EditorImageBindingTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
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

        return '<p>Isi '.implode(' ', $imgs).'</p>';
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

    public function test_saving_a_clinic_binds_the_referenced_editor_image_to_it(): void
    {
        $image = $this->editorImage();
        $other = $this->editorImage();

        $clinic = $this->createClinic(['description' => $this->html($image)]);

        $image->refresh();
        $this->assertSame(Clinic::class, $image->model_type);
        $this->assertSame($clinic->id, $image->model_id);
        $this->assertNull($other->fresh()->model_id);
    }

    public function test_an_image_owned_by_another_clinic_is_not_taken_over(): void
    {
        $image = $this->editorImage();
        $first = $this->createClinic(['description' => $this->html($image)]);
        $second = $this->createClinic(['description' => $this->html($image)]);

        $this->assertSame($first->id, $image->fresh()->model_id);
        $this->assertNotSame($second->id, $image->fresh()->model_id);
    }

    public function test_removing_the_image_from_the_description_releases_it_and_touches_updated_at(): void
    {
        $image = $this->editorImage();
        $clinic = $this->createClinic(['description' => $this->html($image)]);
        $this->age($image, 48);

        $clinic->update(['description' => '<p>Tanpa gambar</p>']);

        $image->refresh();
        $this->assertNull($image->model_type);
        $this->assertNull($image->model_id);
        $this->assertTrue($image->updated_at->greaterThan(now()->subMinute()));
    }

    public function test_updating_other_attributes_does_not_release_the_image(): void
    {
        $image = $this->editorImage();
        $clinic = $this->createClinic(['description' => $this->html($image)]);

        $clinic->update(['location' => 'Gedung B']);

        $this->assertSame($clinic->id, $image->fresh()->model_id);
    }

    public function test_prune_keeps_bound_images_and_removes_released_and_never_saved_ones(): void
    {
        $bound = $this->editorImage();
        $released = $this->editorImage();
        $neverSaved = $this->editorImage();
        $clinic = $this->createClinic(['description' => $this->html($bound, $released)]);
        $clinic->update(['description' => $this->html($bound)]);

        $this->travel(25)->hours();
        $this->age($neverSaved, 25);
        $this->age($released, 25);

        $report = $this->service->prune();

        $this->assertSame(2, $report->orphanRows);
        $this->assertNotNull($bound->fresh());
        $this->assertFilesExist($bound);
        $this->assertNull($released->fresh());
        $this->assertFilesMissing($released);
        $this->assertNull($neverSaved->fresh());
        $this->assertFilesMissing($neverSaved);
    }

    public function test_released_image_is_not_pruned_before_24_hours_since_release(): void
    {
        $image = $this->editorImage();
        $this->age($image, 100);
        $clinic = $this->createClinic(['description' => $this->html($image)]);
        $clinic->update(['description' => '<p>Kosong</p>']);

        $this->assertSame(0, $this->service->prune()->orphanRows);
        $this->assertNotNull($image->fresh());

        $this->travel(25)->hours();

        $this->assertSame(1, $this->service->prune()->orphanRows);
        $this->assertNull($image->fresh());
    }

    public function test_prune_skips_ownerless_images_still_referenced_by_a_clinic_description(): void
    {
        $shared = $this->editorImage();
        $legacy = $this->editorImage();
        $this->createClinic(['description' => $this->html($shared)]);
        $this->createClinic()->forceFill(['description' => $this->html($legacy)])->saveQuietly();
        $this->age($shared, 30);
        $this->age($legacy, 30);
        // Baris lama sebelum perbaikan: pemilik NULL walau dirujuk.
        DB::table('media')->whereIn('id', [$shared->id, $legacy->id])->update(['model_type' => null, 'model_id' => null]);

        $report = $this->service->prune();

        $this->assertSame(0, $report->orphanRows);
        $this->assertNotNull($shared->fresh());
        $this->assertNotNull($legacy->fresh());
        $this->assertFilesExist($shared);
        $this->assertFilesExist($legacy);
    }

    public function test_deleting_the_clinic_deletes_its_editor_images_after_commit(): void
    {
        $image = $this->editorImage();
        $clinic = $this->createClinic(['description' => $this->html($image)]);

        $this->assertFilesExist($image);

        $clinic->delete();

        $this->assertNull($image->fresh());
        $this->assertFilesMissing($image);
    }

    public function test_content_admin_can_update_a_clinic_owned_editor_image_and_registration_officer_cannot(): void
    {
        $image = $this->editorImage();
        $this->createClinic(['description' => $this->html($image)]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->patchJson(route('admin.media.update', $image), ['alt_text' => 'Alt baru'])
            ->assertForbidden();

        $this->actingAs($this->createStaff('content-admin'))
            ->patchJson(route('admin.media.update', $image), ['alt_text' => 'Alt baru'])
            ->assertOk();

        $this->assertSame('Alt baru', $image->fresh()->alt_text);
    }

    public function test_editor_images_do_not_count_as_gallery_or_cover(): void
    {
        $image = $this->editorImage();
        $clinic = $this->createClinic(['description' => $this->html($image)]);

        $clinic->load('media');

        $this->assertCount(0, $clinic->mediaIn('gallery'));
        $this->assertNull($clinic->firstMedia('cover'));
        $this->assertCount(1, $clinic->mediaIn('editor'));
    }
}
