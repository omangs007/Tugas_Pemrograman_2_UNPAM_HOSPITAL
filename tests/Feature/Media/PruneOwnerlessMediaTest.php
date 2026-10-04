<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

/**
 * Butir (c) media:prune (F1-R12): baris media yang pemiliknya sudah tidak ada.
 */
class PruneOwnerlessMediaTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);
        $this->service = app(MediaService::class);
    }

    private function cover(Clinic $clinic): Media
    {
        return $this->service->store(ImageFixtures::jpeg(1200, 800), 'cover', $clinic, ['alt_text' => 'Cover']);
    }

    private function filesExist(Media $media): bool
    {
        return Storage::disk('public')->exists($media->directory.'/'.$media->file_name)
            && Storage::disk('public')->exists($media->thumb_path);
    }

    public function test_media_whose_owner_row_was_deleted_behind_the_models_back_is_pruned(): void
    {
        $gone = $this->createClinic();
        $kept = $this->createClinic();
        $goneCover = $this->cover($gone);
        $keptCover = $this->cover($kept);
        $doctor = $this->createDoctor();
        $photo = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $doctor, ['alt_text' => 'Foto']);

        // Penghapusan tingkat query tidak memicu event `deleting` HasMedia.
        DB::table('clinics')->where('id', $gone->id)->delete();

        $dry = $this->service->prune(true);

        $this->assertSame(1, $dry->orphanRows);
        $this->assertCount(1, $dry->items);
        $this->assertStringContainsString('#'.$goneCover->id, $dry->items[0]);
        $this->assertStringContainsString('pemilik Clinic #'.$gone->id.' sudah tidak ada', $dry->items[0]);
        $this->assertSame($goneCover->size_bytes + Storage::disk('public')->size($goneCover->thumb_path), $dry->bytes);
        $this->assertNotNull(Media::query()->find($goneCover->id), 'Dry-run tidak menghapus.');

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanRows);
        $this->assertNull(Media::query()->find($goneCover->id));
        $this->assertFalse($this->filesExist($goneCover));
        $this->assertNotNull(Media::query()->find($keptCover->id));
        $this->assertTrue($this->filesExist($keptCover));
        $this->assertNotNull(Media::query()->find($photo->id));
        $this->assertTrue($this->filesExist($photo));
        $this->assertSame(1, AuditLog::query()->where('action', 'media.pruned')->count());

        $second = $this->service->prune(true);
        $this->assertSame([0, 0, 0, []], [$second->orphanRows, $second->orphanFiles, $second->bytes, $second->items]);
    }

    public function test_rows_are_pruned_regardless_of_their_age(): void
    {
        $gone = $this->createClinic();
        $cover = $this->cover($gone);
        DB::table('clinics')->where('id', $gone->id)->delete();

        $this->assertTrue($cover->fresh()->created_at->greaterThan(now()->subMinute()));
        $this->assertSame(1, $this->service->prune(true)->orphanRows);
    }

    public function test_unknown_owner_classes_and_existing_owners_of_other_types_are_left_alone(): void
    {
        $user = User::factory()->staff()->create();
        $userMedia = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $user, ['alt_text' => 'Foto']);
        $unknown = $this->createMediaRow(['model_type' => 'App\\Models\\TidakAda', 'model_id' => 999, 'collection' => 'photo']);
        $notModel = $this->createMediaRow(['model_type' => 'stdClass', 'model_id' => 5, 'collection' => 'photo']);

        $report = $this->service->prune();

        $this->assertSame(0, $report->orphanRows);
        $this->assertNotNull(Media::query()->find($userMedia->id));
        $this->assertNotNull(Media::query()->find($unknown->id));
        $this->assertNotNull(Media::query()->find($notModel->id));
    }

    public function test_orphan_rows_count_combines_unowned_old_rows_and_ownerless_rows(): void
    {
        $stale = $this->service->store(ImageFixtures::jpeg(900, 600), 'editor', null, ['alt_text' => 'Editor']);
        DB::table('media')->where('id', $stale->id)->update(['created_at' => now()->subHours(30), 'updated_at' => now()->subHours(30)]);

        $doctor = $this->createDoctor();
        $photo = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $doctor, ['alt_text' => 'Foto']);
        DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->delete();
        DB::table('doctors')->where('id', $doctor->id)->delete();

        $report = $this->service->prune();

        $this->assertSame(2, $report->orphanRows);
        $this->assertNull(Media::query()->find($stale->id));
        $this->assertNull(Media::query()->find($photo->id));
        $this->assertSame(
            ['orphan_rows' => 2, 'orphan_files' => 0, 'bytes' => $report->bytes],
            AuditLog::query()->where('action', 'media.pruned')->sole()->new_values,
        );
    }

    public function test_the_prune_command_reports_ownerless_rows(): void
    {
        $gone = $this->createClinic();
        $cover = $this->cover($gone);
        DB::table('clinics')->where('id', $gone->id)->delete();

        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 1')
            ->expectsOutputToContain('#'.$cover->id)
            ->assertExitCode(0);
    }

    public function test_delete_for_is_atomic_when_one_media_cannot_be_deleted(): void
    {
        $clinic = $this->createClinic();
        $first = $this->cover($clinic);
        $second = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $clinic, ['alt_text' => 'Galeri']);

        // Pemicu galat audit pada penghapusan media kedua.
        $calls = 0;
        AuditLog::creating(function () use (&$calls): void {
            if (++$calls === 2) {
                throw new \RuntimeException('Gagal menulis audit.');
            }
        });

        try {
            $this->service->deleteFor($clinic);
            $this->fail('Galat audit seharusnya diteruskan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Gagal menulis audit.', $e->getMessage());
        }

        $this->assertNotNull(Media::query()->find($first->id), 'Penghapusan pertama ikut di-rollback.');
        $this->assertNotNull(Media::query()->find($second->id));
        $this->assertTrue($this->filesExist($first));
        $this->assertTrue($this->filesExist($second));
    }
}
