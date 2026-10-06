<?php

namespace Tests\Feature\Public;

use App\Models\Gallery;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #20 `galleries.index` dan detail album `galleries.show` (05 hal. 20 dan §6.1, F4A-R5/R13/R23): hanya album
 * terbit; 404 untuk album draf bagi semua aktor termasuk staf; sampul = foto pertama; detail memakai ulang pola modal
 * dan carousel galeri poliklinik.
 */
class PublicGalleriesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** Foto sungguhan lewat MediaService (berkas dan thumbnail ada di disk palsu). */
    private function uploadPhoto(Gallery $gallery, string $alt, ?string $caption = null): void
    {
        $this->app->make(MediaService::class)->store(
            ImageFixtures::jpeg(1200, 800, 'foto.jpg'),
            'photos',
            $gallery,
            ['alt_text' => $alt, 'caption' => $caption],
        );
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    // --- #20: header, SEO, breadcrumb (F4A-R5) ---------------------------------------------------------

    public function test_index_renders_the_default_header_seo_and_breadcrumb(): void
    {
        $this->createGallery(['title' => 'Album Satu']);

        $response = $this->get(route('galleries.index'))->assertOk();

        $response->assertSee('<title>Galeri — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Galeri</h1>', false)
            ->assertSee('Foto-foto contoh')
            ->assertSee('<meta name="description" content="Galeri foto UNPAM HOSPITAL.">', false)
            ->assertSee('<meta property="og:title" content="Galeri">', false)
            ->assertSee('<link rel="canonical" href="'.route('galleries.index').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Galeri'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Galeri</li>', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_index_header_and_seo_follow_the_galleries_index_page_row(): void
    {
        $this->createPage('galleries-index', [
            'title' => 'Album Kami',
            'subtitle' => 'Kegiatan dan fasilitas',
            'meta_title' => 'Album Kami Meta',
            'meta_description' => 'Deskripsi album kami.',
        ]);
        $this->createGallery();

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('<title>Album Kami Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Album Kami</h1>', false)
            ->assertSee('Kegiatan dan fasilitas')
            ->assertSee('<meta name="description" content="Deskripsi album kami.">', false);
    }

    // --- #20: daftar album --------------------------------------------------------------------------------

    public function test_index_lists_published_albums_in_order_with_photo_counts_and_the_first_photo_as_cover(): void
    {
        $b = $this->createGallery(['title' => 'Album B Contoh', 'sort_order' => 2]);
        $a = $this->createGallery(['title' => 'Album A Contoh', 'sort_order' => 1, 'description' => 'Deskripsi singkat album A.']);
        $this->createGallery(['title' => 'Album Draf Contoh', 'sort_order' => 0, 'is_published' => false]);

        $this->addGalleryPhoto($a, ['alt_text' => 'Foto kedua A', 'sort_order' => 3, 'file_name' => 'a-kedua.jpg', 'thumb_path' => 'photos/2026/10/thumbs/a-kedua.jpg', 'directory' => 'photos/2026/10']);
        $this->addGalleryPhoto($a, ['alt_text' => 'Foto pertama A', 'sort_order' => 1, 'file_name' => 'a-pertama.jpg', 'thumb_path' => 'photos/2026/10/thumbs/a-pertama.jpg', 'directory' => 'photos/2026/10']);
        $this->addGalleryPhoto($a, ['alt_text' => 'Foto ketiga A', 'sort_order' => 5, 'file_name' => 'a-ketiga.jpg', 'thumb_path' => 'photos/2026/10/thumbs/a-ketiga.jpg', 'directory' => 'photos/2026/10']);
        $this->addGalleryPhoto($b, ['alt_text' => 'Foto tunggal B', 'sort_order' => 1, 'file_name' => 'b-tunggal.jpg', 'thumb_path' => 'photos/2026/10/thumbs/b-tunggal.jpg', 'directory' => 'photos/2026/10']);

        $response = $this->get(route('galleries.index'))->assertOk();

        $response->assertSeeInOrder(['Album A Contoh', '3 foto', 'Album B Contoh', '1 foto'])
            ->assertSee('Deskripsi singkat album A.')
            ->assertSee('photos/2026/10/thumbs/a-pertama.jpg', false)
            ->assertSee('alt="Foto pertama A"', false)
            ->assertDontSee('a-kedua.jpg', false)
            ->assertDontSee('a-ketiga.jpg', false)
            ->assertDontSee('Album Draf Contoh');
        $this->assertSame(2, substr_count($response->getContent(), 'gallery-card'));
    }

    public function test_each_card_links_to_the_album_by_slug_with_a_single_stretched_link(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Bertaut']);

        $response = $this->get(route('galleries.index'))->assertOk();

        $response->assertSee('href="'.route('galleries.show', $gallery->slug).'"', false)
            ->assertSee('<h2 class="card-title h5 mb-1">', false);
        $this->assertSame(1, substr_count($response->getContent(), 'stretched-link'));
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $response->getContent()));
    }

    public function test_an_album_without_photos_uses_the_gallery_placeholder_and_zero_photos(): void
    {
        $this->createGallery(['title' => 'Album Kosong Foto']);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('images/placeholders/gallery.svg', false)
            ->assertSee('0 foto')
            ->assertDontSee('onerror', false);
    }

    public function test_an_empty_index_shows_the_empty_state_and_noindex(): void
    {
        $this->createGallery(['title' => 'Hanya Draf', 'is_published' => false]);

        $response = $this->get(route('galleries.index'))->assertOk();

        $response->assertSee('Belum ada album galeri')
            ->assertSee('Album foto akan tampil di sini setelah diterbitkan.')
            ->assertSee($this->noindexTag(), false)
            ->assertDontSee('Hanya Draf')
            ->assertDontSee('gallery-card');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_card_description_is_escaped_and_shortened(): void
    {
        $this->createGallery(['title' => 'Album Deskripsi', 'description' => '<b>Tebal</b> '.str_repeat('panjang ', 40)]);

        $response = $this->get(route('galleries.index'))->assertOk();

        $response->assertSee('&lt;b&gt;Tebal&lt;/b&gt;', false)
            ->assertDontSee('<b>Tebal</b>', false);
        $this->assertStringNotContainsString(str_repeat('panjang ', 40), $response->getContent());
    }

    // --- #20: paginasi dua belas (F4A-R13) ---------------------------------------------------------------------

    public function test_the_index_is_paginated_by_twelve_and_page_two_adds_the_page_to_the_title(): void
    {
        foreach (range(1, 13) as $number) {
            $this->createGallery(['title' => sprintf('Album Nomor %02d', $number), 'sort_order' => $number]);
        }

        $first = (string) $this->get(route('galleries.index'))->assertOk()->getContent();
        $second = (string) $this->get(route('galleries.index', ['page' => 2]))->assertOk()->getContent();

        $this->assertSame(12, substr_count($first, 'gallery-card'));
        $this->assertStringContainsString('Album Nomor 12', $first);
        $this->assertStringNotContainsString('Album Nomor 13', $first);
        $this->assertStringContainsString('<title>Galeri — '.config('app.name').'</title>', $first);

        $this->assertSame(1, substr_count($second, 'gallery-card'));
        $this->assertStringContainsString('Album Nomor 13', $second);
        $this->assertStringContainsString('<title>Galeri — Halaman 2 — '.config('app.name').'</title>', $second);
    }

    /** @return array<string, array{string}> */
    public static function invalidPages(): array
    {
        return [
            'bukan angka' => ['page=abc'],
            'nol' => ['page=0'],
            'negatif' => ['page=-3'],
            'array' => ['page[]=1'],
            'array asosiatif' => ['page[a]=b'],
            'per_page tidak dibuka' => ['per_page=500'],
            'parameter asing' => ['kategori=x&q=y'],
        ];
    }

    #[DataProvider('invalidPages')]
    public function test_an_invalid_page_parameter_falls_back_to_the_first_page(string $query): void
    {
        foreach (range(1, 13) as $number) {
            $this->createGallery(['title' => sprintf('Album Nomor %02d', $number), 'sort_order' => $number]);
        }

        $response = $this->get('/galeri?'.$query)->assertOk();

        $this->assertSame(12, substr_count($response->getContent(), 'gallery-card'));
        $response->assertSee('Album Nomor 01')->assertDontSee($this->noindexTag(), false);
    }

    public function test_a_page_beyond_the_last_one_shows_a_notice_and_is_noindex(): void
    {
        $this->createGallery(['title' => 'Satu Album']);

        $this->get(route('galleries.index', ['page' => 9]))
            ->assertOk()
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1')
            ->assertSee($this->noindexTag(), false)
            ->assertDontSee('Belum ada album galeri');
    }

    public function test_the_index_renders_many_albums_with_photos_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        foreach (range(1, 13) as $number) {
            $gallery = $this->createGallery(['title' => "Album Ketat {$number}", 'sort_order' => $number]);

            foreach (range(1, 3) as $photo) {
                $this->addGalleryPhoto($gallery, ['alt_text' => "Foto {$number}.{$photo}", 'sort_order' => $photo]);
            }
        }

        $html = (string) $this->get(route('galleries.index'))->assertOk()->getContent();

        $this->assertSame(12, substr_count($html, 'gallery-card'));
        $this->assertSame(12, substr_count($html, '3 foto'));
        $this->assertStringContainsString('alt="Foto 5.1"', $html);
    }

    // --- detail album: 404 ---------------------------------------------------------------------------------------

    public function test_an_unpublished_album_is_404_for_everyone_including_staff(): void
    {
        $draft = $this->createGallery(['title' => 'Album Draf', 'is_published' => false]);
        $this->addGalleryPhoto($draft, ['alt_text' => 'Foto draf']);
        $url = route('galleries.show', $draft->slug);

        $this->get($url)->assertNotFound();

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($url)->assertNotFound();
        }
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->get(route('galleries.show', 'tidak-ada'))->assertNotFound();
        $this->get('/galeri/Tidak%20Ada')->assertNotFound();
    }

    public function test_publishing_an_album_makes_its_detail_reachable(): void
    {
        $gallery = $this->createGallery(['title' => 'Segera Tayang', 'is_published' => false]);

        $this->get(route('galleries.show', $gallery->slug))->assertNotFound();

        $gallery->forceFill(['is_published' => true])->save();

        $this->get(route('galleries.show', $gallery->slug))->assertOk()->assertSee('Segera Tayang');
    }

    // --- detail album: isi dan modal (F4A-R23) ----------------------------------------------------------------------

    public function test_detail_renders_thumbnails_one_modal_carousel_alt_texts_and_captions(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Foto Contoh', 'description' => 'Kumpulan foto contoh.']);
        $this->uploadPhoto($gallery, 'Alt satu', 'Keterangan satu');
        $this->uploadPhoto($gallery, 'Alt dua', 'Keterangan dua');
        $this->uploadPhoto($gallery, 'Alt tiga');

        $response = $this->get(route('galleries.show', $gallery->slug))->assertOk();
        $html = $response->getContent();

        $response->assertSee('<h1 class="mb-1">Album Foto Contoh</h1>', false)
            ->assertSee('Kumpulan foto contoh.')
            ->assertSee('3 foto')
            ->assertSee('id="galeri-modal"', false)
            ->assertSee('data-gallery-modal', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-labelledby="galeri-modal-judul"', false)
            ->assertSee('Perbesar foto 1 dari 3: Alt satu')
            ->assertSee('Perbesar foto 3 dari 3: Alt tiga')
            ->assertSee('alt="Alt dua"', false)
            ->assertSee('Keterangan satu')
            ->assertSee('Keterangan dua')
            ->assertSee('Foto sebelumnya')
            ->assertSee('Foto berikutnya');
        $this->assertSame(3, substr_count($html, 'data-gallery-index='));
        $this->assertSame(3, substr_count($html, 'class="carousel-item'));
        $this->assertSame(1, substr_count($html, 'class="carousel-item active"'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('/thumbs/', $html);
    }

    public function test_detail_orders_photos_by_sort_order_then_id(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Urutan']);
        $this->addGalleryPhoto($gallery, ['alt_text' => 'Terakhir', 'sort_order' => 9]);
        $this->addGalleryPhoto($gallery, ['alt_text' => 'Pertama', 'sort_order' => 1]);
        $this->addGalleryPhoto($gallery, ['alt_text' => 'Tengah', 'sort_order' => 4]);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSeeInOrder(['Perbesar foto 1 dari 3: Pertama', 'Perbesar foto 2 dari 3: Tengah', 'Perbesar foto 3 dari 3: Terakhir']);
    }

    public function test_detail_escapes_captions_and_description(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Aman', 'description' => '<script>alert(1)</script>Deskripsi']);
        $this->addGalleryPhoto($gallery, ['alt_text' => 'Alt <b>aman</b>', 'caption' => '<i>Miring</i> keterangan']);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('&lt;i&gt;Miring&lt;/i&gt; keterangan', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;Deskripsi', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('<i>Miring</i>', false);
    }

    public function test_an_album_without_photos_shows_an_empty_state_and_no_modal(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Belum Berisi']);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('Album ini belum memiliki foto')
            ->assertDontSee('galeri-modal', false)
            ->assertDontSee('data-gallery-index', false);
    }

    /**
     * `x-image` selalu menulis `data-fallback`; berkas yang hilang di disk ditangani JS (`recheckBrokenImages` dan
     * listener `error`) dan diuji manual, bukan di tes Feature ini.
     */
    public function test_a_photo_keeps_the_alt_and_exposes_the_placeholder_fallback_attribute(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Atribut Cadangan']);
        $this->uploadPhoto($gallery, 'Foto dengan atribut cadangan');

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('alt="Foto dengan atribut cadangan"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/gallery.svg').'"', false)
            ->assertDontSee('onerror', false);
    }

    public function test_detail_has_the_breadcrumb_and_a_back_link(): void
    {
        $gallery = $this->createGallery(['title' => 'Jejak Album']);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Galeri', 'Jejak Album'], false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('galleries.index').'">Galeri</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Jejak Album</li>', false)
            ->assertSee('Kembali ke galeri');
    }

    // --- detail album: SEO (F4A-R5) ----------------------------------------------------------------------------------

    public function test_detail_title_and_description_follow_the_album(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Judul Contoh', 'description' => 'Deskripsi album contoh.']);
        $this->uploadPhoto($gallery, 'Sampul album');

        $first = $gallery->media()->first();

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('<title>Album Judul Contoh — Galeri — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi album contoh.">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="Album Judul Contoh — Galeri">', false)
            ->assertSee('<meta property="og:description" content="Deskripsi album contoh.">', false)
            ->assertSee('<meta property="og:image" content="'.url($first->url).'">', false)
            ->assertSee('<link rel="canonical" href="'.route('galleries.show', $gallery->slug).'">', false)
            ->assertDontSee($this->noindexTag(), false);
    }

    public function test_detail_description_is_cut_at_160_characters(): void
    {
        $long = str_repeat('Deskripsi album panjang. ', 20);
        $gallery = $this->createGallery(['title' => 'Album Panjang', 'description' => $long]);

        $html = (string) $this->get(route('galleries.show', $gallery->slug))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)">/', $html, $match));
        $this->assertSame(Str::limit($long, 160, ''), html_entity_decode($match[1]));
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($match[1])));
    }

    public function test_detail_description_falls_back_to_the_galleries_index_page_description(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Tanpa Deskripsi', 'description' => null]);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('<meta name="description" content="Galeri foto UNPAM HOSPITAL.">', false)
            ->assertDontSee('og:image', false);

        $this->createPage('galleries-index', ['meta_description' => 'Deskripsi galeri dari admin.']);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('<meta name="description" content="Deskripsi galeri dari admin.">', false);
    }

    public function test_detail_renders_many_photos_in_strict_mode_and_without_inline_handlers(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $gallery = $this->createGallery(['title' => 'Album Banyak Foto']);

        foreach (range(1, 20) as $number) {
            $this->addGalleryPhoto($gallery, ['alt_text' => "Foto banyak {$number}", 'sort_order' => $number]);
        }

        $html = (string) $this->get(route('galleries.show', $gallery->slug))->assertOk()->getContent();

        $this->assertSame(20, substr_count($html, 'data-gallery-index='));
        $this->assertSame(20, substr_count($html, 'class="carousel-item'));
        $this->assertStringContainsString('Perbesar foto 20 dari 20: Foto banyak 20', $html);
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));
    }

    public function test_a_single_photo_album_has_no_carousel_controls(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Satu Foto']);
        $this->addGalleryPhoto($gallery, ['alt_text' => 'Satu-satunya']);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('1 foto')
            ->assertDontSee('Foto sebelumnya')
            ->assertDontSee('Foto berikutnya');
    }
}
