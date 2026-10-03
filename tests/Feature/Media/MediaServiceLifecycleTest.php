<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
        $this->owner = User::factory()->staff()->create();
    }

    private function storePhoto(?User $owner = null): Media
    {
        return $this->service->store(ImageFixtures::jpeg(600, 600), 'photo', $owner ?? $this->owner, ['alt_text' => 'Foto']);
    }

    private function replacePhoto(): Media
    {
        return $this->service->replace($this->owner, 'photo', ImageFixtures::jpeg(700, 700), ['alt_text' => 'Foto baru'], $this->owner);
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

    public function test_replace_swaps_the_single_media_and_deletes_the_old_files(): void
    {
        $old = $this->storePhoto();

        $new = $this->replacePhoto();

        $this->assertNotSame($old->id, $new->id);
        $this->assertFilesMissing($old);
        $this->assertFilesExist($new);
        $this->assertNull(Media::query()->find($old->id));
        $this->assertSame('Foto baru', $new->alt_text);
        $this->assertSame(
            1,
            Media::query()->where('collection', 'photo')->where('model_type', $this->owner->getMorphClass())->where('model_id', $this->owner->id)->count(),
        );
    }

    public function test_replace_writes_a_replaced_audit_and_no_second_uploaded_or_deleted_audit(): void
    {
        $old = $this->storePhoto();
        $new = $this->replacePhoto();

        $this->assertSame(1, AuditLog::query()->where('action', 'media.uploaded')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'media.deleted')->count());

        $log = AuditLog::query()->where('action', 'media.replaced')->sole();
        $this->assertSame($new->id, $log->auditable_id);
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame(['media_id' => $old->id, 'file_name' => $old->file_name], $log->old_values);
        $this->assertSame(['media_id' => $new->id, 'file_name' => $new->file_name], $log->new_values);
    }

    public function test_replace_without_a_previous_media_stores_the_first_one(): void
    {
        $new = $this->replacePhoto();

        $this->assertFilesExist($new);
        $this->assertSame(1, $new->sort_order);

        $log = AuditLog::query()->where('action', 'media.replaced')->sole();
        $this->assertNull($log->old_values);
        $this->assertSame(0, AuditLog::query()->where('action', 'media.uploaded')->count());
    }

    public function test_replace_rejects_collections_that_allow_many_images(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Koleksi gallery bukan koleksi tunggal dan tidak dapat diganti.');

        $this->service->replace($this->owner, 'gallery', ImageFixtures::jpeg(900, 600), ['alt_text' => 'Foto']);
    }

    public function test_replace_requires_a_persisted_owner(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pemilik media harus sudah tersimpan.');

        $this->service->replace(new User, 'photo', ImageFixtures::jpeg(700, 700), ['alt_text' => 'Foto']);
    }

    public function test_replace_keeps_the_old_media_when_the_new_upload_is_invalid(): void
    {
        $old = $this->storePhoto();

        $thrown = null;

        try {
            $this->service->replace($this->owner, 'photo', ImageFixtures::svgWithScript(), ['alt_text' => 'Foto baru']);
        } catch (ValidationException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertFilesExist($old);
        $this->assertNotNull(Media::query()->find($old->id));
        $this->assertSame(1, Media::query()->count());
    }

    public function test_replace_leaves_other_owners_media_untouched(): void
    {
        $other = User::factory()->staff()->create();
        $othersPhoto = $this->storePhoto($other);
        $this->storePhoto();

        $this->replacePhoto();

        $this->assertFilesExist($othersPhoto);
        $this->assertNotNull(Media::query()->find($othersPhoto->id));
    }

    public function test_replace_deletes_old_files_only_after_the_surrounding_transaction_commits(): void
    {
        $old = $this->storePhoto();

        DB::transaction(function () use ($old): void {
            $new = $this->replacePhoto();

            $this->assertFilesExist($old);
            $this->assertNotNull(Media::query()->find($old->id));
            $this->assertFilesExist($new);
        });

        $this->assertFilesMissing($old);
        $this->assertNull(Media::query()->find($old->id));
    }

    public function test_replace_inside_a_rolled_back_transaction_keeps_the_old_media(): void
    {
        $old = $this->storePhoto();

        $thrown = null;

        try {
            DB::transaction(function (): void {
                $this->replacePhoto();

                throw new RuntimeException('simulasi galat');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame('simulasi galat', $thrown->getMessage());
        $this->assertFilesExist($old);
        $this->assertNotNull(Media::query()->find($old->id));
        $this->assertSame(1, Media::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'media.replaced')->count());
    }

    public function test_delete_removes_the_row_both_files_and_writes_an_audit(): void
    {
        $media = $this->storePhoto();

        $this->service->delete($media);

        $this->assertFilesMissing($media);
        $this->assertNull(Media::query()->find($media->id));

        $log = AuditLog::query()->where('action', 'media.deleted')->sole();
        $this->assertSame($media->id, $log->auditable_id);
        $this->assertSame($media->id, $log->old_values['media_id']);
        $this->assertSame('photo', $log->old_values['collection']);
        $this->assertNull($log->new_values);
    }

    public function test_delete_waits_for_the_commit_and_keeps_everything_after_a_rollback(): void
    {
        $media = $this->storePhoto();

        $thrown = null;

        try {
            DB::transaction(function () use ($media): void {
                $this->service->delete($media);

                $this->assertFilesExist($media);

                throw new RuntimeException('simulasi galat');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertFilesExist($media);
        $this->assertNotNull(Media::query()->find($media->id));
        $this->assertSame(0, AuditLog::query()->where('action', 'media.deleted')->count());

        $this->service->delete(Media::query()->findOrFail($media->id));

        $this->assertFilesMissing($media);
    }

    public function test_delete_for_removes_only_the_media_of_that_owner(): void
    {
        $other = User::factory()->staff()->create();
        $othersPhoto = $this->storePhoto($other);
        $photo = $this->storePhoto();
        $galleryOne = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, ['alt_text' => 'Satu']);
        $galleryTwo = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, ['alt_text' => 'Dua']);

        $this->service->deleteFor($this->owner);

        foreach ([$photo, $galleryOne, $galleryTwo] as $media) {
            $this->assertFilesMissing($media);
            $this->assertNull(Media::query()->find($media->id));
        }

        $this->assertFilesExist($othersPhoto);
        $this->assertNotNull(Media::query()->find($othersPhoto->id));
        $this->assertSame(3, AuditLog::query()->where('action', 'media.deleted')->count());
    }
}
