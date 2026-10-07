<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #36 tab Fasilitas (T4): akses per peran (AKS-04), daftar dan filter (VAL-07, EMP-03), CRUD (CRD-06),
 * cover dan galeri (D-41, MED-05), unggahan berbahaya (SEK-09, SEK-10), sanitasi (SEK-08), dan hapus permanen (CRD-13).
 */
class AdminFacilitiesTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
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
            'name' => 'Laboratorium Contoh',
            'category' => 'penunjang',
            'short_description' => 'Pemeriksaan laboratorium contoh untuk pengujian.',
            'description' => '<p>Deskripsi <strong>lengkap</strong> fasilitas.</p>',
            'is_active' => '1',
            'sort_order' => '3',
        ], $overrides);
    }

    private function attachCover(Facility $facility, string $alt = 'Foto sampul contoh'): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $facility, ['alt_text' => $alt]);
    }

    private function attachGalleryImage(Facility $facility, string $alt): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'galeri.jpg'), 'gallery', $facility, ['alt_text' => $alt]);
    }

    // --- Akses (AKS-04) ------------------------------------------------------------------------------

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
        $this->createFacility(['name' => 'Fasilitas Akses']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.facilities.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.facilities.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.facilities.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $facility = $this->createFacility();

        $this->assertSame(url('/admin/fasilitas'), route('admin.facilities.index'));
        $this->assertSame(url('/admin/fasilitas/buat'), route('admin.facilities.create'));
        $this->assertSame(url('/admin/fasilitas/'.$facility->id.'/ubah'), route('admin.facilities.edit', $facility));
        $this->assertSame(url('/admin/fasilitas/'.$facility->id), route('admin.facilities.update', $facility));
    }

    public function test_there_is_no_show_route_for_facilities(): void
    {
        $this->assertFalse(Route::has('admin.facilities.show'));
    }

    // --- Daftar --------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_with_category_cover_status_and_an_always_visible_delete_button(): void
    {
        $lab = $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang, 'sort_order' => 1]);
        $igd = $this->createFacility(['name' => 'IGD Contoh', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $parkir = $this->createFacility(['name' => 'Area Parkir Contoh', 'category' => FacilityCategory::Umum, 'sort_order' => 3, 'is_active' => false]);
        $this->createFacility(['name' => 'Musala Contoh', 'category' => FacilityCategory::Umum, 'sort_order' => 4]);

        $cover = $this->attachCover($lab, 'Foto laboratorium');

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSeeInOrder(['Laboratorium Contoh', 'IGD Contoh', 'Area Parkir Contoh', 'Musala Contoh'])
            ->assertSee('data-label="Kategori"><i class="bi bi-tools" aria-hidden="true"></i> Penunjang</td>', false)
            ->assertSee('data-label="Kategori"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Fasilitas medis</td>', false)
            ->assertSee('data-label="Kategori"><i class="bi bi-building" aria-hidden="true"></i> Fasilitas umum</td>', false)
            ->assertSee('<span class="badge text-bg-success">Aktif</span>', false)
            ->assertSee('<span class="badge text-bg-secondary">Nonaktif</span>', false)
            ->assertSee((string) $cover->thumb_url, false)
            ->assertSee('images/placeholders/facility.svg', false)
            ->assertSee('data-confirm-action="'.route('admin.facilities.destroy', $lab).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.facilities.destroy', $igd).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.facilities.destroy', $parkir).'"', false)
            ->assertSee('href="'.route('admin.facilities.edit', $igd).'"', false);
    }

    public function test_index_shows_module_tab_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.facilities.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Fasilitas dan Kamar')
            ->assertSee('Tambah Fasilitas')
            ->assertSee('href="'.route('admin.facilities.create').'"', false)
            ->assertSee('id="modal-hapus-fasilitas"', false)
            ->assertSee('Hapus fasilitas?')
            ->assertSee('Fasilitas beserta gambarnya akan dihapus permanen.');
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSee('Belum ada fasilitas');
    }

    public function test_search_status_category_and_sort_filters(): void
    {
        $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang, 'sort_order' => 1]);
        $this->createFacility(['name' => 'IGD Contoh', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Musala Contoh', 'category' => FacilityCategory::Umum, 'sort_order' => 3, 'is_active' => false]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.facilities.index', ['q' => 'igd']))
            ->assertOk()->assertSee('IGD Contoh')->assertDontSee('Laboratorium Contoh');

        $this->get(route('admin.facilities.index', ['status' => 'nonaktif']))
            ->assertOk()->assertSee('Musala Contoh')->assertDontSee('IGD Contoh');

        $this->get(route('admin.facilities.index', ['status' => 'aktif']))
            ->assertOk()->assertSee('IGD Contoh')->assertDontSee('Musala Contoh');

        $this->get(route('admin.facilities.index', ['kategori' => 'penunjang']))
            ->assertOk()->assertSee('Laboratorium Contoh')->assertDontSee('IGD Contoh')->assertDontSee('Musala Contoh');

        $this->get(route('admin.facilities.index'))
            ->assertSeeInOrder(['Laboratorium Contoh', 'IGD Contoh', 'Musala Contoh']);

        $this->get(route('admin.facilities.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['IGD Contoh', 'Laboratorium Contoh', 'Musala Contoh']);

        $this->get(route('admin.facilities.index', ['urut' => '-nama']))
            ->assertSeeInOrder(['Musala Contoh', 'Laboratorium Contoh', 'IGD Contoh']);
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->createFacility(['name' => 'Diskon 100% Contoh']);
        $this->createFacility(['name' => 'Diskon 1000 Contoh']);
        $this->createFacility(['name' => 'Ruang_A Contoh']);
        $this->createFacility(['name' => 'RuangXA Contoh']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.facilities.index', ['q' => '100%']))
            ->assertOk()->assertSee('Diskon 100% Contoh')->assertDontSee('Diskon 1000 Contoh');

        $this->get(route('admin.facilities.index', ['q' => 'Ruang_A']))
            ->assertOk()->assertSee('Ruang_A Contoh')->assertDontSee('RuangXA Contoh');
    }

    public function test_invalid_filters_are_ignored_and_array_params_do_not_cause_a_server_error(): void
    {
        $this->createFacility(['name' => 'Laboratorium Contoh']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.facilities.index', ['status' => 'zzz', 'kategori' => 'vip', 'urut' => 'zzz', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Laboratorium Contoh')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.facilities.index', ['urut' => ['x'], 'status' => ['x'], 'q' => ['x'], 'kategori' => ['x']]))
            ->assertOk();
    }

    // --- Form dan CRUD (CRD-06) ----------------------------------------------------------------------

    public function test_create_form_has_the_facility_fields_cover_gallery_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.create'))
            ->assertOk()
            ->assertSee('Tambah Fasilitas')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('action="'.route('admin.facilities.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="category"', false)
            ->assertSee('value="medis"', false)
            ->assertSee('value="penunjang"', false)
            ->assertSee('value="umum"', false)
            ->assertSee('name="short_description"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="meta_title"', false)
            ->assertSee('name="meta_description"', false)
            ->assertSee('name="cover"', false)
            ->assertSee('name="cover_alt"', false)
            ->assertSee('name="gallery[0]"', false)
            ->assertSee('name="gallery_alt[0]"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_facility_without_images_crd06(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload())
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil ditambahkan.');

        $facility = Facility::query()->sole();

        $this->assertSame('Laboratorium Contoh', $facility->name);
        $this->assertSame('laboratorium-contoh', $facility->slug);
        $this->assertSame(FacilityCategory::Penunjang, $facility->category);
        $this->assertSame('Pemeriksaan laboratorium contoh untuk pengujian.', $facility->short_description);
        $this->assertStringContainsString('<strong>lengkap</strong>', (string) $facility->description);
        $this->assertTrue($facility->is_active);
        $this->assertSame(3, $facility->sort_order);
        $this->assertNull($facility->meta_title);
        $this->assertNull($facility->meta_description);
        $this->assertSame(0, Media::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'facility.created',
            'description' => 'Fasilitas "Laboratorium Contoh" ditambahkan.',
        ]);
    }

    public function test_optional_fields_may_be_left_empty_and_every_category_is_accepted(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        foreach (['medis' => 'IGD Contoh', 'penunjang' => 'Radiologi Contoh', 'umum' => 'Musala Contoh'] as $category => $name) {
            $this->post(route('admin.facilities.store'), $this->payload([
                'name' => $name,
                'category' => $category,
                'description' => '',
                'sort_order' => '',
                'meta_title' => '',
                'meta_description' => '',
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Facility::query()->count());

        $facility = Facility::query()->where('name', 'IGD Contoh')->sole();
        $this->assertSame(FacilityCategory::Medis, $facility->category);
        $this->assertNull($facility->description);
        $this->assertSame(0, $facility->sort_order);
        $this->assertNull($facility->meta_title);
    }

    public function test_description_is_sanitized_when_saved_through_the_form_sek08(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload([
                'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a><iframe src="https://evil.test"></iframe>',
            ]))
            ->assertRedirect(route('admin.facilities.index'));

        $description = (string) Facility::query()->sole()->description;

        $this->assertStringContainsString('Aman', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('javascript:', $description);
        $this->assertStringNotContainsString('<iframe', $description);
    }

    public function test_edit_form_shows_values_the_selected_category_and_gallery_management_forms(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang, 'short_description' => 'Ringkasan laboratorium.', 'sort_order' => 5]);
        $first = $this->createMediaRow(['model_type' => Facility::class, 'model_id' => $facility->id, 'collection' => 'gallery', 'alt_text' => 'Foto pertama', 'sort_order' => 1]);
        $second = $this->createMediaRow(['model_type' => Facility::class, 'model_id' => $facility->id, 'collection' => 'gallery', 'alt_text' => 'Foto kedua', 'sort_order' => 2]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.facilities.edit', $facility))
            ->assertOk()
            ->assertSee('Ubah Laboratorium Contoh')
            ->assertSee('action="'.route('admin.facilities.update', $facility).'"', false)
            ->assertSee('value="Laboratorium Contoh"', false)
            ->assertSee('value="penunjang" selected', false)
            ->assertSee('Ringkasan laboratorium.')
            ->assertSee('value="5"', false)
            ->assertSee('Galeri (2 dari 20 foto)')
            ->assertSee('Foto pertama')
            ->assertSee('Foto kedua')
            ->assertSee('action="'.route('admin.media.update', $first).'"', false)
            ->assertSee('action="'.route('admin.media.reorder').'"', false)
            ->assertSee('data-confirm-action="'.route('admin.media.destroy', $second).'"', false);
    }

    public function test_update_changes_fields_keeps_the_slug_and_allows_the_unchanged_name(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang]);
        $slug = $facility->slug;
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.facilities.update', $facility), $this->payload(['short_description' => 'Ringkasan baru.']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil diperbarui.');

        $this->assertSame('Ringkasan baru.', $facility->fresh()->short_description);

        $this->put(route('admin.facilities.update', $facility), $this->payload([
            'name' => 'Nama Sudah Berbeda',
            'category' => 'umum',
        ]))->assertSessionHasNoErrors();

        $facility->refresh();
        $this->assertSame('Nama Sudah Berbeda', $facility->name);
        $this->assertSame($slug, $facility->slug);
        $this->assertSame(FacilityCategory::Umum, $facility->category);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'facility.updated',
            'description' => 'Fasilitas "Nama Sudah Berbeda" diperbarui.',
        ]);
    }

    public function test_a_facility_is_deactivated_through_the_edit_form_and_leaves_the_active_scope(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh', 'is_active' => true]);
        $this->createFacility(['name' => 'Musala Contoh']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'is_active' => 0]);
        $this->assertSame(['Musala Contoh'], Facility::query()->active()->pluck('name')->all());
    }

    // --- Cover dan galeri (D-41, MED-05) -------------------------------------------------------------

    public function test_cover_upload_on_create_stores_media_for_the_facility(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.facilities.store'), $this->payload([
                'cover' => ImageFixtures::jpeg(1200, 800, 'cover.jpg'),
                'cover_alt' => 'Foto sampul laboratorium',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'));

        $facility = Facility::query()->sole();
        $media = Media::query()->where('collection', 'cover')->sole();

        $this->assertSame(Facility::class, $media->model_type);
        $this->assertSame($facility->id, $media->model_id);
        $this->assertSame('Foto sampul laboratorium', $media->alt_text);
        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertStringStartsWith('cover/'.now()->format('Y/m'), $media->directory);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_replacing_the_cover_removes_the_old_file_after_commit(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $old = $this->attachCover($facility, 'Sampul lama');
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload([
                'cover' => ImageFixtures::jpeg(1000, 700, 'baru.jpg'),
                'cover_alt' => 'Sampul baru',
            ]))
            ->assertSessionHasNoErrors();

        $new = Media::query()->where('collection', 'cover')->sole();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Sampul baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_cover_keeps_it_and_a_changed_alt_updates_the_existing_cover(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $cover = $this->attachCover($facility, 'Sampul lama');
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.facilities.update', $facility), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame($cover->id, Media::query()->where('collection', 'cover')->sole()->id);

        $this->put(route('admin.facilities.update', $facility), $this->payload(['cover_alt' => '  Sampul diperbarui  ']))
            ->assertSessionHasNoErrors();

        $same = Media::query()->where('collection', 'cover')->sole();
        $this->assertSame($cover->id, $same->id);
        $this->assertSame('Sampul diperbarui', $same->alt_text);
        Storage::disk('public')->assertExists($same->directory.'/'.$same->file_name);
    }

    public function test_an_empty_cover_alt_is_rejected_while_a_cover_exists_and_keeps_the_old_alt(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $this->attachCover($facility, 'Sampul lama');

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['name' => 'Nama Tidak Tersimpan', 'cover_alt' => '  ']))
            ->assertSessionHasErrors(['cover_alt' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame('Sampul lama', Media::query()->where('collection', 'cover')->sole()->alt_text);
        $this->assertSame('Laboratorium Contoh', $facility->fresh()->name);
    }

    public function test_an_empty_cover_alt_without_a_cover_is_not_an_error(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['cover_alt' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_gallery_images_are_added_in_input_order_with_their_own_alt_text(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600, 'g1.jpg'), 2 => ImageFixtures::jpeg(900, 600, 'g2.jpg')],
                'gallery_alt' => [0 => 'Ruang pemeriksaan', 1 => '', 2 => 'Ruang tunggu'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'));

        $this->assertSame(
            ['Ruang pemeriksaan', 'Ruang tunggu'],
            Media::query()->where('collection', 'gallery')->orderBy('sort_order')->orderBy('id')->pluck('alt_text')->all(),
        );
    }

    public function test_a_gallery_image_without_alt_text_is_rejected_and_nothing_is_stored(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => ''],
            ]))
            ->assertSessionHasErrors(['gallery_alt.0' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame(0, Facility::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function galleryLimits(): array
    {
        return [
            '19 yang ada ditambah 2 baru' => [19, 2],
            '20 yang ada ditambah 1 baru' => [20, 1],
        ];
    }

    #[DataProvider('galleryLimits')]
    public function test_the_gallery_is_limited_to_20_images_including_existing_ones(int $existing, int $incoming): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);

        foreach (range(1, $existing) as $position) {
            $this->createMediaRow([
                'model_type' => Facility::class,
                'model_id' => $facility->id,
                'collection' => 'gallery',
                'sort_order' => $position,
            ]);
        }

        $files = [];
        $alts = [];

        foreach (range(0, $incoming - 1) as $index) {
            $files[$index] = ImageFixtures::jpeg(900, 600);
            $alts[$index] = 'Foto baru '.($index + 1);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['gallery' => $files, 'gallery_alt' => $alts]))
            ->assertSessionHasErrors(['gallery' => 'Galeri maksimal 20 gambar.']);

        $this->assertSame($existing, Media::query()->where('collection', 'gallery')->count());
    }

    public function test_hostile_and_undersized_uploads_are_rejected_without_storing_anything_sek09_sek10(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $hostile = [
            'svg berskrip' => ImageFixtures::svgWithScript(),
            'php berkepala jpeg' => ImageFixtures::phpWithJpegHeader(),
            'teks bernama jpg' => ImageFixtures::textNamedJpg(),
            'sampul terlalu kecil' => ImageFixtures::jpeg(799, 449, 'kecil.jpg'),
        ];

        foreach ($hostile as $file) {
            $this->post(route('admin.facilities.store'), $this->payload(['cover' => $file, 'cover_alt' => 'x']))
                ->assertSessionHasErrors('cover');
        }

        $this->post(route('admin.facilities.store'), $this->payload([
            'gallery' => [0 => ImageFixtures::svgWithScript('galeri.svg'), 1 => ImageFixtures::phpWithJpegHeader('galeri.php.jpg')],
            'gallery_alt' => [0 => 'x', 1 => 'y'],
        ]))->assertSessionHasErrors(['gallery.0', 'gallery.1']);

        $this->assertSame(0, Facility::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_content_admin_manages_facility_gallery_images_through_the_media_endpoints(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $a = $this->attachGalleryImage($facility, 'Foto A');
        $b = $this->attachGalleryImage($facility, 'Foto B');
        $editUrl = route('admin.facilities.edit', $facility);
        $this->actingAs($this->createStaff('content-admin'));

        $this->from($editUrl)
            ->patch(route('admin.media.update', $a), ['alt_text' => 'Foto A baru'])
            ->assertRedirect($editUrl)
            ->assertSessionHas('success', 'Gambar berhasil diperbarui.');
        $this->assertSame('Foto A baru', $a->fresh()->alt_text);

        $this->from($editUrl)
            ->post(route('admin.media.reorder'), ['ids' => [$b->id, $a->id]])
            ->assertRedirect($editUrl);
        $this->assertSame(
            [$b->id, $a->id],
            Media::query()->where('collection', 'gallery')->orderBy('sort_order')->pluck('id')->all(),
        );

        $path = $a->directory.'/'.$a->file_name;
        $this->from($editUrl)
            ->delete(route('admin.media.destroy', $a))
            ->assertRedirect($editUrl);
        $this->assertDatabaseMissing('media', ['id' => $a->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('media', ['id' => $b->id]);
    }

    // --- Hapus permanen (CRD-13) ---------------------------------------------------------------------

    public function test_deleting_a_facility_removes_its_media_files_crd13(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.facilities.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
            'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
            'gallery_alt' => [0 => 'Galeri'],
        ]));
        $facility = Facility::query()->sole();
        $paths = Media::query()->get()->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])->all();
        $this->assertCount(4, $paths);

        $this->delete(route('admin.facilities.destroy', $facility))
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil dihapus.');

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_a_facility_can_always_be_deleted_by_a_manager(string $role): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Dihapus']);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.facilities.destroy', $facility))
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil dihapus.');

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }

    // --- Rute dan penolakan ---------------------------------------------------------------------------

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/fasilitas/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_facility_routes(): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Aman']);

        $requests = [
            ['GET', route('admin.facilities.create'), []],
            ['POST', route('admin.facilities.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['GET', route('admin.facilities.edit', $facility), []],
            ['PUT', route('admin.facilities.update', $facility), $this->payload(['name' => 'Diubah Paksa'])],
            ['DELETE', route('admin.facilities.destroy', $facility), []],
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

        $this->assertSame('Fasilitas Aman', $facility->fresh()->name);
        $this->assertDatabaseMissing('facilities', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
    }

    public function test_registration_officer_cannot_change_facility_images_through_the_media_endpoints(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $image = $this->attachGalleryImage($facility, 'Foto tetap');
        $this->actingAs($this->createStaff('registration-officer'));

        $this->patch(route('admin.media.update', $image), ['alt_text' => 'Diubah paksa'])->assertForbidden();
        $this->post(route('admin.media.reorder'), ['ids' => [$image->id]])->assertForbidden();
        $this->delete(route('admin.media.destroy', $image))->assertForbidden();

        $this->assertSame('Foto tetap', $image->fresh()->alt_text);
        $this->assertDatabaseHas('media', ['id' => $image->id]);
    }
}
