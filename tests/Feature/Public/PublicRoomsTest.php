<?php

namespace Tests\Feature\Public;

use App\Models\Media;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #11 `rooms.index` dan #12-#15 `rooms.show` (05 hal. 11-15, 07 W-07, F4B-R9 s.d. F4B-R12, F4B-R30):
 * kartu dan detail kategori kamar aktif dengan badge Tersedia/Penuh (teks + ikon + warna), label dari accessor,
 * kalimat peringatan tanpa kata "real-time", dan 404 untuk kategori nonaktif bagi semua aktor. Waktu dibekukan
 * pada Senin 5 Oktober 2026 pukul 10.00 WIB. Mencakup CRD-07, EMP-01, Review Focus 1 dan 3, serta mode ketat.
 */
class PublicRoomsTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const NOTICE = 'Ketersediaan tempat tidur diperbarui manual oleh petugas dan dapat berubah sewaktu-waktu. Hubungi rumah sakit untuk memastikan. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh.';

    private const STALE = 'Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 10:00:00'); // Senin
    }

    /** @param  array<string, mixed>  $overrides */
    private function room(array $overrides = []): RoomCategory
    {
        return $this->createRoomCategory(array_merge([
            'name' => 'BPJS Kelas 1',
            'slug' => 'bpjs-kelas-1',
            'short_description' => 'Kamar dua tempat tidur contoh.',
            'bed_capacity' => 20,
            'beds_available' => 12,
            'sample_rate' => 400000,
            'rate_unit' => 'per malam',
            'rate_note' => null,
            'meta_title' => null,
            'meta_description' => null,
            'amenities' => ['AC', 'Kamar mandi dalam'],
            'availability_updated_at' => now()->subHours(2),
            'sort_order' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function attachImage(Model $owner, string $collection, string $alt, string $name = 'foto.jpg'): Media
    {
        return $this->app->make(MediaService::class)->store(
            ImageFixtures::jpeg(1200, 800, $name),
            $collection,
            $owner,
            ['alt_text' => $alt],
        );
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function showUrl(RoomCategory $room): string
    {
        return route('rooms.show', $room->slug);
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    // --- #11: header, SEO, breadcrumb, peringatan ----------------------------------------------------------

    public function test_index_renders_the_default_header_seo_breadcrumb_and_the_notice(): void
    {
        $this->room();

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('<title>Kamar Rawat Inap — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Kamar Rawat Inap</h1>', false)
            ->assertSee('Ringkasan kategori kamar dan ketersediaan')
            ->assertSee('<meta name="description" content="Kategori kamar rawat inap, kapasitas, dan ketersediaan yang diperbarui manual.">', false)
            ->assertSee('<meta property="og:title" content="Kamar Rawat Inap">', false)
            ->assertSee('<link rel="canonical" href="'.route('rooms.index').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Kamar Rawat Inap'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Kamar Rawat Inap</li>', false)
            ->assertSee('alert alert-info', false)
            ->assertSee('bi-info-circle', false)
            ->assertSee(self::NOTICE)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_index_header_and_seo_follow_the_rooms_index_page_row(): void
    {
        $this->createPage('rooms-index', [
            'title' => 'Kamar Kami',
            'subtitle' => 'Pilihan kamar',
            'meta_title' => 'Kamar Kami Meta',
            'meta_description' => 'Deskripsi kamar kami.',
        ]);
        $this->room();

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('<title>Kamar Kami Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Kamar Kami</h1>', false)
            ->assertSee('Pilihan kamar')
            ->assertSee('<meta name="description" content="Deskripsi kamar kami.">', false);
    }

    // --- #11: kartu -----------------------------------------------------------------------------------------------

    public function test_index_lists_active_categories_in_order_with_the_exact_texts(): void
    {
        $this->room(['name' => 'Kamar Kedua', 'slug' => 'kamar-kedua', 'sort_order' => 2, 'bed_capacity' => 6, 'beds_available' => 2, 'sample_rate' => 1250000]);
        $this->room(['name' => 'Kamar Pertama', 'slug' => 'kamar-pertama', 'sort_order' => 1]);
        $this->room(['name' => 'Kamar Nonaktif Uji', 'slug' => 'kamar-nonaktif', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSeeInOrder(['Kamar Pertama', 'Kamar Kedua'])
            ->assertSee('Kapasitas: 20 tempat tidur')
            ->assertSee('12 dari 20 tempat tidur tersedia')
            ->assertSee('Tarif contoh: Rp 400.000 per malam')
            ->assertSee('Kapasitas: 6 tempat tidur')
            ->assertSee('2 dari 6 tempat tidur tersedia')
            ->assertSee('Tarif contoh: Rp 1.250.000 per malam')
            ->assertSee('Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.00 WIB')
            ->assertSee('Kamar dua tempat tidur contoh.')
            ->assertDontSee('Kamar Nonaktif Uji');
        $this->assertSame(2, substr_count($response->getContent(), 'class="card h-100 room-card"'));
    }

    public function test_each_card_has_one_stretched_link_a_decorative_button_and_no_nested_anchors(): void
    {
        $room = $this->room();

        $response = $this->get(route('rooms.index'))->assertOk();
        $html = $response->getContent();

        $response->assertSee('href="'.$this->showUrl($room).'"', false)
            ->assertSee('<h2 class="card-title h5 mb-1">', false)
            ->assertSee('<span class="btn btn-outline-primary mt-auto" aria-hidden="true">Lihat Detail</span>', false)
            ->assertSee('images/placeholders/room.svg', false)
            ->assertSee('alt="BPJS Kelas 1"', false);
        $this->assertSame(1, substr_count($html, 'stretched-link'));
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));
    }

    public function test_a_card_uses_the_cover_thumbnail_and_its_alt_text(): void
    {
        $room = $this->room();
        $this->attachImage($room, 'cover', 'Foto contoh kamar kelas satu');

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('alt="Foto contoh kamar kelas satu"', false)
            ->assertSee('/thumbs/', false);
    }

    // --- badge (F4B-R9) -----------------------------------------------------------------------------------------------

    public function test_the_badge_says_available_with_icon_and_color_when_beds_remain(): void
    {
        $this->room(['beds_available' => 1]);

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('<span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>', false)
            ->assertDontSee('text-bg-danger', false)
            ->assertDontSee('Terbatas');
    }

    public function test_the_badge_says_full_with_icon_and_color_when_no_bed_remains(): void
    {
        $this->room(['beds_available' => 0]);

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('<span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>', false)
            ->assertSee('0 dari 20 tempat tidur tersedia')
            ->assertDontSee('text-bg-success', false);
    }

    public function test_the_badge_is_available_when_every_bed_is_free(): void
    {
        $this->room(['bed_capacity' => 8, 'beds_available' => 8]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('8 dari 8 tempat tidur tersedia')
            ->assertSee('bi-check-circle', false);
    }

    // --- pembaruan manual dan kedaluwarsa (F4B-R10, BR-114) ---------------------------------------------------------------

    public function test_a_never_updated_category_says_so_and_is_marked_stale(): void
    {
        $this->room(['availability_updated_at' => null]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Belum diperbarui')
            ->assertSee(self::STALE)
            ->assertSee('text-warning-emphasis', false)
            ->assertDontSee('Diperbarui manual oleh petugas pada');
    }

    public function test_the_stale_sentence_appears_only_after_exactly_twenty_four_hours(): void
    {
        $this->room(['name' => 'Tepat Dua Puluh Empat', 'slug' => 'tepat-24', 'availability_updated_at' => now()->subHours(24), 'sort_order' => 1]);

        $this->get(route('rooms.index'))->assertOk()->assertDontSee(self::STALE);

        $this->room(['name' => 'Lewat Semenit', 'slug' => 'lewat-semenit', 'availability_updated_at' => now()->subHours(24)->subMinute(), 'sort_order' => 2]);

        $html = (string) $this->get(route('rooms.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, self::STALE));
    }

    public function test_a_fresh_update_shows_no_stale_sentence(): void
    {
        $this->room(['availability_updated_at' => now()->subMinutes(5)]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 09.55 WIB')
            ->assertDontSee(self::STALE);
    }

    public function test_the_rate_says_contact_us_when_there_is_no_sample_rate(): void
    {
        $this->room(['sample_rate' => null]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Tarif contoh: Hubungi kami')
            ->assertDontSee('Rp ');
    }

    public function test_pages_never_use_the_words_real_time_live_or_directly(): void
    {
        $room = $this->room(['beds_available' => 0, 'availability_updated_at' => null]);
        $this->room(['name' => 'Kamar Lain', 'slug' => 'kamar-lain', 'sort_order' => 2]);

        foreach ([route('rooms.index'), $this->showUrl($room)] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $text = strip_tags($html);

            $this->assertStringNotContainsString('real-time', $html);
            $this->assertStringNotContainsString('Real-time', $html);
            $this->assertDoesNotMatchRegularExpression('/real[- ]?time|langsung|\blive\b/i', $text, $url);
        }
    }

    // --- #11: kosong dan mode ketat (EMP-01) ----------------------------------------------------------------------------------

    public function test_an_empty_database_shows_the_empty_state_and_still_the_notice(): void
    {
        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('Belum ada kategori kamar')
            ->assertSee('Informasi kamar rawat inap akan tampil di sini setelah ditambahkan.')
            ->assertSee('bi-building', false)
            ->assertSee('role="status"', false)
            ->assertSee(self::NOTICE)
            ->assertDontSee('room-card', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_only_inactive_categories_also_show_the_empty_state(): void
    {
        $this->room(['name' => 'Hanya Nonaktif', 'is_active' => false]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Belum ada kategori kamar')
            ->assertDontSee('Hanya Nonaktif');
    }

    public function test_many_categories_with_covers_render_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        foreach (range(1, 4) as $number) {
            $room = $this->room(['name' => "Kamar Ketat {$number}", 'slug' => "kamar-ketat-{$number}", 'sort_order' => $number]);
            $this->attachImage($room, 'cover', "Alt kamar {$number}", "r{$number}.jpg");
        }

        $html = (string) $this->get(route('rooms.index'))->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="card h-100 room-card"'));
        $this->assertStringContainsString('alt="Alt kamar 3"', $html);
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }

    // --- menu publik ---------------------------------------------------------------------------------------------------------------

    public function test_the_room_menu_items_are_visible_and_active_on_the_room_pages(): void
    {
        $room = $this->room();
        $href = preg_quote(route('rooms.index'), '#');

        $home = (string) $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item" href="'.$href.'"\s*>Kamar Rawat Inap</a>#', $home));
        $this->assertStringContainsString('<li><a href="'.route('rooms.index').'">Kamar Rawat Inap</a></li>', $home);

        $detail = (string) $this->get($this->showUrl($room))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item active" href="'.$href.'"\s+aria-current="page"\s*>Kamar Rawat Inap</a>#', $detail));
    }

    // --- detail: 404 (Review Focus 3) -------------------------------------------------------------------------------------------------

    public function test_an_inactive_category_is_404_for_everyone_including_staff(): void
    {
        $room = $this->room(['is_active' => false]);
        $url = $this->showUrl($room);

        $this->get($url)->assertNotFound();

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($url)->assertNotFound();
        }
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->get(route('rooms.show', 'tidak-ada'))->assertNotFound();
        $this->get('/kamar-rawat-inap/Tidak%20Ada')->assertNotFound();
        $this->get('/kamar-rawat-inap/123')->assertNotFound();
    }

    public function test_activating_a_category_makes_its_detail_reachable(): void
    {
        $room = $this->room(['name' => 'Segera Dibuka', 'slug' => 'segera-dibuka', 'is_active' => false]);

        $this->get($this->showUrl($room))->assertNotFound();

        $room->forceFill(['is_active' => true])->save();

        $this->get($this->showUrl($room))->assertOk()->assertSee('Segera Dibuka');
    }

    /** @return array<string, array{string, string}> */
    public static function seedSlugs(): array
    {
        return [
            'kelas 1' => ['bpjs-kelas-1', 'BPJS Kelas 1'],
            'kelas 2' => ['bpjs-kelas-2', 'BPJS Kelas 2'],
            'kelas 3' => ['bpjs-kelas-3', 'BPJS Kelas 3'],
            'vip' => ['vip', 'VIP'],
        ];
    }

    #[DataProvider('seedSlugs')]
    public function test_the_four_seed_slugs_share_one_route_and_one_view(string $slug, string $name): void
    {
        $this->room(['name' => $name, 'slug' => $slug]);

        $this->get('/kamar-rawat-inap/'.$slug)
            ->assertOk()
            ->assertSee('<h1 class="mb-1">'.$name.'</h1>', false)
            ->assertSee('<title>'.$name.' — '.config('app.name').'</title>', false);
    }

    // --- detail: isi -----------------------------------------------------------------------------------------------------------------------

    public function test_detail_renders_the_header_notice_breadcrumb_and_back_link(): void
    {
        $room = $this->room();

        $response = $this->get($this->showUrl($room))->assertOk();

        $response->assertSee('<h1 class="mb-1">BPJS Kelas 1</h1>', false)
            ->assertSee('Kamar dua tempat tidur contoh.')
            ->assertSee(self::NOTICE)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Kamar Rawat Inap', 'BPJS Kelas 1'], false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('rooms.index').'">Kamar Rawat Inap</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">BPJS Kelas 1</li>', false)
            ->assertSee('Kembali ke daftar kamar')
            ->assertSee('images/placeholders/room.svg', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_detail_shows_the_summary_panel_with_exact_labels(): void
    {
        $room = $this->room(['rate_note' => 'Belum termasuk tindakan medis.']);

        $response = $this->get($this->showUrl($room))->assertOk();

        $response->assertSee('<h2 class="h5 mb-3">Ringkasan</h2>', false)
            ->assertSee('20 tempat tidur')
            ->assertSee('12 dari 20 tempat tidur tersedia')
            ->assertSee('<span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>', false)
            ->assertSee('Tarif contoh: Rp 400.000 per malam')
            ->assertSee('Belum termasuk tindakan medis.')
            ->assertSee('<time datetime="2026-10-05T08:00:00+07:00">Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.00 WIB</time>', false)
            ->assertDontSee(self::STALE);
    }

    public function test_detail_of_a_never_updated_full_category(): void
    {
        $room = $this->room(['beds_available' => 0, 'availability_updated_at' => null, 'sample_rate' => null]);

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('<span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>', false)
            ->assertSee('Belum diperbarui')
            ->assertSee(self::STALE)
            ->assertSee('Tarif contoh: Hubungi kami')
            ->assertDontSee('<time', false);
    }

    public function test_detail_lists_amenities_and_hides_the_block_when_there_are_none(): void
    {
        $with = $this->room(['amenities' => ['AC', 'Kamar mandi dalam', '<b>Televisi</b>']]);
        $without = $this->room(['name' => 'Tanpa Fasilitas', 'slug' => 'tanpa-fasilitas', 'amenities' => null, 'sort_order' => 2]);
        $empty = $this->room(['name' => 'Fasilitas Kosong', 'slug' => 'fasilitas-kosong', 'amenities' => [], 'sort_order' => 3]);

        $this->get($this->showUrl($with))
            ->assertOk()
            ->assertSee('<h2 id="fasilitas-kamar-judul" class="h4 mb-3">Fasilitas Kamar</h2>', false)
            ->assertSee('<ul class="amenity-list">', false)
            ->assertSeeInOrder(['<i class="bi bi-check2" aria-hidden="true"></i> AC', 'Kamar mandi dalam', '&lt;b&gt;Televisi&lt;/b&gt;'], false)
            ->assertDontSee('<b>Televisi</b>', false);

        foreach ([$without, $empty] as $room) {
            $this->get($this->showUrl($room))
                ->assertOk()
                ->assertDontSee('Fasilitas Kamar')
                ->assertDontSee('amenity-list', false);
        }
    }

    public function test_a_dangerous_description_is_sanitized_and_a_blank_one_renders_no_block(): void
    {
        $dangerous = $this->room(['description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(2)">']);
        $blank = $this->room(['name' => 'Tanpa Deskripsi', 'slug' => 'tanpa-deskripsi', 'description' => '<p><br></p>', 'sort_order' => 2]);

        $this->get($this->showUrl($dangerous))
            ->assertOk()
            ->assertSee('<div class="rich-text room-body"><p>Aman</p>', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('onerror', false);

        $this->get($this->showUrl($blank))->assertOk()->assertDontSee('room-body', false);
    }

    public function test_detail_uses_the_cover_with_its_alt_text(): void
    {
        $room = $this->room();
        $media = $this->attachImage($room, 'cover', 'Foto contoh kamar kelas satu');

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('alt="Foto contoh kamar kelas satu"', false)
            ->assertSee('src="'.$media->url.'"', false);
    }

    // --- detail: tombol --------------------------------------------------------------------------------------------------------------------------

    public function test_the_whatsapp_button_is_hidden_without_a_configured_number(): void
    {
        $room = $this->room();

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertDontSee('wa.me')
            ->assertDontSee('Chat via WhatsApp');
    }

    public function test_the_whatsapp_button_opens_wa_me_in_a_new_tab_when_a_number_is_configured(): void
    {
        $this->seed(SettingsSeeder::class);
        $room = $this->room();

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('href="https://wa.me/6280000000000" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('Chat via WhatsApp (membuka WhatsApp)');
    }

    public function test_the_ask_availability_button_links_to_contact_only_when_the_route_exists(): void
    {
        $room = $this->room();

        if (! Route::has('contact')) {
            $this->get($this->showUrl($room))->assertOk()->assertDontSee('Tanya Ketersediaan');

            $this->fakeRoute('contact', '/_uji/kontak');
        }

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('<a class="btn btn-primary w-100 mb-2" href="'.route('contact').'">Tanya Ketersediaan</a>', false);
    }

    // --- detail: galeri -----------------------------------------------------------------------------------------------------------------------------

    public function test_detail_renders_thumbnails_and_one_modal_carousel(): void
    {
        $room = $this->room();

        foreach ([1, 2, 3] as $number) {
            $this->attachImage($room, 'gallery', "Foto kamar {$number}", "k{$number}.jpg");
        }

        $response = $this->get($this->showUrl($room))->assertOk();
        $html = $response->getContent();

        $response->assertSee('id="galeri-modal"', false)
            ->assertSee('data-gallery-modal', false)
            ->assertSee('aria-labelledby="galeri-modal-judul"', false)
            ->assertSee('Galeri BPJS Kelas 1')
            ->assertSee('Perbesar foto 1 dari 3: Foto kamar 1')
            ->assertSee('Perbesar foto 3 dari 3: Foto kamar 3')
            ->assertSee('Foto sebelumnya')
            ->assertSee('Foto berikutnya');
        $this->assertSame(3, substr_count($html, 'data-gallery-index='));
        $this->assertSame(3, substr_count($html, 'class="carousel-item'));
        $this->assertSame(1, substr_count($html, 'class="carousel-item active"'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_without_a_gallery_there_is_no_modal(): void
    {
        $room = $this->room();
        $this->attachImage($room, 'cover', 'Hanya sampul');

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertDontSee('galeri-modal', false)
            ->assertDontSee('data-gallery-index', false);
    }

    // --- detail: SEO (F4B-R30) -----------------------------------------------------------------------------------------------------------------------------

    public function test_detail_title_and_description_follow_meta_fields_then_name_and_short_description(): void
    {
        $plain = $this->room(['meta_title' => null, 'meta_description' => null]);
        $meta = $this->room(['name' => 'Kamar Meta', 'slug' => 'kamar-meta', 'meta_title' => 'Judul Meta Kamar', 'meta_description' => 'Deskripsi meta kamar.', 'sort_order' => 2]);

        $this->get($this->showUrl($plain))
            ->assertOk()
            ->assertSee('<title>BPJS Kelas 1 — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Kamar dua tempat tidur contoh.">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="BPJS Kelas 1">', false)
            ->assertSee('<meta property="og:description" content="Kamar dua tempat tidur contoh.">', false)
            ->assertSee('<link rel="canonical" href="'.$this->showUrl($plain).'">', false)
            ->assertDontSee('og:image', false)
            ->assertDontSee($this->noindexTag(), false);

        $this->get($this->showUrl($meta))
            ->assertOk()
            ->assertSee('<title>Judul Meta Kamar — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi meta kamar.">', false)
            ->assertSee('<meta property="og:title" content="Judul Meta Kamar">', false);
    }

    public function test_detail_og_image_is_the_absolute_cover_url(): void
    {
        $room = $this->room();
        $media = $this->attachImage($room, 'cover', 'Sampul kamar');

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url($media->url).'">', false);
    }

    public function test_detail_description_is_cut_at_160_characters(): void
    {
        $long = str_repeat('Deskripsi kamar panjang. ', 10);
        $room = $this->room(['short_description' => $long, 'meta_description' => null]);

        $html = (string) $this->get($this->showUrl($room))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)">/', $html, $match));
        $this->assertSame(Str::limit(trim($long), 160, ''), html_entity_decode($match[1]));
    }

    // --- akses dan keamanan markup -----------------------------------------------------------------------------------------------------------------------------

    public function test_pages_are_identical_for_guests_patients_and_staff(): void
    {
        $room = $this->room(['name' => 'Terbuka Untuk Semua']);

        foreach ([null, 'patient', 'content-admin', 'registration-officer', 'super-admin'] as $actor) {
            if ($actor === 'patient') {
                $this->actingAs(User::factory()->patient()->create());
            } elseif ($actor !== null) {
                $this->actingAs($this->createStaff($actor));
            }

            $this->get(route('rooms.index'))->assertOk()->assertSee('Terbuka Untuk Semua');
            $this->get($this->showUrl($room))->assertOk()->assertSee('Terbuka Untuk Semua');
        }
    }

    public function test_the_views_use_escaped_output_and_no_inline_event_handlers(): void
    {
        $room = $this->room(['name' => 'Kamar <b>Tebal</b> Uji', 'short_description' => '<i>Miring</i> ringkas', 'rate_note' => '<u>Catatan</u>']);

        $index = (string) $this->get(route('rooms.index'))->assertOk()->getContent();
        $show = (string) $this->get($this->showUrl($room))->assertOk()->getContent();

        foreach ([$index, $show] as $html) {
            $this->assertStringContainsString('Kamar &lt;b&gt;Tebal&lt;/b&gt; Uji', $html);
            $this->assertStringNotContainsString('<b>Tebal</b>', $html);
            $this->assertStringNotContainsString('<i>Miring</i>', $html);
            $this->assertStringNotContainsString('<u>Catatan</u>', $html);
            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
            $this->assertStringNotContainsString('javascript:', $html);
        }

        foreach (['index', 'show', '_availability-badge', '_notice'] as $view) {
            $source = (string) file_get_contents(resource_path("views/public/rooms/{$view}.blade.php"));

            $this->assertNotSame('', $source, $view);
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source), $view);
        }
    }
}
