<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #39, tab Kategori Artikel (T7, V-33): CRUD, nama unik, slug otomatis, hitungan artikel, dan hapus yang
 * membuat artikel menjadi tanpa kategori (Review Focus 5, juga untuk Super Admin).
 */
class AdminArticleCategoriesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Gizi dan Nutrisi',
            'description' => 'Artikel seputar pola makan seimbang.',
            'sort_order' => '3',
        ], $overrides);
    }

    /**
     * @return list<string>
     */
    private function cells(string $html, string $label): array
    {
        preg_match_all('/data-label="'.preg_quote($label, '/').'">\s*(.*?)\s*<\/td>/s', $html, $matches);

        return array_map(fn (string $cell): string => trim(html_entity_decode(strip_tags($cell))), $matches[1]);
    }

    // --- Akses (AKS #39) ---------------------------------------------------------------------------------

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
        $category = $this->createArticleCategory();
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.article-categories.index'))->assertStatus($status);
        $this->get(route('admin.article-categories.create'))->assertStatus($status);
        $this->get(route('admin.article-categories.edit', $category))->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $category = $this->createArticleCategory();

        $this->get(route('admin.article-categories.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.article-categories.edit', $category))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.article-categories.index'))
            ->assertForbidden();
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $category = $this->createArticleCategory();

        $this->assertSame(url('/admin/konten/kategori-artikel'), route('admin.article-categories.index'));
        $this->assertSame(url('/admin/konten/kategori-artikel/buat'), route('admin.article-categories.create'));
        $this->assertSame(url('/admin/konten/kategori-artikel/'.$category->id.'/ubah'), route('admin.article-categories.edit', $category));
        $this->assertSame(url('/admin/konten/kategori-artikel/'.$category->id), route('admin.article-categories.update', $category));
        $this->assertFalse(Route::has('admin.article-categories.show'));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/konten/kategori-artikel/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_cannot_store_update_or_destroy(): void
    {
        $category = $this->createArticleCategory(['name' => 'Nama Asli']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->post(route('admin.article-categories.store'), $this->payload())->assertForbidden();
        $this->put(route('admin.article-categories.update', $category), $this->payload(['name' => 'Diubah Petugas']))->assertForbidden();
        $this->delete(route('admin.article-categories.destroy', $category))->assertForbidden();

        $this->assertSame(1, ArticleCategory::query()->count());
        $this->assertSame('Nama Asli', $category->fresh()->name);
    }

    // --- Daftar ---------------------------------------------------------------------------------------------

    public function test_index_lists_categories_in_order_with_article_counts_in_strict_mode(): void
    {
        $umum = $this->createArticleCategory(['name' => 'Umum', 'sort_order' => 0, 'description' => null]);
        $gizi = $this->createArticleCategory(['name' => 'Gizi', 'sort_order' => 1, 'description' => 'Pola makan']);
        $anak = $this->createArticleCategory(['name' => 'Anak', 'sort_order' => 2, 'description' => null]);
        $bedah = $this->createArticleCategory(['name' => 'Bedah', 'sort_order' => 2, 'description' => null]);

        $this->createArticle([], $umum);
        $this->createArticle([], $gizi);
        $this->createArticle([], $gizi);
        $this->createArticle(['status' => 'draft', 'published_at' => null], $gizi);
        $this->createArticle([], $gizi)->delete();
        $this->createArticle([], $bedah);

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.article-categories.index'))
            ->assertOk()
            ->assertSee('Pola makan')
            ->assertSee('href="'.route('admin.article-categories.edit', $anak).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.article-categories.destroy', $gizi).'"', false)
            ->assertSee('Artikel di kategori ini akan menjadi tanpa kategori.')
            ->getContent();

        $this->assertSame(['Umum', 'Gizi Pola makan', 'Anak', 'Bedah'], array_map(fn (string $cell): string => preg_replace('/\s+/', ' ', $cell), $this->cells($html, 'Nama')));
        $this->assertSame(['1', '3', '0', '1'], $this->cells($html, 'Jumlah artikel'), 'Artikel terhapus tidak dihitung; draf ikut dihitung.');
        $this->assertSame(['0', '1', '2', '2'], $this->cells($html, 'Urutan'));
    }

    public function test_index_shows_module_tabs_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.article-categories.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.pages.index').'"', false)
            ->assertSee('href="'.route('admin.article-categories.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Kategori Artikel')
            ->assertSee('Tambah Kategori')
            ->assertSee('href="'.route('admin.article-categories.create').'"', false);
    }

    public function test_index_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.article-categories.index'))
            ->assertOk()
            ->assertSee('Belum ada kategori artikel');
    }

    public function test_index_search_matches_the_name_and_escapes_like_characters(): void
    {
        $this->createArticleCategory(['name' => 'Gizi Anak', 'sort_order' => 1]);
        $this->createArticleCategory(['name' => 'Jantung', 'sort_order' => 2]);
        $this->createArticleCategory(['name' => 'Diskon 50% Sehat', 'sort_order' => 3]);
        $admin = $this->createStaff('content-admin');

        $names = fn (array $query): array => array_map(
            fn (string $cell): string => trim(explode("\n", $cell)[0]),
            $this->cells($this->actingAs($admin)->get(route('admin.article-categories.index', $query))->assertOk()->getContent(), 'Nama'),
        );

        $this->assertSame(['Gizi Anak', 'Jantung', 'Diskon 50% Sehat'], $names([]));
        $this->assertSame(['Gizi Anak'], $names(['q' => 'gizi']));
        $this->assertSame(['Diskon 50% Sehat'], $names(['q' => '%']));
        $this->assertSame([], $names(['q' => 'tidak-ada']));
    }

    public function test_index_ignores_invalid_params_without_a_server_error(): void
    {
        $this->createArticleCategory(['name' => 'Gizi Anak']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.article-categories.index', ['q' => str_repeat('a', 101), 'status' => 'zzz', 'urut' => 'kolom']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Gizi Anak');

        $this->get(route('admin.article-categories.index', ['q' => ['x'], 'page' => ['x'], 'per_page' => ['x']]))
            ->assertOk();
    }

    // --- Form ---------------------------------------------------------------------------------------------------

    public function test_create_form_renders_without_a_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.article-categories.create'))
            ->assertOk()
            ->assertSee('Tambah Kategori')
            ->assertSee('name="name"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="sort_order"', false)
            ->assertDontSee('name="slug"', false)
            ->assertSee('action="'.route('admin.article-categories.store').'"', false);
    }

    public function test_edit_form_shows_current_values(): void
    {
        $category = $this->createArticleCategory(['name' => 'Gizi', 'description' => 'Pola makan', 'sort_order' => 4]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.article-categories.edit', $category))
            ->assertOk()
            ->assertSee('Ubah Gizi')
            ->assertSee('value="Gizi"', false)
            ->assertSee('Pola makan')
            ->assertSee('value="4"', false)
            ->assertSee('action="'.route('admin.article-categories.update', $category).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    // --- Tambah dan ubah -----------------------------------------------------------------------------------------

    public function test_content_admin_adds_a_category_with_an_automatic_slug_and_the_audit_row_is_written(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.article-categories.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.article-categories.index'))
            ->assertSessionHas('success', 'Kategori artikel berhasil ditambahkan.');

        $category = ArticleCategory::query()->sole();
        $this->assertSame('Gizi dan Nutrisi', $category->name);
        $this->assertSame('gizi-dan-nutrisi', $category->slug);
        $this->assertSame('Artikel seputar pola makan seimbang.', $category->description);
        $this->assertSame(3, $category->sort_order);

        $audit = AuditLog::query()->where('action', 'article_category.created')->sole();
        $this->assertSame('Kategori artikel "Gizi dan Nutrisi" ditambahkan.', $audit->description);
        $this->assertSame($admin->id, $audit->user_id);
    }

    public function test_optional_fields_default_to_null_and_zero(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.article-categories.store'), ['name' => 'Tanpa Deskripsi', 'description' => '', 'sort_order' => ''])
            ->assertSessionHasNoErrors();

        $category = ArticleCategory::query()->sole();
        $this->assertNull($category->description);
        $this->assertSame(0, $category->sort_order);
    }

    public function test_two_names_with_the_same_slug_source_get_numbered_slugs(): void
    {
        $this->createArticleCategory(['name' => 'Gizi', 'slug' => 'gizi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.article-categories.store'), $this->payload(['name' => 'Gizi!']))
            ->assertSessionHasNoErrors();

        $this->assertSame('gizi-2', ArticleCategory::query()->where('name', 'Gizi!')->sole()->slug);
    }

    public function test_update_changes_fields_and_keeps_the_slug_when_the_name_changes(): void
    {
        $category = $this->createArticleCategory(['name' => 'Nama Lama', 'slug' => 'nama-lama']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.article-categories.update', $category), $this->payload(['name' => 'Nama Baru']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.article-categories.index'))
            ->assertSessionHas('success', 'Kategori artikel berhasil diperbarui.');

        $category->refresh();
        $this->assertSame('Nama Baru', $category->name);
        $this->assertSame('nama-lama', $category->slug, 'Slug tidak berubah saat nama diedit.');
        $this->assertSame(3, $category->sort_order);

        $audit = AuditLog::query()->where('action', 'article_category.updated')->sole();
        $this->assertSame('Kategori artikel "Nama Baru" diperbarui.', $audit->description);
    }

    public function test_a_category_may_be_saved_again_with_its_own_name(): void
    {
        $category = $this->createArticleCategory(['name' => 'Gizi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.article-categories.update', $category), $this->payload(['name' => 'Gizi', 'sort_order' => '9']))
            ->assertSessionHasNoErrors();

        $this->assertSame(9, $category->fresh()->sort_order);
    }

    public function test_duplicate_names_are_rejected_even_with_different_letter_case(): void
    {
        $this->createArticleCategory(['name' => 'Gizi']);
        $other = $this->createArticleCategory(['name' => 'Anak']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.article-categories.store'), $this->payload(['name' => 'Gizi']))
            ->assertSessionHasErrors(['name' => 'Nama kategori sudah digunakan.']);
        $this->post(route('admin.article-categories.store'), $this->payload(['name' => 'gizi']))
            ->assertSessionHasErrors(['name' => 'Nama kategori sudah digunakan.']);
        $this->put(route('admin.article-categories.update', $other), $this->payload(['name' => 'GIZI']))
            ->assertSessionHasErrors(['name' => 'Nama kategori sudah digunakan.']);

        $this->assertSame(2, ArticleCategory::query()->count());
        $this->assertSame('Anak', $other->fresh()->name);
    }

    public function test_html_in_the_description_is_rejected(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.article-categories.create'))
            ->post(route('admin.article-categories.store'), $this->payload(['description' => '<script>alert(1)</script>']))
            ->assertRedirect(route('admin.article-categories.create'))
            ->assertSessionHasErrors(['description' => 'Deskripsi tidak boleh mengandung tag HTML.']);

        $this->assertSame(0, ArticleCategory::query()->count());
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.article-categories.create'))
            ->post(route('admin.article-categories.store'), $this->payload(['name' => '', 'sort_order' => '77']))
            ->assertRedirect(route('admin.article-categories.create'));

        $this->get(route('admin.article-categories.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama kategori wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="77"', false);
    }

    public function test_slug_and_unknown_fields_are_not_mass_assignable_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.article-categories.store'), $this->payload(['slug' => 'dipaksa', 'id' => 999]))
            ->assertSessionHasNoErrors();

        $category = ArticleCategory::query()->sole();
        $this->assertSame('gizi-dan-nutrisi', $category->slug);
        $this->assertNotSame(999, $category->id);
    }

    // --- Hapus (Review Focus 5) -------------------------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_deleting_a_category_leaves_its_articles_without_a_category(string $role): void
    {
        $category = $this->createArticleCategory(['name' => 'Akan Dihapus']);
        $published = $this->createArticle([], $category);
        $draft = $this->createArticle(['status' => 'draft', 'published_at' => null], $category);
        $trashed = $this->createArticle([], $category);
        $trashed->delete();
        $untouched = $this->createArticle([], $this->createArticleCategory(['name' => 'Tetap Ada']));

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.article-categories.destroy', $category))
            ->assertRedirect(route('admin.article-categories.index'))
            ->assertSessionHas('success', 'Kategori artikel berhasil dihapus.');

        $this->assertDatabaseMissing('article_categories', ['id' => $category->id]);
        $this->assertNull($published->fresh()->article_category_id);
        $this->assertNull($draft->fresh()->article_category_id);
        $this->assertNull(Article::withTrashed()->findOrFail($trashed->id)->article_category_id);
        $this->assertNotNull($untouched->fresh()->article_category_id);
        $this->assertSame(4, Article::withTrashed()->count(), 'Tidak ada artikel yang ikut terhapus.');

        $audit = AuditLog::query()->where('action', 'article_category.deleted')->sole();
        $this->assertSame('Kategori artikel "Akan Dihapus" dihapus.', $audit->description);
    }

    public function test_deleting_an_empty_category_works_and_the_list_updates(): void
    {
        $category = $this->createArticleCategory(['name' => 'Kosong']);

        $this->actingAs($this->createStaff('content-admin'))
            ->delete(route('admin.article-categories.destroy', $category))
            ->assertRedirect(route('admin.article-categories.index'));

        $this->get(route('admin.article-categories.index'))
            ->assertOk()
            ->assertDontSee('Kosong')
            ->assertSee('Belum ada kategori artikel');
    }
}
