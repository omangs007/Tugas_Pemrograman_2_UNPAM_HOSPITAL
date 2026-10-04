<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Media;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class PruneMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    private Media $stale;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->stale = app(MediaService::class)->store(ImageFixtures::jpeg(900, 600), 'editor', null, ['alt_text' => 'Contoh']);
        DB::table('media')->where('id', $this->stale->id)->update(['created_at' => now()->subHours(25), 'updated_at' => now()->subHours(25)]);

        $disk = Storage::disk('public');
        $disk->put('editor/2026/10/stray-old.jpg', str_repeat('x', 10));
        touch($disk->path('editor/2026/10/stray-old.jpg'), time() - 7200);
    }

    public function test_dry_run_prints_the_counts_the_list_and_the_test_mode_sentence_without_deleting(): void
    {
        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 1')
            ->expectsOutputToContain('Berkas yatim: 1')
            ->expectsOutputToContain('Total ukuran: ')
            ->expectsOutputToContain('#'.$this->stale->id)
            ->expectsOutputToContain('editor/2026/10/stray-old.jpg')
            ->expectsOutputToContain('Mode uji: tidak ada yang dihapus.')
            ->assertExitCode(0);

        $this->assertNotNull(Media::query()->find($this->stale->id));
        Storage::disk('public')->assertExists('editor/2026/10/stray-old.jpg');
        $this->assertSame(0, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_real_run_prints_the_counts_deletes_the_orphans_and_exits_zero(): void
    {
        $this->artisan('media:prune')
            ->expectsOutputToContain('Baris media yatim: 1')
            ->expectsOutputToContain('Berkas yatim: 1')
            ->expectsOutputToContain('Total ukuran: ')
            ->expectsOutputToContain('Pembersihan selesai.')
            ->doesntExpectOutputToContain('Mode uji')
            ->assertExitCode(0);

        $this->assertNull(Media::query()->find($this->stale->id));
        Storage::disk('public')->assertMissing('editor/2026/10/stray-old.jpg');
        $this->assertSame(1, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_nothing_to_prune_still_exits_zero(): void
    {
        $this->artisan('media:prune')->run();

        $this->artisan('media:prune')
            ->expectsOutputToContain('Baris media yatim: 0')
            ->expectsOutputToContain('Berkas yatim: 0')
            ->assertExitCode(0);
    }
}
