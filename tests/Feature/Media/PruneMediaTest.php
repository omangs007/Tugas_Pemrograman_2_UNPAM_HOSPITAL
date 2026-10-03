<?php

namespace Tests\Feature\Media;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaPruneReport;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class PruneMediaTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->service = app(MediaService::class);
    }

    private function store(string $collection, ?User $owner): Media
    {
        return $this->service->store(ImageFixtures::jpeg(900, 600), $collection, $owner, ['alt_text' => 'Contoh']);
    }

    private function age(Media $media, int $hours): void
    {
        DB::table('media')->where('id', $media->id)->update(['created_at' => now()->subHours($hours)]);
    }

    /**
     * Berkas 10 byte tanpa baris `media`, dengan umur diatur lewat touch().
     */
    private function stray(string $path, int $ageMinutes): void
    {
        $disk = Storage::disk('public');
        $disk->put($path, str_repeat('x', 10));
        touch($disk->path($path), time() - $ageMinutes * 60);
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

    /**
     * Skenario MED-07: satu baris terpakai, satu yatim lama, satu yatim baru, logo situs yatim lama,
     * dua berkas yatim lama, satu berkas yatim baru, satu berkas di luar folder koleksi, satu berkas tersembunyi.
     *
     * @return array{owned: Media, stale: Media, fresh: Media, logo: Media}
     */
    private function scenario(): array
    {
        $owner = User::factory()->staff()->create();

        $owned = $this->store('photo', $owner);
        $this->age($owned, 72);

        $stale = $this->store('editor', null);
        $this->age($stale, 25);

        $fresh = $this->store('editor', null);
        $this->age($fresh, 23);

        $logo = $this->store('logo', null);
        $this->age($logo, 72);
        app(SettingsService::class)->set('site.logo_media_id', $logo->id);

        $this->stray('editor/2026/10/stray-old.jpg', 120);
        $this->stray('photo/2026/10/thumbs/stray-thumb-old.jpg', 180);
        $this->stray('editor/2026/10/stray-new.jpg', 10);
        $this->stray('other/2026/10/not-ours.jpg', 600);
        $this->stray('editor/.gitkeep', 600);

        return compact('owned', 'stale', 'fresh', 'logo');
    }

    private function expectedBytes(Media $stale): int
    {
        return $stale->size_bytes + Storage::disk('public')->size($stale->thumb_path) + 10 + 10;
    }

    public function test_report_is_an_immutable_value_object_with_empty_items_by_default(): void
    {
        $report = new MediaPruneReport(1, 2, 3, true);

        $this->assertSame([1, 2, 3, true, []], [$report->orphanRows, $report->orphanFiles, $report->bytes, $report->dryRun, $report->items]);

        $this->expectException(Error::class);

        $report->orphanRows = 9;
    }

    public function test_dry_run_reports_orphans_but_deletes_nothing(): void
    {
        $media = $this->scenario();
        $expectedBytes = $this->expectedBytes($media['stale']);

        $report = $this->service->prune(true);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->orphanRows);
        $this->assertSame(2, $report->orphanFiles);
        $this->assertSame($expectedBytes, $report->bytes);

        $this->assertCount(3, $report->items);
        $listing = implode("\n", $report->items);
        $this->assertStringContainsString('#'.$media['stale']->id, $listing);
        $this->assertStringContainsString('editor/2026/10/stray-old.jpg', $listing);
        $this->assertStringContainsString('photo/2026/10/thumbs/stray-thumb-old.jpg', $listing);

        $this->assertSame(4, Media::query()->count());
        foreach ($media as $row) {
            $this->assertFilesExist($row);
        }
        foreach (['editor/2026/10/stray-old.jpg', 'photo/2026/10/thumbs/stray-thumb-old.jpg', 'editor/2026/10/stray-new.jpg', 'other/2026/10/not-ours.jpg', 'editor/.gitkeep'] as $path) {
            Storage::disk('public')->assertExists($path);
        }
        $this->assertSame(0, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_real_run_deletes_only_orphans_and_writes_a_system_audit(): void
    {
        $media = $this->scenario();
        $expectedBytes = $this->expectedBytes($media['stale']);

        $report = $this->service->prune();

        $this->assertFalse($report->dryRun);
        $this->assertSame(1, $report->orphanRows);
        $this->assertSame(2, $report->orphanFiles);
        $this->assertSame($expectedBytes, $report->bytes);

        $this->assertNull(Media::query()->find($media['stale']->id));
        $this->assertFilesMissing($media['stale']);
        Storage::disk('public')->assertMissing('editor/2026/10/stray-old.jpg');
        Storage::disk('public')->assertMissing('photo/2026/10/thumbs/stray-thumb-old.jpg');

        foreach (['owned', 'fresh', 'logo'] as $name) {
            $this->assertNotNull(Media::query()->find($media[$name]->id), $name);
            $this->assertFilesExist($media[$name]);
        }
        Storage::disk('public')->assertExists('editor/2026/10/stray-new.jpg');
        Storage::disk('public')->assertExists('other/2026/10/not-ours.jpg');
        Storage::disk('public')->assertExists('editor/.gitkeep');

        $log = AuditLog::query()->where('action', 'media.pruned')->sole();
        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame(Media::class, $log->auditable_type);
        $this->assertNull($log->auditable_id);
        $this->assertSame(['orphan_rows' => 1, 'orphan_files' => 2, 'bytes' => $expectedBytes], $log->new_values);
    }

    public function test_a_second_run_finds_nothing_but_is_still_audited(): void
    {
        $this->scenario();
        $this->service->prune();

        $report = $this->service->prune();

        $this->assertSame([0, 0, 0, []], [$report->orphanRows, $report->orphanFiles, $report->bytes, $report->items]);
        $this->assertSame(2, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_the_site_logo_is_only_protected_while_it_is_the_configured_logo(): void
    {
        $media = $this->scenario();
        app(SettingsService::class)->set('site.logo_media_id', null);

        $report = $this->service->prune();

        $this->assertSame(2, $report->orphanRows);
        $this->assertNull(Media::query()->find($media['logo']->id));
        $this->assertFilesMissing($media['logo']);
    }

    public function test_an_old_orphan_row_whose_files_are_already_gone_is_still_removed(): void
    {
        $stale = $this->store('editor', null);
        $this->age($stale, 30);
        Storage::disk('public')->delete([$stale->directory.'/'.$stale->file_name, $stale->thumb_path]);

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanRows);
        $this->assertSame(0, $report->orphanFiles);
        $this->assertSame($stale->size_bytes, $report->bytes);
        $this->assertNull(Media::query()->find($stale->id));
    }

    public function test_file_age_threshold_is_one_hour(): void
    {
        $this->stray('editor/2026/10/almost.jpg', 59);
        $this->stray('editor/2026/10/over.jpg', 61);

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanFiles);
        Storage::disk('public')->assertExists('editor/2026/10/almost.jpg');
        Storage::disk('public')->assertMissing('editor/2026/10/over.jpg');
    }

    public function test_row_age_threshold_is_24_hours(): void
    {
        $almost = $this->store('editor', null);
        $this->age($almost, 23);
        $over = $this->store('editor', null);
        $this->age($over, 25);

        $this->service->prune();

        $this->assertNotNull(Media::query()->find($almost->id));
        $this->assertNull(Media::query()->find($over->id));
    }

    public function test_pruning_an_empty_system_reports_zeroes(): void
    {
        $report = $this->service->prune(true);

        $this->assertSame([0, 0, 0, true, []], [$report->orphanRows, $report->orphanFiles, $report->bytes, $report->dryRun, $report->items]);
    }
}
