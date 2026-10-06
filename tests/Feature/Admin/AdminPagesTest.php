<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use App\Support\NavigationMenu;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #39, tab Halaman (T5, F4A-R24/R28): 15 halaman tetap tanpa tambah/hapus, akses per peran (AKS),
 * sanitasi isi (Review Focus 2), audit `page.updated`, dan tab modul `content`.
 */
class AdminPagesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 5 Oktober 2026 pukul 10.30 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 10:30:00'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Profil Rumah Sakit Baru',
            'subtitle' => 'Mengenal kami lebih dekat',
            'body' => '<p>Isi <strong>baru</strong> halaman profil.</p>',
            'meta_title' => 'Profil UNPAM HOSPITAL',
            'meta_description' => 'Profil singkat UNPAM HOSPITAL.',
        ], $overrides);
    }

    // --- Akses (AKS #39) ----------------------------------------------------------------------------

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
    public function test_index_and_edit_access_per_staff_role(string $role, int $status): void
    {
        $page = $this->createPage('about');
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.pages.index'))->assertStatus($status);
        $this->get(route('admin.pages.edit', $page))->assertStatus($status);
    }

    public function test_patient_gets_403(): void
    {
        $page = $this->createPage('about');
        $this->actingAs(User::factory()->patient()->create());

        $this->get(route('admin.pages.index'))->assertForbidden();
        $this->get(route('admin.pages.edit', $page))->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $page = $this->createPage('about');

        $this->get(route('admin.pages.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.pages.edit', $page))->assertRedirect(route('admin.login'));
        $this->put(route('admin.pages.update', $page), $this->payload())->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $page = $this->createPage('about');

        $this->assertSame(url('/admin/konten/halaman'), route('admin.pages.index'));
        $this->assertSame(url('/admin/konten/halaman/'.$page->id.'/ubah'), route('admin.pages.edit', $page));
        $this->assertSame(url('/admin/konten/halaman/'.$page->id), route('admin.pages.update', $page));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/konten/halaman/abc/ubah')
            ->assertNotFound();
    }

    // --- Tidak ada tambah/hapus (CRD-13, Review Focus 5) ----------------------------------------------

    public function test_there_are_no_create_store_destroy_or_show_routes_for_pages(): void
    {
        foreach (['create', 'store', 'destroy', 'show'] as $action) {
            $this->assertFalse(Route::has('admin.pages.'.$action), "admin.pages.{$action} tidak boleh ada.");
        }
    }

    public function test_delete_and_post_to_page_urls_fail_with_405_even_for_super_admin(): void
    {
        $page = $this->createPage('about');
        $this->actingAs($this->createStaff('super-admin'));

        $this->delete('/admin/konten/halaman/'.$page->id)->assertStatus(405);
        $this->post('/admin/konten/halaman', $this->payload())->assertStatus(405);
        $this->get('/admin/konten/halaman/buat')->assertNotFound();

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertSame(1, Page::query()->count());
    }

    public function test_page_policy_never_allows_create_or_delete_for_staff(): void
    {
        $admin = $this->createStaff('content-admin');
        $page = $this->createPage('about');

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Page::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $page));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $page));
        $this->assertFalse(Gate::forUser($admin)->allows('create', Page::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $page));

        $policy = new PagePolicy;
        $this->assertFalse($policy->create($admin));
        $this->assertFalse($policy->delete($admin, $page));
    }

    // --- Daftar ------------------------------------------------------------------------------------------

    public function test_index_lists_the_fifteen_seeded_pages_in_key_order_with_columns_in_strict_mode(): void
    {
        $this->seed(PageSeeder::class);
        $dewi = $this->createStaff('content-admin', ['name' => 'Dewi Editor']);
        $budi = $this->createStaff('content-admin', ['name' => 'Budi Konten']);
        Page::query()->where('key', 'about')->sole()->forceFill(['updated_by' => $dewi->id])->save();
        Page::query()->where('key', 'faq')->sole()->forceFill(['updated_by' => $budi->id])->save();

        $html = $this->actingAs($dewi)
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertSee('Halaman')
            ->assertSee('Alamat')
            ->assertSee('Terakhir diubah')
            ->assertSee('oleh Dewi Editor')
            ->assertSee('oleh Budi Konten')
            ->assertSee('5 Okt 2026 10.30')
            ->getContent();

        preg_match_all('/data-label="Halaman">\s*<span class="fw-semibold">(.*?)<\/span>/s', $html, $titles);
        $expected = array_map(fn (string $key): string => Page::DEFAULTS[$key]['title'], Page::KEYS);
        $this->assertSame($expected, array_map(fn (string $title): string => html_entity_decode($title), $titles[1]));

        preg_match_all('/data-label="Isi">(.*?)<\/td>/s', $html, $bodies);
        $expectedBodies = array_map(fn (string $key): string => in_array($key, Page::KEYS_WITH_BODY, true) ? 'Ya' : '—', Page::KEYS);
        $this->assertSame($expectedBodies, array_map(fn (string $cell): string => trim(html_entity_decode($cell)), $bodies[1]));

        $this->assertSame(15, preg_match_all('#href="'.preg_quote(url('/admin/konten/halaman'), '#').'/\d+/ubah"#', $html));
    }

    public function test_index_shows_the_public_address_and_view_link_only_for_registered_routes(): void
    {
        $this->seed(PageSeeder::class);
        $this->assertTrue(Route::has('home'));
        $this->assertTrue(Route::has('clinics.index'));

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->getContent();

        foreach (Page::ROUTES as $routeName) {
            if (! Route::has($routeName)) {
                continue;
            }

            $this->assertStringContainsString('<code>'.route($routeName, [], false).'</code>', $html, "Alamat {$routeName} tidak tampil.");
            $this->assertStringContainsString('href="'.route($routeName).'"', $html, "Tautan Lihat {$routeName} tidak tampil.");
        }
    }

    public function test_index_has_an_edit_link_per_page_but_no_add_or_delete_controls(): void
    {
        $this->seed(PageSeeder::class);
        $about = Page::query()->where('key', 'about')->sole();
        $privacy = Page::query()->where('key', 'privacy-policy')->sole();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.pages.edit', $about).'"', false)
            ->assertSee('href="'.route('admin.pages.edit', $privacy).'"', false)
            ->assertDontSee('Tambah Halaman')
            ->assertDontSee('data-confirm-action', false)
            ->assertDontSee('Hapus');
    }

    public function test_index_shows_module_tab_breadcrumb_and_sidebar_group(): void
    {
        $this->seed(PageSeeder::class);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertSee('Konten Website')
            ->assertSee('href="'.route('admin.pages.index').'"', false)
            ->assertSee('aria-label="Tab modul"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('Konten');
    }

    // --- Tab modul content ----------------------------------------------------------------------------

    public function test_the_content_module_tabs_follow_the_documented_configuration(): void
    {
        $tabs = config('navigation.modules.content');

        $this->assertSame(
            [
                ['Halaman', 'admin.pages.index'],
                ['Banner', 'admin.banners.index'],
                ['Artikel', 'admin.articles.index'],
                ['Kategori Artikel', 'admin.article-categories.index'],
                ['Galeri', 'admin.galleries.index'],
                ['FAQ', 'admin.faqs.index'],
                ['Mitra Asuransi', 'admin.insurance-partners.index'],
            ],
            array_map(fn (array $tab): array => [$tab['label'], $tab['route']], $tabs),
        );

        foreach ($tabs as $tab) {
            $this->assertSame('content.manage', $tab['permission']);
        }

        $this->assertSame(['admin.pages.*'], $tabs[0]['active']);
    }

    public function test_module_tabs_are_hidden_from_the_registration_officer_and_filtered_by_existing_routes(): void
    {
        $admin = $this->createStaff('content-admin');
        $officer = $this->createStaff('registration-officer');

        $adminLabels = array_column(NavigationMenu::items('modules.content', $admin), 'label');
        $this->assertContains('Halaman', $adminLabels);

        foreach (config('navigation.modules.content') as $tab) {
            $this->assertSame(Route::has($tab['route']), in_array($tab['label'], $adminLabels, true), $tab['label']);
        }

        $this->assertSame([], NavigationMenu::items('modules.content', $officer));
    }

    // --- Edit --------------------------------------------------------------------------------------------

    public function test_edit_form_for_a_page_with_body_shows_the_rich_text_editor_and_a_read_only_key(): void
    {
        $page = $this->createPage('about', ['title' => 'Profil Lama', 'subtitle' => 'Sub lama', 'body' => '<p>Isi lama</p>', 'meta_title' => 'Meta lama']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('Ubah Profil Lama')
            ->assertSee('value="about"', false)
            ->assertSee('readonly', false)
            ->assertSee('value="Profil Lama"', false)
            ->assertSee('value="Sub lama"', false)
            ->assertSee('value="Meta lama"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('name="body"', false)
            ->assertSee('Isi lama')
            ->assertSee('name="meta_description"', false)
            ->assertSee('action="'.route('admin.pages.update', $page).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertDontSee('name="key"', false)
            ->assertDontSee('Halaman ini tidak memiliki isi yang dapat diedit.');
    }

    public function test_edit_form_for_a_page_without_body_has_no_editor_and_shows_the_explanation(): void
    {
        $page = $this->createPage('home');

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('Halaman ini tidak memiliki isi yang dapat diedit.')
            ->assertDontSee('data-rich-text', false)
            ->assertDontSee('name="body"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="subtitle"', false)
            ->assertSee('name="meta_title"', false);
    }

    // --- Update ------------------------------------------------------------------------------------------

    public function test_update_saves_the_fields_sets_updated_by_and_writes_the_audit_row(): void
    {
        $page = $this->createPage('about', ['body' => '<p>Isi lama</p>']);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->put(route('admin.pages.update', $page), $this->payload())
            ->assertRedirect(route('admin.pages.index'))
            ->assertSessionHas('success', 'Halaman berhasil diperbarui.');

        $page->refresh();
        $this->assertSame('Profil Rumah Sakit Baru', $page->title);
        $this->assertSame('Mengenal kami lebih dekat', $page->subtitle);
        $this->assertSame('<p>Isi <strong>baru</strong> halaman profil.</p>', $page->body);
        $this->assertSame('Profil UNPAM HOSPITAL', $page->meta_title);
        $this->assertSame('Profil singkat UNPAM HOSPITAL.', $page->meta_description);
        $this->assertSame($admin->id, $page->updated_by);
        $this->assertSame('about', $page->key);
        $this->assertTrue($page->is_system);

        $audit = AuditLog::query()->where('action', 'page.updated')->sole();
        $this->assertSame($page->id, $audit->auditable_id);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('Halaman "Profil Rumah Sakit Baru" diperbarui.', $audit->description);
        $this->assertSame('[konten diubah]', $audit->new_values['body']);
    }

    public function test_update_may_use_patch_and_redirects_back_to_the_list_with_the_new_title(): void
    {
        $page = $this->createPage('vision-mission');
        $admin = $this->createStaff('super-admin');

        $this->actingAs($admin)
            ->patch(route('admin.pages.update', $page), $this->payload(['title' => 'Visi dan Misi Baru']))
            ->assertRedirect(route('admin.pages.index'));

        $this->get(route('admin.pages.index'))->assertSee('Visi dan Misi Baru');
    }

    public function test_update_of_a_page_without_body_keeps_the_body_untouched(): void
    {
        $page = $this->createPage('home');
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Beranda Baru',
                'subtitle' => '',
                'meta_title' => '',
                'meta_description' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.pages.index'));

        $page->refresh();
        $this->assertSame('Beranda Baru', $page->title);
        $this->assertNull($page->subtitle);
        $this->assertNull($page->body);
        $this->assertNull($page->meta_title);
        $this->assertNull($page->meta_description);
        $this->assertSame($admin->id, $page->updated_by);
    }

    public function test_body_is_rejected_for_a_page_without_body_and_nothing_is_saved(): void
    {
        $page = $this->createPage('home', ['title' => 'Judul Lama']);

        $this->actingAs($this->createStaff('super-admin'))
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), $this->payload(['title' => 'Judul Tidak Tersimpan']))
            ->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHasErrors(['body' => 'Halaman ini tidak memiliki isi yang dapat diedit.']);

        $page->refresh();
        $this->assertSame('Judul Lama', $page->title);
        $this->assertNull($page->body);
        $this->assertNull($page->updated_by);
    }

    public function test_invalid_input_keeps_old_input_and_shows_the_error_summary(): void
    {
        $page = $this->createPage('about');

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), $this->payload(['title' => '', 'subtitle' => 'Subjudul Kiriman']))
            ->assertRedirect(route('admin.pages.edit', $page));

        $this->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Judul wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="Subjudul Kiriman"', false);
    }

    public function test_meta_limits_are_enforced_over_http(): void
    {
        $page = $this->createPage('about');
        $this->actingAs($this->createStaff('content-admin'))->from(route('admin.pages.edit', $page));

        $this->put(route('admin.pages.update', $page), $this->payload(['meta_title' => str_repeat('a', 71)]))
            ->assertSessionHasErrors(['meta_title' => 'Meta title maksimal 70 karakter.']);

        $this->put(route('admin.pages.update', $page), $this->payload(['meta_description' => str_repeat('a', 161)]))
            ->assertSessionHasErrors(['meta_description' => 'Meta description maksimal 160 karakter.']);

        $this->put(route('admin.pages.update', $page), $this->payload(['meta_title' => str_repeat('a', 70), 'meta_description' => str_repeat('b', 160)]))
            ->assertSessionHasNoErrors();
    }

    // --- Rich text (Review Focus 2) -----------------------------------------------------------------------

    public function test_hostile_body_is_sanitized_when_saved_through_the_form(): void
    {
        $page = $this->createPage('about');

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.pages.update', $page), $this->payload([
                'body' => '<p style="color:red" class="x">Aman</p><script>alert(1)</script><img src="/storage/x.jpg" alt="ok" onerror="alert(1)"><a href="javascript:alert(1)">klik</a><iframe src="https://evil.test"></iframe>',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.pages.index'));

        $body = (string) $page->fresh()->body;

        $this->assertStringContainsString('Aman', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('alert(1)</script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('style=', $body);
        $this->assertStringNotContainsString('class=', $body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankBodies(): array
    {
        return [
            'paragraf kosong Quill' => ['<p><br></p>'],
            'hanya spasi tak terpisah' => ['<p>&nbsp;</p>'],
            'hanya spasi' => ['   '],
            'hanya script' => ['<script>alert(1)</script>'],
        ];
    }

    #[DataProvider('blankBodies')]
    public function test_a_body_that_is_blank_after_sanitizing_is_stored_as_null(string $body): void
    {
        $page = $this->createPage('about', ['body' => '<p>Isi lama</p>']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.pages.update', $page), $this->payload(['body' => $body]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.pages.index'));

        $this->assertNull($page->fresh()->body);
    }

    // --- Kolom yang tidak boleh diubah klien ---------------------------------------------------------------

    public function test_key_is_system_flag_and_updated_by_sent_by_the_client_are_ignored(): void
    {
        $page = $this->createPage('about');
        $other = $this->createStaff('registration-officer');
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->put(route('admin.pages.update', $page), $this->payload([
                'key' => 'hacked',
                'is_system' => '0',
                'updated_by' => (string) $other->id,
                'id' => '999',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.pages.index'));

        $page->refresh();
        $this->assertSame('about', $page->key);
        $this->assertTrue($page->is_system);
        $this->assertSame($admin->id, $page->updated_by);
        $this->assertSame(1, Page::query()->count());
        $this->assertDatabaseMissing('pages', ['key' => 'hacked']);
    }

    public function test_registration_officer_cannot_update_a_page(): void
    {
        $page = $this->createPage('about', ['title' => 'Judul Asli']);

        $this->actingAs($this->createStaff('registration-officer'))
            ->put(route('admin.pages.update', $page), $this->payload())
            ->assertForbidden();

        $this->assertSame('Judul Asli', $page->fresh()->title);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'page.updated']);
    }
}
