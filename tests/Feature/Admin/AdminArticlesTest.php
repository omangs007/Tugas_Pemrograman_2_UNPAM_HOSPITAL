<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #39, tab Artikel (T8, V-32, F4A-R10/R11): CRUD, draf/terbit/terjadwal, penulis, sanitasi isi,
 * cover, soft delete, dan slug yang tidak dipakai ulang.
 */
class AdminArticlesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    private ArticleCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Senin, 5 Oktober 2026 pukul 10.00 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
        $this->category = $this->createArticleCategory(['name' => 'Kesehatan Umum']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Menjaga Kesehatan Jantung Sejak Dini',
            'article_category_id' => (string) $this->category->id,
            'excerpt' => 'Kebiasaan sederhana untuk menjaga kesehatan jantung.',
            'body' => '<p>'.str_repeat('Isi artikel yang cukup panjang. ', 5).'</p>',
            'status' => 'draft',
            'published_at' => '',
            'meta_title' => '',
            'meta_description' => '',
        ], $overrides);
    }

    /**
     * @return list<string>
     */
    private function cells(string $html, string $label): array
    {
        preg_match_all('/data-label="'.preg_quote($label, '/').'">\s*(.*?)\s*<\/td>/s', $html, $matches);

        return array_map(
            fn (string $cell): string => preg_replace('/\s+/', ' ', trim(html_entity_decode(strip_tags($cell)))) ?? '',
            $matches[1],
        );
    }

    // --- Akses (AKS #39) ----------------------------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'admin konten' => ['content-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_access_per_staff_role(string $role, int $status): void
    {
        $article = $this->createArticle([], $this->category);
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.articles.index'))->assertStatus($status);
        $this->get(route('admin.articles.create'))->assertStatus($status);
        $this->get(route('admin.articles.edit', $article))->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $article = $this->createArticle([], $this->category);

        $this->get(route('admin.articles.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.articles.edit', $article))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.articles.index'))
            ->assertForbidden();
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $article = $this->createArticle([], $this->category);

        $this->assertSame(url('/admin/konten/artikel'), route('admin.articles.index'));
        $this->assertSame(url('/admin/konten/artikel/buat'), route('admin.articles.create'));
        $this->assertSame(url('/admin/konten/artikel/'.$article->id.'/ubah'), route('admin.articles.edit', $article));
        $this->assertSame(url('/admin/konten/artikel/'.$article->id), route('admin.articles.update', $article));
        $this->assertFalse(Route::has('admin.articles.show'));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/konten/artikel/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_cannot_store_update_or_destroy(): void
    {
        $article = $this->createArticle(['title' => 'Judul Asli Artikel'], $this->category);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->post(route('admin.articles.store'), $this->payload())->assertForbidden();
        $this->put(route('admin.articles.update', $article), $this->payload(['title' => 'Diubah Oleh Petugas']))->assertForbidden();
        $this->delete(route('admin.articles.destroy', $article))->assertForbidden();

        $this->assertSame(1, Article::withTrashed()->count());
        $this->assertSame('Judul Asli Artikel', $article->fresh()->title);
        $this->assertNull($article->fresh()->deleted_at);
    }

    // --- Daftar -------------------------------------------------------------------------------------------------

    public function test_index_lists_articles_newest_first_with_category_status_date_and_author_in_strict_mode(): void
    {
        $siti = $this->createStaff('content-admin', ['name' => 'Siti Editor']);
        $budi = $this->createStaff('content-admin', ['name' => 'Budi Konten']);
        $other = $this->createArticleCategory(['name' => 'Gaya Hidup']);

        $this->createArticle(['title' => 'Artikel Terbit', 'published_at' => '2026-10-03 09:00:00', 'author_user_id' => $siti->id], $this->category);
        $this->createArticle(['title' => 'Artikel Lama', 'published_at' => '2026-09-01 14:30:00', 'author_user_id' => $siti->id], $this->category);
        $this->createArticle(['title' => 'Artikel Terjadwal', 'published_at' => '2026-10-20 08:00:00', 'author_user_id' => $budi->id], $other);
        $this->createArticle(['title' => 'Artikel Draf', 'status' => 'draft', 'published_at' => null, 'article_category_id' => null, 'author_user_id' => $budi->id]);
        $this->createArticle(['title' => 'Artikel Dihapus', 'published_at' => '2026-10-04 08:00:00', 'author_user_id' => $siti->id], $this->category)->delete();

        $html = $this->actingAs($siti)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertDontSee('Artikel Dihapus')
            ->getContent();

        $this->assertSame(['Artikel Terjadwal', 'Artikel Terbit', 'Artikel Lama', 'Artikel Draf'], $this->cells($html, 'Judul'));
        $this->assertSame(['Gaya Hidup', 'Kesehatan Umum', 'Kesehatan Umum', 'Tanpa kategori'], $this->cells($html, 'Kategori'));
        $this->assertSame(['Terbit Terjadwal', 'Terbit', 'Terbit', 'Draf'], $this->cells($html, 'Status'));
        $this->assertSame(['20 Okt 2026 08.00', '03 Okt 2026 09.00', '01 Sep 2026 14.30', '—'], $this->cells($html, 'Tanggal terbit'));
        $this->assertSame(['Budi Konten', 'Siti Editor', 'Siti Editor', 'Budi Konten'], $this->cells($html, 'Penulis'));
    }

    public function test_index_shows_actions_tabs_breadcrumb_and_add_button(): void
    {
        $article = $this->createArticle(['title' => 'Artikel Satu'], $this->category);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.pages.index').'"', false)
            ->assertSee('href="'.route('admin.articles.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Tambah Artikel')
            ->assertSee('href="'.route('admin.articles.create').'"', false)
            ->assertSee('href="'.route('admin.articles.edit', $article).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.articles.destroy', $article).'"', false);
    }

    public function test_index_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('Belum ada artikel');
    }

    public function test_index_filters_by_status_category_keyword_and_sort(): void
    {
        $gaya = $this->createArticleCategory(['name' => 'Gaya Hidup']);
        $this->createArticle(['title' => 'Artikel Terbit', 'published_at' => '2026-10-03 09:00:00'], $this->category);
        $this->createArticle(['title' => 'Artikel Lama', 'published_at' => '2026-09-01 14:30:00'], $this->category);
        $this->createArticle(['title' => 'Artikel Terjadwal', 'published_at' => '2026-10-20 08:00:00'], $gaya);
        $this->createArticle(['title' => 'Artikel Draf', 'status' => 'draft', 'published_at' => null, 'article_category_id' => null]);
        $admin = $this->createStaff('content-admin');

        $titles = fn (array $query): array => $this->cells(
            $this->actingAs($admin)->get(route('admin.articles.index', $query))->assertOk()->getContent(),
            'Judul',
        );

        $this->assertSame(['Artikel Terjadwal', 'Artikel Terbit', 'Artikel Lama', 'Artikel Draf'], $titles([]));
        $this->assertSame(['Artikel Draf'], $titles(['status' => 'draf']));
        $this->assertSame(['Artikel Terjadwal', 'Artikel Terbit', 'Artikel Lama'], $titles(['status' => 'terbit']));
        $this->assertSame(['Artikel Terbit', 'Artikel Lama'], $titles(['kategori' => $this->category->slug]));
        $this->assertSame(['Artikel Terjadwal'], $titles(['kategori' => $gaya->slug]));
        $this->assertSame(['Artikel Lama'], $titles(['q' => 'lama']));
        $this->assertSame(['Artikel Draf', 'Artikel Lama', 'Artikel Terbit', 'Artikel Terjadwal'], $titles(['urut' => 'judul']));
        $this->assertSame(['Artikel Terjadwal', 'Artikel Terbit', 'Artikel Lama', 'Artikel Draf'], $titles(['urut' => '-judul']));
        $this->assertSame(['Artikel Draf', 'Artikel Lama', 'Artikel Terbit', 'Artikel Terjadwal'], $titles(['urut' => '-terbaru']), 'Urutan menaik menempatkan tanggal kosong lebih dulu.');
    }

    public function test_index_search_escapes_like_characters(): void
    {
        $this->createArticle(['title' => 'Diskon 50% Pemeriksaan'], $this->category);
        $this->createArticle(['title' => 'Artikel Biasa Saja'], $this->category);
        $admin = $this->createStaff('content-admin');

        $html = $this->actingAs($admin)->get(route('admin.articles.index', ['q' => '%']))->assertOk()->getContent();

        $this->assertSame(['Diskon 50% Pemeriksaan'], $this->cells($html, 'Judul'));
    }

    public function test_index_ignores_invalid_filters_with_a_warning_and_array_params_do_not_cause_a_500(): void
    {
        $this->createArticle(['title' => 'Artikel Satu'], $this->category);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.articles.index', ['status' => 'zzz', 'kategori' => 'tidak-ada', 'urut' => 'kolom']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Artikel Satu');

        $this->get(route('admin.articles.index', ['q' => ['x'], 'kategori' => ['x'], 'status' => ['x'], 'urut' => ['x'], 'page' => ['x']]))
            ->assertOk();
    }

    // --- Form ---------------------------------------------------------------------------------------------------

    public function test_create_form_renders_all_fields_without_a_slug(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('Tambah Artikel')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="article_category_id"', false)
            ->assertSee('Tanpa kategori')
            ->assertSee('Kesehatan Umum')
            ->assertSee('name="excerpt"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('name="body"', false)
            ->assertSee('name="status"', false)
            ->assertSee('Draf')
            ->assertSee('Terbit')
            ->assertSee('type="datetime-local"', false)
            ->assertSee('name="published_at"', false)
            ->assertSee('Kosong = sekarang saat diterbitkan.')
            ->assertSee('name="cover"', false)
            ->assertSee('name="cover_alt"', false)
            ->assertSee('name="meta_title"', false)
            ->assertSee('name="meta_description"', false)
            ->assertDontSee('name="slug"', false)
            ->assertSee('action="'.route('admin.articles.store').'"', false);
    }

    public function test_edit_form_shows_current_values_author_address_and_current_cover(): void
    {
        $author = $this->createStaff('content-admin', ['name' => 'Siti Editor']);
        $article = $this->createArticle([
            'title' => 'Artikel Lama',
            'excerpt' => 'Ringkasan lama.',
            'body' => '<p>Isi lama artikel yang cukup panjang untuk contoh.</p>',
            'published_at' => '2026-10-03 09:15:00',
            'author_user_id' => $author->id,
            'meta_title' => 'Meta lama',
        ], $this->category);
        $this->createMediaRow([
            'model_type' => Article::class,
            'model_id' => $article->id,
            'collection' => 'cover',
            'alt_text' => 'Alt cover lama',
        ]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertSee('Ubah Artikel Lama')
            ->assertSee('value="Artikel Lama"', false)
            ->assertSee('Ringkasan lama.')
            ->assertSee('Isi lama artikel yang cukup panjang untuk contoh.')
            ->assertSee('value="2026-10-03T09:15"', false)
            ->assertSee('value="Meta lama"', false)
            ->assertSee('value="Alt cover lama"', false)
            ->assertSee('Gambar saat ini')
            ->assertSee('Siti Editor')
            ->assertSee('/artikel/'.$article->slug)
            ->assertSee('action="'.route('admin.articles.update', $article).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    // --- Tambah --------------------------------------------------------------------------------------------------

    public function test_content_admin_saves_a_draft_with_the_author_slug_and_audit_row(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.articles.index'))
            ->assertSessionHas('success', 'Artikel berhasil ditambahkan.');

        $article = Article::query()->sole();
        $this->assertSame('Menjaga Kesehatan Jantung Sejak Dini', $article->title);
        $this->assertSame('menjaga-kesehatan-jantung-sejak-dini', $article->slug);
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertNull($article->published_at);
        $this->assertSame($this->category->id, $article->article_category_id);
        $this->assertSame($admin->id, $article->author_user_id);
        $this->assertSame('Kebiasaan sederhana untuk menjaga kesehatan jantung.', $article->excerpt);
        $this->assertStringContainsString('Isi artikel yang cukup panjang.', $article->body);
        $this->assertNull($article->meta_title);
        $this->assertNull($article->meta_description);
        $this->assertFalse($article->isPubliclyVisible());
        $this->assertNotContains($article->id, Article::query()->published()->pluck('id')->all());

        $audit = AuditLog::query()->where('action', 'article.created')->sole();
        $this->assertSame('Artikel "Menjaga Kesehatan Jantung Sejak Dini" ditambahkan.', $audit->description);
        $this->assertSame($admin->id, $audit->user_id);
    }

    public function test_publishing_without_a_date_fills_published_at_with_now(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.articles.store'), $this->payload(['status' => 'published', 'published_at' => '']))
            ->assertSessionHasNoErrors();

        $article = Article::query()->sole();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertSame('2026-10-05 10:00:00', $article->published_at?->format('Y-m-d H:i:s'));
        $this->assertTrue($article->isPubliclyVisible());
        $this->assertFalse($article->isScheduled());
        $this->assertContains($article->id, Article::query()->published()->pluck('id')->all());
    }

    public function test_a_future_published_at_makes_the_article_scheduled_and_not_yet_public(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), $this->payload(['status' => 'published', 'published_at' => '2026-10-20T08:30']))
            ->assertSessionHasNoErrors();

        $article = Article::query()->sole();
        $this->assertSame('2026-10-20 08:30', $article->published_at?->format('Y-m-d H:i'));
        $this->assertTrue($article->isScheduled());
        $this->assertFalse($article->isPubliclyVisible());
        $this->assertNotContains($article->id, Article::query()->published()->pluck('id')->all());

        $this->get(route('admin.articles.index'))->assertSee('Terjadwal');
    }

    public function test_the_published_at_input_is_stored_in_the_application_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.articles.store'), $this->payload(['status' => 'published', 'published_at' => '2026-10-01T09:30']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('articles', ['slug' => 'menjaga-kesehatan-jantung-sejak-dini', 'published_at' => '2026-10-01 09:30:00']);
    }

    public function test_an_article_may_have_no_category(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.articles.store'), $this->payload(['article_category_id' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Article::query()->sole()->article_category_id);
    }

    public function test_slug_author_and_unknown_fields_are_not_mass_assignable_from_the_form(): void
    {
        $admin = $this->createStaff('content-admin');
        $other = $this->createStaff('super-admin');

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), $this->payload(['slug' => 'dipaksa', 'author_user_id' => (string) $other->id, 'id' => 999]))
            ->assertSessionHasNoErrors();

        $article = Article::query()->sole();
        $this->assertSame('menjaga-kesehatan-jantung-sejak-dini', $article->slug);
        $this->assertSame($admin->id, $article->author_user_id);
        $this->assertNotSame(999, $article->id);
    }

    // --- Isi artikel (Review Focus 2) -------------------------------------------------------------------------------

    public function test_hostile_body_is_sanitized_when_saved_through_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.articles.store'), $this->payload([
                'body' => '<p style="color:red" class="x">Paragraf aman yang cukup panjang untuk lolos batas minimal.</p><script>alert(1)</script><img src="/storage/x.jpg" alt="ok" onerror="alert(1)"><a href="javascript:alert(1)">klik</a><iframe src="https://evil.test"></iframe>',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.articles.index'));

        $body = (string) Article::query()->sole()->body;

        $this->assertStringContainsString('Paragraf aman', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('style=', $body);
        $this->assertStringNotContainsString('class=', $body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function bodiesThatAreBlankAfterSanitizing(): array
    {
        return [
            'spasi tak terpisah berulang' => [str_repeat('<p>&nbsp;</p>', 6)],
            'hanya script dan paragraf kosong' => ['<script>alert("xss")</script>'.str_repeat('<p><br></p>', 3)],
            'hanya iframe dan paragraf kosong' => ['<iframe src="https://evil.test"></iframe>'.str_repeat('<p><br></p>', 3)],
        ];
    }

    #[DataProvider('bodiesThatAreBlankAfterSanitizing')]
    public function test_a_body_that_is_blank_after_sanitizing_is_a_validation_error_not_a_500(string $body): void
    {
        $this->assertGreaterThanOrEqual(50, mb_strlen($body), 'Isi uji harus lolos batas minimal 50 karakter mentah.');

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), $this->payload(['body' => $body]))
            ->assertRedirect(route('admin.articles.create'))
            ->assertSessionHasErrors(['body' => 'Isi artikel wajib diisi.']);

        $this->assertSame(0, Article::withTrashed()->count());
    }

    public function test_a_blank_body_is_also_rejected_on_update_and_the_old_body_stays(): void
    {
        $article = $this->createArticle(['body' => '<p>Isi lama yang tetap harus tersimpan apa adanya.</p>'], $this->category);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), $this->payload(['body' => str_repeat('<p>&nbsp;</p>', 6)]))
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHasErrors(['body' => 'Isi artikel wajib diisi.']);

        $this->assertStringContainsString('Isi lama yang tetap harus tersimpan apa adanya.', (string) $article->fresh()->body);
    }

    // --- Validasi lewat HTTP ----------------------------------------------------------------------------------------

    /**
     * Pesan `null` = cukup ada galat pada field itu (pesan persisnya bergantung pada aturan Laravel mana yang gagal lebih dulu).
     *
     * @return array<string, array{array<string, mixed>, string, string|null}>
     */
    public static function invalidInputs(): array
    {
        return [
            'judul kosong' => [['title' => ''], 'title', 'Judul artikel wajib diisi.'],
            'judul terlalu pendek' => [['title' => 'Abcd'], 'title', 'Judul artikel minimal 5 karakter.'],
            'kategori tidak tersedia' => [['article_category_id' => '999999'], 'article_category_id', 'Kategori artikel yang dipilih tidak tersedia.'],
            'ringkasan kosong' => [['excerpt' => ''], 'excerpt', 'Ringkasan artikel wajib diisi.'],
            'ringkasan terlalu panjang' => [['excerpt' => str_repeat('a', 301)], 'excerpt', 'Ringkasan artikel maksimal 300 karakter.'],
            'isi kosong' => [['body' => ''], 'body', 'Isi artikel wajib diisi.'],
            'isi terlalu pendek' => [['body' => '<p>Pendek</p>'], 'body', 'Isi artikel minimal 50 karakter.'],
            'status kosong' => [['status' => ''], 'status', 'Status artikel wajib dipilih.'],
            'status tidak valid' => [['status' => 'diarsipkan'], 'status', 'Status artikel tidak valid.'],
            'tanggal terbit tidak valid' => [['published_at' => 'kapan-saja'], 'published_at', 'Format tanggal terbit tidak valid.'],
            'meta title terlalu panjang' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description terlalu panjang' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt cover wajib' => [['cover' => 'FILE', 'cover_alt' => ''], 'cover_alt', 'Teks alternatif gambar sampul wajib diisi.'],
            'cover terlalu kecil' => [['cover' => 'SMALL', 'cover_alt' => 'x'], 'cover', 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'cover lebih dari 2 MB' => [['cover' => 'BIG', 'cover_alt' => 'x'], 'cover', 'Ukuran gambar maksimal 2 MB.'],
            'cover SVG berskrip' => [['cover' => 'SVG', 'cover_alt' => 'x'], 'cover', null],
            'cover teks bernama jpg' => [['cover' => 'TEXT', 'cover_alt' => 'x'], 'cover', null],
            'cover PHP berkepala JPEG' => [['cover' => 'PHP', 'cover_alt' => 'x'], 'cover', null],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_and_stores_nothing(array $override, string $field, ?string $message): void
    {
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), $this->payload($override))
            ->assertRedirect(route('admin.articles.create'))
            ->assertSessionHasErrors($field);

        $errors = session('errors')->get($field);

        if ($message === null) {
            $this->assertNotEmpty($errors);
        } else {
            $this->assertContains($message, $errors);
        }

        $this->assertSame(0, Article::withTrashed()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), $this->payload(['title' => '', 'excerpt' => 'Ringkasan Kiriman']))
            ->assertRedirect(route('admin.articles.create'));

        $this->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Judul artikel wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Ringkasan Kiriman');
    }

    // --- Cover (Review Focus 4) ---------------------------------------------------------------------------------------

    public function test_cover_upload_on_create_stores_media_for_the_article(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.articles.store'), $this->payload([
                'cover' => ImageFixtures::jpeg(1200, 675, 'cover.jpg'),
                'cover_alt' => 'Ilustrasi jantung sehat',
            ]))
            ->assertSessionHasNoErrors();

        $article = Article::query()->sole();
        $media = Media::query()->sole();

        $this->assertSame('cover', $media->collection);
        $this->assertSame(Article::class, $media->model_type);
        $this->assertSame($article->id, $media->model_id);
        $this->assertSame('Ilustrasi jantung sehat', $media->alt_text);
        $this->assertStringStartsWith('cover/'.now()->format('Y/m'), $media->directory);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_replacing_the_cover_removes_the_old_file_after_commit(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.articles.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 675, 'lama.jpg'),
            'cover_alt' => 'Cover lama',
        ]));
        $article = Article::query()->sole();
        $old = Media::query()->sole();
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->put(route('admin.articles.update', $article), $this->payload([
            'cover' => ImageFixtures::jpeg(1000, 600, 'baru.jpg'),
            'cover_alt' => 'Cover baru',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.articles.index'));

        $new = Media::query()->sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Cover baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_cover_keeps_it_and_a_changed_alt_updates_the_existing_cover(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.articles.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 675),
            'cover_alt' => 'Alt lama',
        ]));
        $article = Article::query()->sole();
        $media = Media::query()->sole();

        $this->put(route('admin.articles.update', $article), $this->payload(['cover_alt' => '  Alt baru  ']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.articles.index'));

        $same = Media::query()->sole();
        $this->assertSame($media->id, $same->id);
        $this->assertSame('Alt baru', $same->alt_text);
    }

    public function test_update_with_an_empty_cover_alt_is_rejected_while_a_cover_exists(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.articles.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 675),
            'cover_alt' => 'Alt lama',
        ]));
        $article = Article::query()->sole();

        $this->put(route('admin.articles.update', $article), $this->payload(['title' => 'Judul Tidak Tersimpan', 'cover_alt' => '  ']))
            ->assertSessionHasErrors(['cover_alt' => 'Teks alternatif gambar sampul wajib diisi.']);

        $this->assertSame('Alt lama', Media::query()->sole()->alt_text);
        $this->assertSame('Menjaga Kesehatan Jantung Sejak Dini', $article->fresh()->title);
    }

    public function test_png_with_a_jpg_name_is_accepted_and_stored_with_its_real_extension(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.articles.store'), $this->payload([
                'cover' => ImageFixtures::pngNamedJpg(1200, 675, 'cover.jpg'),
                'cover_alt' => 'Cover PNG',
            ]))
            ->assertSessionHasNoErrors();

        $media = Media::query()->sole();
        $this->assertSame('image/png', $media->mime_type);
        $this->assertStringEndsWith('.png', $media->file_name);
    }

    // --- Ubah --------------------------------------------------------------------------------------------------------

    public function test_update_changes_fields_keeps_the_slug_and_writes_the_audit_row(): void
    {
        $author = $this->createStaff('content-admin');
        $article = $this->createArticle(['title' => 'Judul Lama Artikel', 'slug' => 'judul-lama-artikel', 'author_user_id' => $author->id], $this->category);
        $other = $this->createArticleCategory(['name' => 'Gaya Hidup']);
        $editor = $this->createStaff('content-admin');

        $this->actingAs($editor)
            ->put(route('admin.articles.update', $article), $this->payload([
                'title' => 'Judul Baru Artikel',
                'article_category_id' => (string) $other->id,
                'status' => 'published',
                'published_at' => '2026-10-02T08:00',
                'meta_title' => 'Meta baru',
                'meta_description' => 'Deskripsi meta baru.',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.articles.index'))
            ->assertSessionHas('success', 'Artikel berhasil diperbarui.');

        $article->refresh();
        $this->assertSame('Judul Baru Artikel', $article->title);
        $this->assertSame('judul-lama-artikel', $article->slug, 'Slug tidak berubah saat judul diedit.');
        $this->assertSame($other->id, $article->article_category_id);
        $this->assertSame('Meta baru', $article->meta_title);
        $this->assertSame('Deskripsi meta baru.', $article->meta_description);
        $this->assertSame($author->id, $article->author_user_id, 'Penulis tidak berubah saat diedit staf lain.');

        $audit = AuditLog::query()->where('action', 'article.updated')->sole();
        $this->assertSame('Artikel "Judul Baru Artikel" diperbarui.', $audit->description);
        $this->assertSame($editor->id, $audit->user_id);
    }

    public function test_the_author_cannot_be_forged_through_the_update_form(): void
    {
        $author = $this->createStaff('content-admin');
        $other = $this->createStaff('super-admin');
        $article = $this->createArticle(['author_user_id' => $author->id], $this->category);

        $this->actingAs($other)
            ->put(route('admin.articles.update', $article), $this->payload(['author_user_id' => (string) $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($author->id, $article->fresh()->author_user_id);
    }

    public function test_going_back_to_draft_keeps_the_published_at_date(): void
    {
        $article = $this->createArticle(['status' => 'published', 'published_at' => '2026-10-01 08:00:00'], $this->category);
        $this->actingAs($this->createStaff('content-admin'));

        // Form mengirim kembali tanggal yang tampil.
        $this->put(route('admin.articles.update', $article), $this->payload(['status' => 'draft', 'published_at' => '2026-10-01T08:00']))
            ->assertSessionHasNoErrors();
        $article->refresh();
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertSame('2026-10-01 08:00:00', $article->published_at?->format('Y-m-d H:i:s'));

        // Kolom tanggal dikosongkan oleh admin: draf tidak mengosongkan published_at.
        $this->put(route('admin.articles.update', $article), $this->payload(['status' => 'draft', 'published_at' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01 08:00:00', $article->fresh()->published_at?->format('Y-m-d H:i:s'));
        $this->assertFalse($article->fresh()->isPubliclyVisible());
        $this->assertNotContains($article->id, Article::query()->published()->pluck('id')->all());
    }

    public function test_republishing_with_an_empty_date_sets_published_at_to_now(): void
    {
        $article = $this->createArticle(['status' => 'draft', 'published_at' => '2026-10-01 08:00:00'], $this->category);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.articles.update', $article), $this->payload(['status' => 'published', 'published_at' => '']))
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertSame('2026-10-05 10:00:00', $article->published_at?->format('Y-m-d H:i:s'));
    }

    public function test_an_unchanged_published_at_input_keeps_the_stored_seconds(): void
    {
        $article = $this->createArticle(['status' => 'published', 'published_at' => '2026-10-03 09:15:42'], $this->category);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.articles.update', $article), $this->payload(['status' => 'published', 'published_at' => '2026-10-03T09:15', 'title' => 'Judul Diperbarui Saja']))
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame('2026-10-03 09:15:42', $article->published_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Judul Diperbarui Saja', $article->title);
    }

    public function test_a_changed_published_at_input_is_saved(): void
    {
        $article = $this->createArticle(['status' => 'published', 'published_at' => '2026-10-03 09:15:42'], $this->category);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.articles.update', $article), $this->payload(['status' => 'published', 'published_at' => '2026-10-04T11:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-04 11:00:00', $article->fresh()->published_at?->format('Y-m-d H:i:s'));
    }

    public function test_a_trashed_article_cannot_be_edited_or_updated(): void
    {
        $article = $this->createArticle(['title' => 'Artikel Terhapus'], $this->category);
        $article->delete();
        $this->actingAs($this->createStaff('super-admin'));

        $this->get(route('admin.articles.edit', $article))->assertNotFound();
        $this->put(route('admin.articles.update', $article), $this->payload())->assertNotFound();
        $this->delete(route('admin.articles.destroy', $article))->assertNotFound();
    }

    // --- Hapus (Review Focus 5) --------------------------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_destroy_is_a_soft_delete_that_keeps_the_row_and_writes_the_audit_row(string $role): void
    {
        $article = $this->createArticle(['title' => 'Artikel Akan Dihapus'], $this->category);
        $admin = $this->createStaff($role);

        $this->actingAs($admin)
            ->delete(route('admin.articles.destroy', $article))
            ->assertRedirect(route('admin.articles.index'))
            ->assertSessionHas('success', 'Artikel berhasil dihapus.');

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
        $this->assertSame(1, Article::withTrashed()->count());
        $this->assertSame(0, Article::query()->count());

        $audit = AuditLog::query()->where('action', 'article.deleted')->sole();
        $this->assertSame('Artikel "Artikel Akan Dihapus" dihapus.', $audit->description);
        $this->assertSame($admin->id, $audit->user_id);

        $this->get(route('admin.articles.index'))->assertOk()->assertDontSee('Artikel Akan Dihapus');
    }

    public function test_soft_delete_keeps_the_cover_media_and_files_but_force_delete_removes_them(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.articles.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 675),
            'cover_alt' => 'Cover artikel',
        ]));
        $article = Article::query()->sole();
        $media = Media::query()->sole();
        $paths = [$media->directory.'/'.$media->file_name, $media->thumb_path];

        $this->delete(route('admin.articles.destroy', $article))->assertRedirect(route('admin.articles.index'));

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
        $this->assertSame(1, Media::query()->count(), 'Media artikel yang dihapus lunak dipertahankan.');

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        Article::withTrashed()->findOrFail($article->id)->forceDelete();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_a_deleted_articles_slug_is_not_reused_by_a_new_article_with_the_same_title(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.articles.store'), $this->payload())->assertSessionHasNoErrors();
        $first = Article::query()->sole();
        $this->assertSame('menjaga-kesehatan-jantung-sejak-dini', $first->slug);

        $this->delete(route('admin.articles.destroy', $first))->assertRedirect(route('admin.articles.index'));

        $this->post(route('admin.articles.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.articles.index'));

        $second = Article::query()->sole();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('menjaga-kesehatan-jantung-sejak-dini-2', $second->slug);
        $this->assertSame(2, Article::withTrashed()->count());
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider berjalan sebelum GD siap).
     */
    private function materialize(mixed $value): mixed
    {
        return match ($value) {
            'FILE' => ImageFixtures::jpeg(1200, 675),
            'SMALL' => ImageFixtures::jpeg(700, 400, 'kecil.jpg'),
            'BIG' => ImageFixtures::oversizedJpeg(2100, 1200, 675, 'besar.jpg'),
            'SVG' => ImageFixtures::svgWithScript('cover.svg'),
            'TEXT' => ImageFixtures::textNamedJpg(),
            'PHP' => ImageFixtures::phpWithJpegHeader(),
            default => $value,
        };
    }
}
