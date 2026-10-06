<?php

namespace Tests\Feature\Admin;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #39 tab Galeri (T9): daftar, tambah (tanpa foto, F4A-R23), ubah, hapus, akses per peran,
 * validasi V-34 untuk field album, dan hapus album beserta berkas fotonya (CRD-13).
 */
class AdminGalleriesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Kegiatan Rumah Sakit',
            'description' => 'Dokumentasi kegiatan contoh.',
            'is_published' => '1',
            'sort_order' => '2',
        ], $overrides);
    }

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
    public function test_index_access_per_staff_role(string $role, int $status): void
    {
        $this->createGallery(['title' => 'Album Akses']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.galleries.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.galleries.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.galleries.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $gallery = $this->createGallery();

        $this->assertSame(url('/admin/konten/galeri'), route('admin.galleries.index'));
        $this->assertSame(url('/admin/konten/galeri/buat'), route('admin.galleries.create'));
        $this->assertSame(url('/admin/konten/galeri/'.$gallery->id.'/ubah'), route('admin.galleries.edit', $gallery));
        $this->assertSame(url('/admin/konten/galeri/'.$gallery->id), route('admin.galleries.update', $gallery));
        $this->assertSame(url('/admin/konten/galeri/'.$gallery->id.'/foto'), route('admin.galleries.photos.store', $gallery));
    }

    public function test_index_renders_many_rows_with_photo_counts_cover_and_status_in_strict_mode(): void
    {
        $persalinan = $this->createGallery(['title' => 'Album Persalinan', 'sort_order' => 1]);
        $laboratorium = $this->createGallery(['title' => 'Album Laboratorium', 'sort_order' => 2]);
        $draf = $this->createGallery(['title' => 'Album Draf', 'sort_order' => 3, 'is_published' => false]);
        $this->createGallery(['title' => 'Album Kosong', 'sort_order' => 4]);

        foreach ([1, 2, 3] as $position) {
            $this->addGalleryPhoto($persalinan, ['sort_order' => $position, 'alt_text' => 'Persalinan '.$position]);
        }
        $this->addGalleryPhoto($laboratorium, ['sort_order' => 1]);
        $this->addGalleryPhoto($draf, ['sort_order' => 1]);
        $this->addGalleryPhoto($draf, ['sort_order' => 2]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.galleries.index'))
            ->assertOk()
            ->assertSeeInOrder(['Album Persalinan', 'Album Laboratorium', 'Album Draf', 'Album Kosong'])
            ->assertSee('data-label="Jumlah foto">3</td>', false)
            ->assertSee('data-label="Jumlah foto">1</td>', false)
            ->assertSee('data-label="Jumlah foto">2</td>', false)
            ->assertSee('data-label="Jumlah foto">0</td>', false)
            ->assertSee('<span class="badge text-bg-success">Terbit</span>', false)
            ->assertSee('<span class="badge text-bg-secondary">Draf</span>', false)
            ->assertSee('href="'.route('admin.galleries.edit', $persalinan).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.galleries.destroy', $draf).'"', false)
            ->assertDontSee('```', false);
    }

    public function test_index_shows_module_tabs_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.galleries.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.galleries.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Konten')
            ->assertSee('Tambah Album')
            ->assertSee('href="'.route('admin.galleries.create').'"', false);
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.galleries.index'))
            ->assertOk()
            ->assertSee('Belum ada album galeri');
    }

    public function test_search_status_and_sort_filters(): void
    {
        $this->createGallery(['title' => 'Album Persalinan', 'sort_order' => 2]);
        $this->createGallery(['title' => 'Album Laboratorium', 'sort_order' => 1]);
        $this->createGallery(['title' => 'Arsip Draf', 'sort_order' => 3, 'is_published' => false]);
        $this->createGallery(['title' => 'Album 100% Sehat', 'sort_order' => 4]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.galleries.index', ['q' => 'lab']))
            ->assertOk()->assertSee('Album Laboratorium')->assertDontSee('Album Persalinan');

        $this->get(route('admin.galleries.index', ['status' => 'draf']))
            ->assertOk()->assertSee('Arsip Draf')->assertDontSee('Album Persalinan');

        $this->get(route('admin.galleries.index', ['status' => 'terbit']))
            ->assertOk()->assertSee('Album Persalinan')->assertDontSee('Arsip Draf');

        $this->get(route('admin.galleries.index'))
            ->assertSeeInOrder(['Album Laboratorium', 'Album Persalinan', 'Arsip Draf', 'Album 100% Sehat']);

        $this->get(route('admin.galleries.index', ['urut' => 'judul']))
            ->assertSeeInOrder(['Album 100% Sehat', 'Album Laboratorium', 'Album Persalinan', 'Arsip Draf']);

        // Tanda % pada kata kunci dicari apa adanya (bukan wildcard).
        $this->get(route('admin.galleries.index', ['q' => '100%']))
            ->assertOk()->assertSee('Album 100% Sehat')->assertDontSee('Album Laboratorium');
    }

    public function test_invalid_filters_are_ignored_and_array_params_do_not_cause_a_server_error(): void
    {
        $this->createGallery(['title' => 'Album Persalinan']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.galleries.index', ['status' => 'zzz', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Album Persalinan')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.galleries.index', ['urut' => ['x'], 'status' => ['x'], 'q' => ['x']]))
            ->assertOk();
    }

    public function test_create_form_has_album_fields_and_no_slug_or_photo_fields(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.galleries.create'))
            ->assertOk()
            ->assertSee('Tambah Album')
            ->assertSee('action="'.route('admin.galleries.store').'"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="is_published"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('Foto diunggah setelah album disimpan.')
            ->assertDontSee('name="slug"', false)
            ->assertDontSee('name="photos[0]"', false)
            ->assertDontSee('```', false);
    }

    public function test_content_admin_adds_an_album_without_photos_and_is_sent_to_the_edit_page_crd09(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.galleries.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $gallery = Gallery::query()->sole();

        $this->assertSame('Kegiatan Rumah Sakit', $gallery->title);
        $this->assertSame('kegiatan-rumah-sakit', $gallery->slug);
        $this->assertSame('Dokumentasi kegiatan contoh.', $gallery->description);
        $this->assertTrue($gallery->is_published);
        $this->assertSame(2, $gallery->sort_order);
        $this->assertSame(0, Media::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'gallery.created']);

        $this->get(route('admin.galleries.edit', $gallery))
            ->assertOk()
            ->assertSee('Ubah Album Kegiatan Rumah Sakit')
            ->assertSee('Foto album (0 dari 100)');

        $this->actingAs($admin)
            ->post(route('admin.galleries.store'), $this->payload(['title' => 'Album Lain']))
            ->assertRedirect(route('admin.galleries.edit', Gallery::query()->where('title', 'Album Lain')->sole()))
            ->assertSessionHas('success', 'Album galeri berhasil ditambahkan.');
    }

    public function test_albums_with_the_same_title_get_distinct_slugs_and_the_slug_never_changes(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.galleries.store'), $this->payload(['title' => 'Kegiatan Bersama']));
        $this->post(route('admin.galleries.store'), $this->payload(['title' => 'Kegiatan Bersama']));

        $this->assertSame(
            ['kegiatan-bersama', 'kegiatan-bersama-2'],
            Gallery::query()->orderBy('id')->pluck('slug')->all(),
        );

        $second = Gallery::query()->where('slug', 'kegiatan-bersama-2')->sole();

        $this->put(route('admin.galleries.update', $second), $this->payload(['title' => 'Judul Sudah Berbeda']))
            ->assertSessionHasNoErrors();

        $this->assertSame('kegiatan-bersama-2', $second->fresh()->slug);
    }

    public function test_update_changes_fields_unpublishes_and_audits(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Lama', 'is_published' => true, 'sort_order' => 1]);
        $slug = $gallery->slug;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.galleries.update', $gallery), $this->payload([
                'title' => 'Album Baru',
                'description' => '',
                'is_published' => '0',
                'sort_order' => '9',
            ]))
            ->assertRedirect(route('admin.galleries.index'))
            ->assertSessionHas('success', 'Album galeri berhasil diperbarui.');

        $gallery->refresh();
        $this->assertSame('Album Baru', $gallery->title);
        $this->assertSame($slug, $gallery->slug);
        $this->assertNull($gallery->description);
        $this->assertFalse($gallery->is_published);
        $this->assertSame(9, $gallery->sort_order);
        $this->assertNotContains($gallery->id, Gallery::query()->published()->pluck('id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'gallery.updated']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'judul kosong' => [['title' => ''], 'title', 'Judul album wajib diisi.'],
            'judul bukan teks' => [['title' => ['a']], 'title', 'Judul album harus berupa teks.'],
            'judul terlalu pendek' => [['title' => 'Ab'], 'title', 'Judul album minimal 3 karakter.'],
            'judul terlalu panjang' => [['title' => str_repeat('a', 101)], 'title', 'Judul album maksimal 100 karakter.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 501)], 'description', 'Deskripsi album maksimal 500 karakter.'],
            'status terbit hilang' => [['is_published' => null], 'is_published', 'Status terbit tidak valid.'],
            'status terbit tidak valid' => [['is_published' => 'mungkin'], 'is_published', 'Status terbit tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.galleries.create'))
            ->post(route('admin.galleries.store'), array_merge($this->payload(), $override))
            ->assertRedirect(route('admin.galleries.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, Gallery::query()->count());
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.galleries.create'))
            ->post(route('admin.galleries.store'), $this->payload(['title' => '', 'description' => 'Deskripsi lama']))
            ->assertRedirect(route('admin.galleries.create'));

        $this->get(route('admin.galleries.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Judul album wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Deskripsi lama');
    }

    public function test_edit_form_shows_values_and_the_photo_management_forms(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Radiologi', 'description' => 'Foto ruang radiologi.']);
        $first = $this->addGalleryPhoto($gallery, ['sort_order' => 1, 'alt_text' => 'Foto pertama', 'caption' => 'Ruang CT']);
        $second = $this->addGalleryPhoto($gallery, ['sort_order' => 2, 'alt_text' => 'Foto kedua']);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.galleries.edit', $gallery))
            ->assertOk()
            ->assertSee('Ubah Album Album Radiologi')
            ->assertSee('value="Album Radiologi"', false)
            ->assertSee('action="'.route('admin.galleries.update', $gallery).'"', false)
            ->assertSee('Foto album (2 dari 100)')
            ->assertSee('value="Foto pertama"', false)
            ->assertSee('value="Ruang CT"', false)
            ->assertSee('action="'.route('admin.media.update', $first).'"', false)
            ->assertSee('action="'.route('admin.media.reorder').'"', false)
            ->assertSee('data-confirm-action="'.route('admin.media.destroy', $second).'"', false)
            ->assertSee('action="'.route('admin.galleries.photos.store', $gallery).'"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="photos[0]"', false)
            ->assertSee('name="alt_texts[4]"', false)
            ->assertSee('name="captions[4]"', false)
            ->assertDontSee('name="photos[5]"', false)
            ->assertDontSee('```', false);
    }

    public function test_deleting_an_album_removes_its_photos_and_files_but_not_other_albums_crd13(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin);

        $this->post(route('admin.galleries.store'), $this->payload(['title' => 'Album Dihapus']));
        $gallery = Gallery::query()->where('title', 'Album Dihapus')->sole();

        $this->post(route('admin.galleries.photos.store', $gallery), [
            'photos' => [0 => ImageFixtures::jpeg(700, 500, 'a.jpg'), 1 => ImageFixtures::jpeg(700, 500, 'b.jpg')],
            'alt_texts' => [0 => 'Foto A', 1 => 'Foto B'],
        ])->assertSessionHasNoErrors();

        $other = $this->createGallery(['title' => 'Album Aman']);
        $otherPhoto = $this->addGalleryPhoto($other, ['sort_order' => 1]);

        $paths = Media::query()
            ->where('model_id', $gallery->id)
            ->get()
            ->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])
            ->all();
        $this->assertCount(4, $paths);

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        $this->delete(route('admin.galleries.destroy', $gallery))
            ->assertRedirect(route('admin.galleries.index'))
            ->assertSessionHas('success', 'Album galeri berhasil dihapus.');

        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
        $this->assertSame(0, Media::query()->where('model_id', $gallery->id)->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('galleries', ['id' => $other->id]);
        $this->assertDatabaseHas('media', ['id' => $otherPhoto->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'gallery.deleted']);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'media.deleted')->count());
    }

    public function test_non_numeric_ids_are_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->get('/admin/konten/galeri/abc/ubah')->assertNotFound();
        $this->post('/admin/konten/galeri/abc/foto')->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_gallery_routes(): void
    {
        $gallery = $this->createGallery(['title' => 'Album Aman']);

        $requests = [
            ['GET', route('admin.galleries.create'), []],
            ['POST', route('admin.galleries.store'), $this->payload(['title' => 'Tidak Boleh'])],
            ['GET', route('admin.galleries.edit', $gallery), []],
            ['PUT', route('admin.galleries.update', $gallery), $this->payload(['title' => 'Diubah Paksa'])],
            ['DELETE', route('admin.galleries.destroy', $gallery), []],
            ['POST', route('admin.galleries.photos.store', $gallery), ['alt_texts' => [0 => 'x']]],
        ];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user);

            foreach ($requests as [$method, $url, $data]) {
                $this->call($method, $url, $data)->assertForbidden();
            }
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url, $data]) {
            $this->call($method, $url, $data)->assertRedirect(route('admin.login'));
        }

        $this->assertSame('Album Aman', $gallery->fresh()->title);
        $this->assertDatabaseMissing('galleries', ['title' => 'Tidak Boleh']);
        $this->assertDatabaseHas('galleries', ['id' => $gallery->id]);
    }
}
