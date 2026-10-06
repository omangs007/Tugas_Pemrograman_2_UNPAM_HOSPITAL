<?php

namespace Tests\Feature\Seeding;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Content\HtmlSanitizer;
use App\Services\Settings\SettingsService;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PageSeeder (13 §8.8, 13 §2.3): 15 halaman sistem di semua lingkungan, termasuk produksi.
 */
class PageSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** @return array<string, array{0: string}> */
    public static function bodyKeys(): array
    {
        return array_combine(Page::KEYS_WITH_BODY, array_map(fn (string $key): array => [$key], Page::KEYS_WITH_BODY));
    }

    private function body(string $key): string
    {
        return (string) DB::table('pages')->where('key', $key)->value('body');
    }

    private function expectedBody(string $key, ?string $address = null): string
    {
        $source = PageSeeder::BODIES[$key];

        if ($key === 'about') {
            $source = str_replace(PageSeeder::ADDRESS_TOKEN, e($address ?? SettingsSeeder::DEFAULTS['contact.address']['value']), $source);
        }

        return app(HtmlSanitizer::class)->clean($source);
    }

    public function test_it_seeds_the_fifteen_system_pages_in_key_order(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(PageSeeder::class);

        $this->assertSame(15, Page::query()->count());
        $this->assertSame(Page::KEYS, Page::query()->orderBy('id')->pluck('key')->all());
        $this->assertSame(15, Page::query()->where('is_system', true)->count());
        $this->assertSame(0, Page::query()->whereNotNull('updated_by')->count());
        $this->assertSame(0, Page::query()->whereNotNull('meta_title')->count());
    }

    public function test_titles_subtitles_and_meta_descriptions_follow_the_defaults(): void
    {
        $this->seed(PageSeeder::class);

        foreach (Page::KEYS as $key) {
            $page = Page::query()->where('key', $key)->firstOrFail();

            $this->assertSame(Page::DEFAULTS[$key]['title'], $page->title, "judul {$key}");
            $this->assertSame(Page::DEFAULTS[$key]['subtitle'], $page->subtitle, "subjudul {$key}");
            $this->assertSame(Page::DEFAULTS[$key]['meta_description'], $page->meta_description, "meta {$key}");
            $this->assertLessThanOrEqual(160, mb_strlen((string) $page->meta_description), "panjang meta {$key}");
        }
    }

    public function test_only_the_five_keys_with_body_have_content(): void
    {
        $this->seed(PageSeeder::class);

        $this->assertSame(Page::KEYS_WITH_BODY, array_keys(PageSeeder::BODIES));

        foreach (Page::KEYS as $key) {
            $body = Page::query()->where('key', $key)->value('body');

            if (in_array($key, Page::KEYS_WITH_BODY, true)) {
                $this->assertNotNull($body, "body {$key}");
                $this->assertGreaterThan(200, mb_strlen($body), "panjang body {$key}");
            } else {
                $this->assertNull($body, "body {$key}");
            }
        }
    }

    #[DataProvider('bodyKeys')]
    public function test_each_body_is_stored_exactly_as_the_sanitizer_returns_it(string $key): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(PageSeeder::class);

        $stored = $this->body($key);

        $this->assertSame($this->expectedBody($key), $stored);
        $this->assertStringNotContainsString('class=', $stored);
        $this->assertStringNotContainsString('style=', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('<a ', $stored, 'Tanpa tautan ke route yang belum ada.');
        $this->assertStringNotContainsString('href', $stored);
        $this->assertStringNotContainsString('<img', $stored);
    }

    #[DataProvider('bodyKeys')]
    public function test_no_body_claims_accreditation_or_an_official_partnership(string $key): void
    {
        $this->seed(PageSeeder::class);

        foreach (['akreditasi', 'terakreditasi', 'kerja sama', 'bekerja sama', 'bermitra'] as $claim) {
            $this->assertStringNotContainsStringIgnoringCase($claim, $this->body($key), "{$key} memuat '{$claim}'");
        }
    }

    public function test_about_contains_the_address_from_the_settings(): void
    {
        $this->seed(SettingsSeeder::class);
        WebsiteSetting::query()->where('key', 'contact.address')->update(['value' => 'Jl. Contoh No. 7, Pamulang, Banten 15310']);
        app(SettingsService::class)->forget();

        $this->seed(PageSeeder::class);

        $about = $this->body('about');

        $this->assertStringContainsString('Jl. Contoh No. 7, Pamulang, Banten 15310', $about);
        $this->assertStringContainsString('<strong>prototipe</strong>', $about);
        $this->assertSame(3, substr_count($about, '<p>'));
        $this->assertStringNotContainsString(PageSeeder::ADDRESS_TOKEN, $about);
    }

    public function test_about_falls_back_to_the_default_address_when_no_setting_row_exists(): void
    {
        $this->assertSame(0, WebsiteSetting::query()->count());

        $this->seed(PageSeeder::class);

        $this->assertStringContainsString(
            'Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310',
            $this->body('about'),
        );
    }

    public function test_the_address_is_escaped_before_it_enters_the_body(): void
    {
        $this->seed(SettingsSeeder::class);
        WebsiteSetting::query()->where('key', 'contact.address')->update(['value' => 'Jl. A & B <b>Blok</b> No. 1']);
        app(SettingsService::class)->forget();

        $this->seed(PageSeeder::class);

        $about = $this->body('about');

        $this->assertStringContainsString('Jl. A &amp; B &lt;b&gt;Blok&lt;/b&gt; No. 1', $about);
        $this->assertStringNotContainsString('<b>', $about);
    }

    public function test_vision_and_mission_have_a_vision_paragraph_and_a_numbered_mission_list(): void
    {
        $this->seed(PageSeeder::class);

        $body = $this->body('vision-mission');

        $this->assertStringContainsString('<h2>Visi</h2>', $body);
        $this->assertStringContainsString('<h2>Misi</h2>', $body);
        $this->assertSame(1, substr_count($body, '<ol>'));
        $this->assertSame(4, substr_count($body, '<li>'));
        $this->assertStringContainsString('Menjadi layanan kesehatan yang ramah, mudah diakses, dan mengutamakan keselamatan pasien.', $body);
        $this->assertStringContainsString('contoh', mb_strtolower($body));
    }

    public function test_insurance_info_has_the_three_sections_but_not_the_warning_box(): void
    {
        $this->seed(PageSeeder::class);

        $body = $this->body('insurance-info');

        $this->assertSame(3, substr_count($body, '<h2>'));
        $this->assertStringContainsString('<h2>BPJS Kesehatan</h2>', $body);
        $this->assertStringContainsString('<h2>Asuransi</h2>', $body);
        $this->assertStringContainsString('<h2>Pembayaran Umum</h2>', $body);
        $this->assertStringContainsString('13 digit', $body);
        $this->assertStringNotContainsString('Informasi ini contoh dan harus diverifikasi', $body, 'Kotak peringatan ditampilkan view #16.');
    }

    public function test_registration_guide_has_online_steps_counter_steps_and_the_time_limits(): void
    {
        $this->seed(PageSeeder::class);

        $body = $this->body('registration-guide');

        $this->assertStringContainsString('<h2>Pendaftaran Online</h2>', $body);
        $this->assertStringContainsString('<h2>Pendaftaran di Loket</h2>', $body);
        $this->assertStringContainsString('<h2>Batas Waktu</h2>', $body);
        $this->assertSame(1, preg_match('~<ol>(.*?)</ol>~s', $body, $steps));
        $this->assertSame(6, substr_count($steps[1], '<li>'), 'Enam langkah pendaftaran online.');
        $this->assertStringContainsString('60 menit', $body);
        $this->assertStringContainsString('120 menit', $body);
        $this->assertStringContainsString('tidak dipakai ulang', $body);
    }

    public function test_privacy_policy_follows_the_twelve_points_of_12_section_7_6(): void
    {
        $this->seed(PageSeeder::class);

        $body = $this->body('privacy-policy');

        $this->assertSame(12, substr_count($body, '<h2>'));
        $this->assertStringContainsString('Kami tidak meminta NIK maupun data medis.', $body);
        $this->assertStringContainsString('1.0', $body);
        $this->assertStringContainsString('Teks contoh; tinjau dengan konsultan hukum sebelum produksi.', $body);
        $this->assertStringContainsString('terenkripsi', $body);
        $this->assertStringContainsString('Admin konten tidak dapat melihat data pasien.', $body);
        $this->assertStringContainsString('cookie sesi', $body);
        $this->assertStringNotContainsString('UU PDP', $body, 'Prototipe tidak mengklaim kepatuhan UU PDP (12 §7.7).');
    }

    public function test_running_twice_keeps_fifteen_rows_and_the_same_ids(): void
    {
        $this->seed(PageSeeder::class);
        $ids = Page::query()->orderBy('id')->pluck('id', 'key')->all();

        $this->seed(PageSeeder::class);

        $this->assertSame(15, Page::query()->count());
        $this->assertSame($ids, Page::query()->orderBy('id')->pluck('id', 'key')->all());
    }

    public function test_admin_edits_are_not_overwritten_by_a_second_run(): void
    {
        $this->seed(PageSeeder::class);
        Page::forKey('about')->update(['title' => 'Tentang Kami', 'body' => '<p>Suntingan admin.</p>', 'meta_title' => 'Meta khusus']);
        Page::forKey('faq')->update(['subtitle' => 'Subjudul baru']);

        $this->seed(PageSeeder::class);

        $about = Page::forKey('about');
        $this->assertSame('Tentang Kami', $about->title);
        $this->assertSame('<p>Suntingan admin.</p>', $about->body);
        $this->assertSame('Meta khusus', $about->meta_title);
        $this->assertSame('Subjudul baru', Page::forKey('faq')->subtitle);
    }

    public function test_a_missing_row_is_recreated_without_touching_the_others(): void
    {
        $this->seed(PageSeeder::class);
        Page::forKey('about')->update(['title' => 'Tentang Kami']);
        Page::query()->where('key', 'privacy-policy')->delete();

        $this->seed(PageSeeder::class);

        $this->assertSame(15, Page::query()->count());
        $this->assertSame('Tentang Kami', Page::forKey('about')->title);
        $this->assertSame('Kebijakan Privasi', Page::forKey('privacy-policy')->title);
        $this->assertNotNull(Page::forKey('privacy-policy')->body);
    }

    public function test_seeded_pages_are_read_by_for_key(): void
    {
        $this->seed(PageSeeder::class);

        $page = Page::forKey('registration-guide');

        $this->assertTrue($page->exists);
        $this->assertTrue($page->hasBody());
        $this->assertSame('Panduan Pendaftaran Pasien', $page->seo_title);
        $this->assertSame('Panduan mendaftar kunjungan rawat jalan online maupun di loket.', $page->seo_description);
    }

    public function test_seeding_is_audited_as_the_system(): void
    {
        $this->seed(PageSeeder::class);

        $logs = AuditLog::query()->where('action', 'page.created')->get();

        $this->assertCount(15, $logs);
        $this->assertSame([ActorType::System], $logs->pluck('actor_type')->unique()->values()->all());
        $this->assertNull($logs->first()->user_id);
        $this->assertStringStartsWith('Halaman "', $logs->first()->description);
    }

    public function test_database_seeder_seeds_the_pages_in_production_and_still_skips_the_demo(): void
    {
        $this->inEnvironment('production', function (): void {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
                ->expectsOutputToContain('DemoSeeder dilewati (APP_ENV=production)')
                ->assertExitCode(0);
        });

        $this->assertSame(15, Page::query()->count());
        $this->assertSame(Page::KEYS, Page::query()->orderBy('id')->pluck('key')->all());
        $this->assertSame(19, WebsiteSetting::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, DB::table('media')->count());
        $this->assertSame(0, DB::table('articles')->count() + DB::table('banners')->count() + DB::table('faqs')->count() + DB::table('galleries')->count() + DB::table('insurance_partners')->count());
    }

    public function test_the_page_seeder_has_no_environment_guard(): void
    {
        $this->inEnvironment('production', function (): void {
            // Dipanggil langsung: `db:seed` tanpa --force menunggu konfirmasi di produksi.
            $this->app->make(PageSeeder::class)->run($this->app->make(SettingsService::class));
        });

        $this->assertSame(15, Page::query()->count());
    }

    private function inEnvironment(string $environment, Closure $callback): void
    {
        $original = $this->app['env'];
        $this->app['env'] = $environment;

        try {
            $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }
}
