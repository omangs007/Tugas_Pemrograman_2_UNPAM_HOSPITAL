<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Http\Requests\Admin\ArticleIndexRequest;
use App\Http\Requests\Admin\ArticleRequest;
use App\Models\Article;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-32 + F4A-R10/R11: aturan dan pesan Indonesia untuk artikel, pemetaan `articleAttributes()`, dan filter daftar.
 */
class ArticleRequestTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFormRequests;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Menjaga Kesehatan Jantung',
            'article_category_id' => '',
            'excerpt' => 'Ringkasan artikel.',
            'body' => '<p>'.str_repeat('Isi artikel yang cukup panjang. ', 3).'</p>',
            'status' => 'draft',
            'published_at' => '',
            'meta_title' => '',
            'meta_description' => '',
            'cover_alt' => 'Cover',
        ], $overrides);
    }

    /**
     * Konteks ubah: `cover` memang opsional di kedua konteks, tetapi konteks ubah memakai model nyata.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function updateErrors(array $data): array
    {
        return $this->validationErrors(ArticleRequest::class, $data, null, ['article' => $this->createArticle()], 'PUT');
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'judul kosong' => [['title' => ''], 'title', 'Judul artikel wajib diisi.'],
            'judul bukan teks' => [['title' => ['a']], 'title', 'Judul artikel harus berupa teks.'],
            'judul terlalu pendek' => [['title' => 'Abcd'], 'title', 'Judul artikel minimal 5 karakter.'],
            'judul terlalu panjang' => [['title' => str_repeat('a', 151)], 'title', 'Judul artikel maksimal 150 karakter.'],
            'kategori bukan angka' => [['article_category_id' => 'abc'], 'article_category_id', 'Kategori artikel tidak valid.'],
            'kategori tidak tersedia' => [['article_category_id' => '999999'], 'article_category_id', 'Kategori artikel yang dipilih tidak tersedia.'],
            'ringkasan kosong' => [['excerpt' => ''], 'excerpt', 'Ringkasan artikel wajib diisi.'],
            'ringkasan bukan teks' => [['excerpt' => ['a']], 'excerpt', 'Ringkasan artikel harus berupa teks.'],
            'ringkasan terlalu panjang' => [['excerpt' => str_repeat('a', 301)], 'excerpt', 'Ringkasan artikel maksimal 300 karakter.'],
            'isi kosong' => [['body' => ''], 'body', 'Isi artikel wajib diisi.'],
            'isi bukan teks' => [['body' => ['a']], 'body', 'Isi artikel harus berupa teks.'],
            'isi terlalu pendek' => [['body' => '<p>Pendek</p>'], 'body', 'Isi artikel minimal 50 karakter.'],
            'isi terlalu panjang' => [['body' => str_repeat('a', 200001)], 'body', 'Isi artikel maksimal 200.000 karakter.'],
            'isi kosong setelah sanitasi (nbsp)' => [['body' => str_repeat('<p>&nbsp;</p>', 6)], 'body', 'Isi artikel wajib diisi.'],
            'isi kosong setelah sanitasi (script)' => [['body' => '<script>alert("xss")</script>'.str_repeat('<p><br></p>', 3)], 'body', 'Isi artikel wajib diisi.'],
            'status kosong' => [['status' => ''], 'status', 'Status artikel wajib dipilih.'],
            'status tidak valid' => [['status' => 'diarsipkan'], 'status', 'Status artikel tidak valid.'],
            'tanggal terbit tidak valid' => [['published_at' => 'kapan-saja'], 'published_at', 'Format tanggal terbit tidak valid.'],
            'meta title bukan teks' => [['meta_title' => ['a']], 'meta_title', 'Meta title harus berupa teks.'],
            'meta title terlalu panjang' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description terlalu panjang' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt cover bukan teks' => [['cover_alt' => ['a']], 'cover_alt', 'Teks alternatif gambar sampul harus berupa teks.'],
            'alt cover terlalu panjang' => [['cover_alt' => str_repeat('a', 256)], 'cover_alt', 'Teks alternatif gambar sampul maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $errors = $this->updateErrors($this->validPayload($override));

        $this->assertArrayHasKey($field, $errors);
        $this->assertSame($message, $errors[$field][0]);
    }

    public function test_a_valid_payload_passes_on_create_and_update(): void
    {
        $this->assertSame([], $this->validationErrors(ArticleRequest::class, $this->validPayload()));
        $this->assertSame([], $this->updateErrors($this->validPayload()));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validVariants(): array
    {
        return [
            'diterbitkan tanpa tanggal' => [['status' => 'published', 'published_at' => '']],
            'terjadwal' => [['status' => 'published', 'published_at' => '2026-10-20T08:30']],
            'draf dengan tanggal' => [['status' => 'draft', 'published_at' => '2026-10-01T08:00']],
            'dengan kategori yang ada' => [['article_category_id' => 'ADA']],
            'batas panjang' => [['title' => str_repeat('a', 150), 'excerpt' => str_repeat('b', 300), 'meta_title' => str_repeat('c', 70), 'meta_description' => str_repeat('d', 160)]],
            'isi 50 karakter tepat' => [['body' => str_repeat('a', 50)]],
            'isi 200.000 karakter tepat' => [['body' => str_repeat('a', 200000)]],
            'isi hanya gambar' => [['body' => '<p><img src="/storage/editor/2026/10/gambar-contoh.jpg" alt="Gambar contoh sebagai isi"></p>']],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('validVariants')]
    public function test_valid_variants_pass(array $override): void
    {
        if (($override['article_category_id'] ?? null) === 'ADA') {
            $override['article_category_id'] = (string) $this->createArticleCategory()->id;
        }

        $this->assertSame([], $this->updateErrors($this->validPayload($override)));
    }

    public function test_a_body_that_has_visible_text_after_sanitizing_is_accepted_even_when_it_contains_hostile_markup(): void
    {
        $body = '<script>alert(1)</script><p onclick="x()">Teks yang tetap terlihat setelah disanitasi.</p>';

        $this->assertSame([], $this->updateErrors($this->validPayload(['body' => $body])));
    }

    // --- articleAttributes() (F4A-R10) -----------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFor(array $data, ?Article $article = null): array
    {
        $routeParameters = $article === null ? [] : ['article' => $article];
        $request = $this->makeFormRequest(ArticleRequest::class, $data, null, $routeParameters, $article === null ? 'POST' : 'PUT');
        $request->validateResolved();

        return $request->articleAttributes();
    }

    public function test_publishing_with_an_empty_date_fills_published_at_with_now(): void
    {
        $attributes = $this->attributesFor($this->validPayload(['status' => 'published', 'published_at' => '']));

        $this->assertSame(ArticleStatus::Published, $attributes['status']);
        $this->assertSame('2026-10-05 10:00:00', $attributes['published_at']->format('Y-m-d H:i:s'));
    }

    public function test_a_given_date_is_passed_through_for_published_and_draft(): void
    {
        $published = $this->attributesFor($this->validPayload(['status' => 'published', 'published_at' => '2026-10-20T08:30']));
        $draft = $this->attributesFor($this->validPayload(['status' => 'draft', 'published_at' => '2026-10-01T08:00']));

        $this->assertSame('2026-10-20T08:30', $published['published_at']);
        $this->assertSame('2026-10-01T08:00', $draft['published_at']);
    }

    public function test_a_draft_without_a_date_does_not_touch_published_at(): void
    {
        $attributes = $this->attributesFor($this->validPayload(['status' => 'draft', 'published_at' => '']));

        $this->assertArrayNotHasKey('published_at', $attributes);
        $this->assertSame(ArticleStatus::Draft, $attributes['status']);
    }

    public function test_a_date_equal_to_the_stored_minute_is_not_written_again(): void
    {
        $article = $this->createArticle(['status' => 'published', 'published_at' => '2026-10-03 09:15:42']);

        $same = $this->attributesFor($this->validPayload(['status' => 'published', 'published_at' => '2026-10-03T09:15']), $article);
        $changed = $this->attributesFor($this->validPayload(['status' => 'published', 'published_at' => '2026-10-03T09:16']), $article);

        $this->assertArrayNotHasKey('published_at', $same);
        $this->assertSame('2026-10-03T09:16', $changed['published_at']);
    }

    public function test_article_attributes_contain_only_the_editable_columns_with_casts(): void
    {
        $category = $this->createArticleCategory();

        $attributes = $this->attributesFor($this->validPayload([
            'article_category_id' => (string) $category->id,
            'meta_title' => '  Meta  ',
            'meta_description' => '   ',
            'slug' => 'dipaksa',
            'author_user_id' => '99',
            'id' => '99',
        ]));

        $this->assertSame([
            'article_category_id' => $category->id,
            'title' => 'Menjaga Kesehatan Jantung',
            'excerpt' => 'Ringkasan artikel.',
            'body' => '<p>'.str_repeat('Isi artikel yang cukup panjang. ', 3).'</p>',
            'status' => ArticleStatus::Draft,
            'meta_title' => 'Meta',
            'meta_description' => null,
        ], $attributes);
    }

    public function test_an_empty_category_becomes_null(): void
    {
        $this->assertNull($this->attributesFor($this->validPayload(['article_category_id' => '']))['article_category_id']);
    }

    public function test_cover_helpers_trim_the_alt_and_return_null_without_a_file(): void
    {
        $request = $this->makeFormRequest(ArticleRequest::class, $this->validPayload(['cover_alt' => '  Cover  ']));

        $this->assertNull($request->coverFile());
        $this->assertSame('Cover', $request->coverAlt());
    }

    public function test_only_staff_with_content_manage_may_use_the_request(): void
    {
        $officer = $this->createStaff('registration-officer');
        $article = $this->createArticle();

        $this->assertTrue($this->makeFormRequest(ArticleRequest::class, [])->authorize());
        $this->assertTrue($this->makeFormRequest(ArticleRequest::class, [], $this->createStaff('super-admin'), ['article' => $article], 'PUT')->authorize());
        $this->assertFalse($this->makeFormRequest(ArticleRequest::class, [], $officer)->authorize());
        $this->assertFalse($this->makeFormRequest(ArticleRequest::class, [], $officer, ['article' => $article], 'PUT')->authorize());

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(ArticleRequest::class, $this->validPayload(), $officer);
    }

    // --- ArticleIndexRequest -------------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $query
     */
    private function indexRequest(array $query): ArticleIndexRequest
    {
        $request = ArticleIndexRequest::create('/_uji/filter', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
    }

    public function test_index_filters_accept_valid_values(): void
    {
        $category = $this->createArticleCategory(['name' => 'Gizi', 'slug' => 'gizi']);

        $request = $this->indexRequest(['q' => 'jantung', 'status' => 'terbit', 'kategori' => $category->slug, 'urut' => '-judul']);

        $this->assertEquals(['q' => 'jantung', 'status' => 'terbit', 'kategori' => 'gizi', 'urut' => '-judul'], $request->filters());
    }

    public function test_index_filters_ignore_invalid_values_without_an_error(): void
    {
        $request = $this->indexRequest(['q' => str_repeat('a', 101), 'status' => 'zzz', 'kategori' => "\0", 'urut' => 'kuota', 'kembali' => 'https://situs-lain.test']);

        $this->assertSame([], $request->filters());
        $this->assertSame('Filter tidak valid dan diabaikan.', session('warning'));
    }

    public function test_index_filters_ignore_array_values(): void
    {
        $request = $this->indexRequest(['q' => ['x'], 'kategori' => ['x'], 'status' => ['x'], 'urut' => ['x']]);

        $this->assertSame([], $request->filters());
    }

    public function test_index_apply_to_filters_and_orders_the_query(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi', 'slug' => 'gizi']);
        $umum = $this->createArticleCategory(['name' => 'Umum', 'slug' => 'umum']);
        $this->createArticle(['title' => 'Bubur Sehat', 'published_at' => '2026-10-01 08:00:00'], $gizi);
        $this->createArticle(['title' => 'Cuci Tangan', 'published_at' => '2026-10-03 08:00:00'], $umum);
        $this->createArticle(['title' => 'Antibiotik 100%', 'status' => 'draft', 'published_at' => null], $umum);

        $titles = fn (array $query): array => $this->indexRequest($query)->applyTo(Article::query())->pluck('title')->all();

        $this->assertSame(['Cuci Tangan', 'Bubur Sehat', 'Antibiotik 100%'], $titles([]));
        $this->assertSame(['Antibiotik 100%'], $titles(['status' => 'draf']));
        $this->assertSame(['Cuci Tangan', 'Bubur Sehat'], $titles(['status' => 'terbit']));
        $this->assertSame(['Bubur Sehat'], $titles(['kategori' => 'gizi']));
        $this->assertSame(['Antibiotik 100%'], $titles(['q' => '100%']));
        $this->assertSame([], $titles(['q' => '%%']));
        $this->assertSame(['Antibiotik 100%', 'Bubur Sehat', 'Cuci Tangan'], $titles(['urut' => 'judul']));
        $this->assertSame(['Cuci Tangan', 'Bubur Sehat', 'Antibiotik 100%'], $titles(['urut' => '-judul']));
    }
}
