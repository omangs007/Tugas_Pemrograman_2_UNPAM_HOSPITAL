<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\RoomCategoryRequest;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #36 tab Kamar dan Kapasitas (T5): mode ketersediaan Petugas (D-12), CRUD Admin Konten (CRD-07),
 * kapasitas (BR-115), amenities (BR-118), cover dan galeri, unggahan berbahaya, serta hapus selalu boleh (CRD-13).
 */
class AdminRoomCategoriesTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Selasa, 6 Oktober 2026 pukul 10.30 WIB.
        $this->travelTo(Carbon::parse('2026-10-06 10:30:00'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kamar Kelas Contoh',
            'short_description' => 'Ruang rawat inap contoh untuk pengujian.',
            'description' => '<p>Deskripsi <strong>kamar</strong> contoh.</p>',
            'amenities' => "AC\nTelevisi\nKamar mandi dalam",
            'bed_capacity' => '12',
            'beds_available' => '5',
            'sample_rate' => '400000',
            'rate_unit' => 'per malam',
            'rate_note' => 'Tarif contoh, belum final',
            'is_active' => '1',
            'sort_order' => '3',
        ], $overrides);
    }

    private function attachCover(RoomCategory $room, string $alt = 'Foto sampul kamar'): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $room, ['alt_text' => $alt]);
    }

    private function attachGalleryImage(RoomCategory $room, string $alt): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'galeri.jpg'), 'gallery', $room, ['alt_text' => $alt]);
    }

    // --- Akses (AKS-04, AKS-05, D-12) -----------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'admin konten' => ['content-admin', 200],
            'petugas pendaftaran (mode ketersediaan)' => ['registration-officer', 200],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_index_access_per_staff_role(string $role, int $status): void
    {
        $this->createRoomCategory(['name' => 'VIP Akses']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.room-categories.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.room-categories.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.room-categories.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $room = $this->createRoomCategory();

        $this->assertSame(url('/admin/kamar'), route('admin.room-categories.index'));
        $this->assertSame(url('/admin/kamar'), route('admin.room-categories.store'));
        $this->assertSame(url('/admin/kamar/buat'), route('admin.room-categories.create'));
        $this->assertSame(url('/admin/kamar/'.$room->id.'/ubah'), route('admin.room-categories.edit', $room));
        $this->assertSame(url('/admin/kamar/'.$room->id), route('admin.room-categories.update', $room));
        $this->assertSame(url('/admin/kamar/'.$room->id.'/ketersediaan'), route('admin.room-categories.availability', $room));
        $this->assertFalse(Route::has('admin.room-categories.show'));
    }

    // --- Daftar ---------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_with_capacity_availability_updater_rate_and_status_in_strict_mode(): void
    {
        $updater = $this->createStaff('registration-officer', ['name' => 'Siti Petugas']);
        $k1 = $this->createRoomCategory([
            'name' => 'BPJS Kelas 1 Contoh', 'bed_capacity' => 12, 'beds_available' => 4, 'sample_rate' => 350000,
            'rate_unit' => 'per malam', 'rate_note' => 'Tarif contoh, belum final', 'sort_order' => 1,
            'availability_updated_at' => now()->subHour(), 'availability_updated_by' => $updater->id,
        ]);
        $k2 = $this->createRoomCategory([
            'name' => 'BPJS Kelas 2 Contoh', 'bed_capacity' => 16, 'beds_available' => 7, 'sample_rate' => null,
            'sort_order' => 2, 'availability_updated_at' => null, 'availability_updated_by' => null,
        ]);
        $this->createRoomCategory(['name' => 'BPJS Kelas 3 Contoh', 'bed_capacity' => 24, 'beds_available' => 0, 'sort_order' => 3]);
        $k4 = $this->createRoomCategory(['name' => 'VIP Contoh', 'bed_capacity' => 6, 'beds_available' => 2, 'sort_order' => 4, 'is_active' => false]);
        $this->attachCover($k1, 'Foto kelas 1');

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['BPJS Kelas 1 Contoh', 'BPJS Kelas 2 Contoh', 'BPJS Kelas 3 Contoh', 'VIP Contoh'])
            ->assertSee('12 tempat tidur')
            ->assertSee('24 tempat tidur')
            ->assertSee('Rp 350.000 per malam')
            ->assertSee('Tarif contoh, belum final')
            ->assertSee('Hubungi kami')
            ->assertSee($k1->availability_updated_label)
            ->assertSee('Belum diperbarui')
            ->assertSee('oleh Siti Petugas')
            ->assertSee('<span class="badge text-bg-success">Aktif</span>', false)
            ->assertSee('<span class="badge text-bg-secondary">Nonaktif</span>', false)
            ->assertSee('images/placeholders/room.svg', false)
            ->assertSee('name="beds_available" value="4" min="0" max="12"', false)
            ->assertSee('name="beds_available" value="0" min="0" max="24"', false)
            ->assertSee('action="'.route('admin.room-categories.availability', $k2).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.room-categories.destroy', $k4).'"', false)
            ->assertDontSee('real-time')
            ->assertDontSee('Real-time');
    }

    public function test_index_shows_module_tabs_breadcrumb_add_button_and_the_delete_modal_to_content_admin(): void
    {
        $this->createRoomCategory(['name' => 'VIP Contoh']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.room-categories.index').'"', false)
            ->assertSee('href="'.route('admin.facilities.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Fasilitas dan Kamar')
            ->assertSee('Tambah Kategori Kamar')
            ->assertSee('href="'.route('admin.room-categories.create').'"', false)
            ->assertSee('id="modal-hapus-kamar"', false)
            ->assertSee('Hapus kategori kamar?')
            ->assertSee('Kategori kamar beserta gambarnya akan dihapus permanen dan halaman publiknya tidak dapat dibuka lagi. Bila hanya ingin menyembunyikannya, nonaktifkan saja.')
            ->assertDontSee('Anda hanya dapat memperbarui ketersediaan tempat tidur.');
    }

    public function test_registration_officer_sees_only_the_availability_mode_d12(): void
    {
        $a = $this->createRoomCategory(['name' => 'BPJS Kelas 1 Contoh', 'sort_order' => 1]);
        $b = $this->createRoomCategory(['name' => 'VIP Contoh', 'sort_order' => 2, 'is_active' => false]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('Anda hanya dapat memperbarui ketersediaan tempat tidur.')
            ->assertSee('action="'.route('admin.room-categories.availability', $a).'"', false)
            ->assertSee('action="'.route('admin.room-categories.availability', $b).'"', false)
            ->assertDontSee('Tambah Kategori Kamar')
            ->assertDontSee('href="'.route('admin.room-categories.create').'"', false)
            ->assertDontSee('href="'.route('admin.room-categories.edit', $a).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.room-categories.destroy', $a).'"', false)
            ->assertDontSee('modal-hapus-kamar')
            ->assertDontSee('href="'.route('admin.facilities.index').'"', false);
    }

    public function test_empty_list_shows_the_empty_state_with_an_add_button_only_for_managers(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('Belum ada kategori kamar')
            ->assertSee('href="'.route('admin.room-categories.create').'"', false);

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('Belum ada kategori kamar')
            ->assertDontSee('href="'.route('admin.room-categories.create').'"', false);
    }

    // --- Form dan CRUD (CRD-07) -----------------------------------------------------------------------

    public function test_create_form_has_the_room_fields_cover_gallery_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.create'))
            ->assertOk()
            ->assertSee('Tambah Kategori Kamar')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('action="'.route('admin.room-categories.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="short_description"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('Fasilitas kamar (satu per baris)')
            ->assertSee('name="amenities"', false)
            ->assertSee('name="bed_capacity"', false)
            ->assertSee('name="beds_available"', false)
            ->assertSee('name="sample_rate"', false)
            ->assertSee('name="rate_unit"', false)
            ->assertSee('value="per malam"', false)
            ->assertSee('name="rate_note"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="meta_title"', false)
            ->assertSee('name="cover"', false)
            ->assertSee('name="cover_alt"', false)
            ->assertSee('name="gallery[0]"', false)
            ->assertSee('name="gallery_alt[0]"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_room_category_and_availability_metadata_is_filled_crd07(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.room-categories.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil ditambahkan.');

        $room = RoomCategory::query()->sole();

        $this->assertSame('Kamar Kelas Contoh', $room->name);
        $this->assertSame('kamar-kelas-contoh', $room->slug);
        $this->assertSame('Ruang rawat inap contoh untuk pengujian.', $room->short_description);
        $this->assertStringContainsString('<strong>kamar</strong>', (string) $room->description);
        $this->assertSame(['AC', 'Televisi', 'Kamar mandi dalam'], $room->amenities);
        $this->assertSame(12, $room->bed_capacity);
        $this->assertSame(5, $room->beds_available);
        $this->assertSame('400000.00', (string) $room->sample_rate);
        $this->assertSame('per malam', $room->rate_unit);
        $this->assertSame('Tarif contoh, belum final', $room->rate_note);
        $this->assertTrue($room->is_active);
        $this->assertSame(3, $room->sort_order);
        $this->assertTrue($room->availability_updated_at->equalTo(now()));
        $this->assertSame($admin->id, $room->availability_updated_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'room_category.created',
            'description' => 'Kategori kamar "Kamar Kelas Contoh" ditambahkan.',
        ]);
    }

    public function test_a_full_room_and_a_room_at_full_capacity_can_be_created(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.room-categories.store'), $this->payload(['name' => 'Kamar Penuh Contoh', 'beds_available' => '0']))
            ->assertSessionHasNoErrors();
        $this->post(route('admin.room-categories.store'), $this->payload(['name' => 'Kamar Kosong Contoh', 'beds_available' => '12']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, RoomCategory::query()->where('name', 'Kamar Penuh Contoh')->value('beds_available'));
        $this->assertSame(12, RoomCategory::query()->where('name', 'Kamar Kosong Contoh')->value('beds_available'));
    }

    public function test_optional_fields_may_be_left_empty_and_the_textarea_amenities_are_normalized(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload([
                'name' => 'Kamar Tanpa Tarif',
                'description' => '',
                'amenities' => "  AC  \r\n\r\n Televisi\n   \n",
                'sample_rate' => '',
                'rate_note' => '',
                'sort_order' => '',
                'meta_title' => '',
                'meta_description' => '',
            ]))
            ->assertSessionHasNoErrors();

        $room = RoomCategory::query()->sole();

        $this->assertSame(['AC', 'Televisi'], $room->amenities);
        $this->assertNull($room->description);
        $this->assertNull($room->sample_rate);
        $this->assertNull($room->rate_note);
        $this->assertSame(0, $room->sort_order);
        $this->assertNull($room->meta_title);
    }

    public function test_empty_amenities_are_stored_as_null(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload(['amenities' => "\n  \n"]))
            ->assertSessionHasNoErrors();

        $this->assertNull(RoomCategory::query()->sole()->amenities);
    }

    public function test_description_is_sanitized_when_saved_through_the_form_sek08(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload([
                'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>',
            ]))
            ->assertRedirect(route('admin.room-categories.index'));

        $description = (string) RoomCategory::query()->sole()->description;

        $this->assertStringContainsString('Aman', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('javascript:', $description);
    }

    public function test_edit_form_shows_values_the_amenities_one_per_line_and_gallery_management_forms(): void
    {
        $room = $this->createRoomCategory([
            'name' => 'VIP Contoh', 'short_description' => 'Ringkasan VIP.', 'amenities' => ['AC', 'Televisi'],
            'bed_capacity' => 6, 'beds_available' => 2, 'sample_rate' => 900000, 'rate_unit' => 'per malam', 'sort_order' => 5,
        ]);
        $first = $this->createMediaRow(['model_type' => RoomCategory::class, 'model_id' => $room->id, 'collection' => 'gallery', 'alt_text' => 'Foto pertama', 'sort_order' => 1]);
        $second = $this->createMediaRow(['model_type' => RoomCategory::class, 'model_id' => $room->id, 'collection' => 'gallery', 'alt_text' => 'Foto kedua', 'sort_order' => 2]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.room-categories.edit', $room))
            ->assertOk()
            ->assertSee('Ubah VIP Contoh')
            ->assertSee('action="'.route('admin.room-categories.update', $room).'"', false)
            ->assertSee('value="VIP Contoh"', false)
            ->assertSee('Ringkasan VIP.')
            ->assertSee('>AC'."\n".'Televisi</textarea>', false)
            ->assertSee('value="6"', false)
            ->assertSee('value="900000"', false)
            ->assertDontSee('name="beds_available"', false)
            ->assertSee('Tempat tidur tersedia saat ini: 2 dari 6.')
            ->assertSee('Galeri (2 dari 20 foto)')
            ->assertSee('action="'.route('admin.media.update', $first).'"', false)
            ->assertSee('action="'.route('admin.media.reorder').'"', false)
            ->assertSee('data-confirm-action="'.route('admin.media.destroy', $second).'"', false);
    }

    public function test_update_changes_fields_keeps_the_slug_and_does_not_touch_availability(): void
    {
        $updater = $this->createStaff('registration-officer');
        $stamp = Carbon::parse('2026-10-05 08:00:00');
        $room = $this->createRoomCategory([
            'name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4,
            'availability_updated_at' => $stamp, 'availability_updated_by' => $updater->id,
        ]);
        $slug = $room->slug;
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.room-categories.update', $room), $this->payload([
            'name' => 'Nama Sudah Berbeda',
            'bed_capacity' => '20',
            'beds_available' => '9',
            'amenities' => "AC\nKulkas",
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil diperbarui.');

        $room->refresh();
        $this->assertSame('Nama Sudah Berbeda', $room->name);
        $this->assertSame($slug, $room->slug);
        $this->assertSame(20, $room->bed_capacity);
        $this->assertSame(4, $room->beds_available);
        $this->assertSame(['AC', 'Kulkas'], $room->amenities);
        $this->assertTrue($room->availability_updated_at->equalTo($stamp));
        $this->assertSame($updater->id, $room->availability_updated_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'room_category.updated',
            'description' => 'Kategori kamar "Nama Sudah Berbeda" diperbarui.',
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'room.availability_updated']);
    }

    public function test_capacity_below_the_current_availability_is_rejected_and_equal_is_accepted(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->from(route('admin.room-categories.edit', $room))
            ->put(route('admin.room-categories.update', $room), $this->payload(['bed_capacity' => '3']))
            ->assertRedirect(route('admin.room-categories.edit', $room))
            ->assertSessionHasErrors(['bed_capacity' => 'Kapasitas tidak boleh lebih kecil dari tempat tidur tersedia saat ini (4). Perbarui ketersediaan terlebih dahulu.']);

        $this->assertSame(12, $room->fresh()->bed_capacity);

        $this->put(route('admin.room-categories.update', $room), $this->payload(['bed_capacity' => '4']))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $room->fresh()->bed_capacity);
        $this->assertSame(4, $room->fresh()->beds_available);
    }

    public function test_the_controller_rechecks_capacity_under_lock_even_if_validation_was_bypassed(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $this->app->bind(RoomCategoryRequest::class, fn () => new class extends RoomCategoryRequest
        {
            public function withValidator(Validator $validator): void {}
        });
        $this->actingAs($this->createStaff('content-admin'));

        $this->from(route('admin.room-categories.edit', $room))
            ->put(route('admin.room-categories.update', $room), $this->payload(['name' => 'Tidak Tersimpan', 'bed_capacity' => '3']))
            ->assertRedirect(route('admin.room-categories.edit', $room))
            ->assertSessionHasErrors(['bed_capacity' => 'Kapasitas tidak boleh lebih kecil dari tempat tidur tersedia saat ini (4). Perbarui ketersediaan terlebih dahulu.']);

        $room->refresh();
        $this->assertSame(12, $room->bed_capacity);
        $this->assertSame('Kamar Kelas Contoh', $room->name);
    }

    public function test_a_room_is_deactivated_through_the_edit_form_and_leaves_the_active_scope(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'is_active' => true]);
        $this->createRoomCategory(['name' => 'Kamar Tetap Aktif']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $room), $this->payload(['is_active' => '0', 'bed_capacity' => (string) $room->bed_capacity]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('room_categories', ['id' => $room->id, 'is_active' => 0]);
        $this->assertSame(['Kamar Tetap Aktif'], RoomCategory::query()->active()->pluck('name')->all());
    }

    // --- Cover dan galeri (D-41, MED-05, Review Focus 4) ----------------------------------------------

    public function test_cover_upload_on_create_stores_media_for_the_room_category(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.room-categories.store'), $this->payload([
                'cover' => ImageFixtures::jpeg(1200, 800, 'cover.jpg'),
                'cover_alt' => 'Foto sampul kamar',
                'gallery' => [0 => ImageFixtures::jpeg(900, 600, 'g1.jpg')],
                'gallery_alt' => [0 => 'Tempat tidur pasien'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.room-categories.index'));

        $room = RoomCategory::query()->sole();
        $cover = Media::query()->where('collection', 'cover')->sole();
        $gallery = Media::query()->where('collection', 'gallery')->sole();

        $this->assertSame(RoomCategory::class, $cover->model_type);
        $this->assertSame($room->id, $cover->model_id);
        $this->assertSame('Foto sampul kamar', $cover->alt_text);
        $this->assertSame($admin->id, $cover->uploaded_by);
        $this->assertSame('Tempat tidur pasien', $gallery->alt_text);
        Storage::disk('public')->assertExists($cover->directory.'/'.$cover->file_name);
        Storage::disk('public')->assertExists($cover->thumb_path);
    }

    public function test_replacing_the_cover_removes_the_old_file_after_commit(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $old = $this->attachCover($room, 'Sampul lama');
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $room), $this->payload([
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

    public function test_update_without_a_new_cover_keeps_it_a_changed_alt_updates_it_and_an_empty_alt_is_rejected(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $cover = $this->attachCover($room, 'Sampul lama');
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.room-categories.update', $room), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($cover->id, Media::query()->where('collection', 'cover')->sole()->id);

        $this->put(route('admin.room-categories.update', $room), $this->payload(['cover_alt' => '  Sampul diperbarui  ']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Sampul diperbarui', Media::query()->where('collection', 'cover')->sole()->alt_text);

        $this->put(route('admin.room-categories.update', $room), $this->payload(['name' => 'Nama Tidak Tersimpan', 'cover_alt' => '  ']))
            ->assertSessionHasErrors(['cover_alt' => 'Teks alternatif gambar wajib diisi.']);
        $this->assertSame('Sampul diperbarui', Media::query()->where('collection', 'cover')->sole()->alt_text);
        $this->assertSame('Kamar Kelas Contoh', $room->fresh()->name);
    }

    public function test_an_empty_cover_alt_without_a_cover_is_not_an_error(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $room), $this->payload(['cover_alt' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_gallery_image_without_alt_text_is_rejected_and_nothing_is_stored(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => ''],
            ]))
            ->assertSessionHasErrors(['gallery_alt.0' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame(0, RoomCategory::query()->count());
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
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);

        foreach (range(1, $existing) as $position) {
            $this->createMediaRow([
                'model_type' => RoomCategory::class,
                'model_id' => $room->id,
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
            ->put(route('admin.room-categories.update', $room), $this->payload(['gallery' => $files, 'gallery_alt' => $alts]))
            ->assertSessionHasErrors(['gallery' => 'Galeri maksimal 20 gambar.']);

        $this->assertSame($existing, Media::query()->where('collection', 'gallery')->count());
    }

    public function test_hostile_and_undersized_uploads_are_rejected_without_storing_anything_sek09_sek10(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $hostile = [
            ImageFixtures::svgWithScript(),
            ImageFixtures::phpWithJpegHeader(),
            ImageFixtures::textNamedJpg(),
            ImageFixtures::jpeg(799, 449, 'kecil.jpg'),
        ];

        foreach ($hostile as $file) {
            $this->post(route('admin.room-categories.store'), $this->payload(['cover' => $file, 'cover_alt' => 'x']))
                ->assertSessionHasErrors('cover');
        }

        $this->post(route('admin.room-categories.store'), $this->payload([
            'gallery' => [0 => ImageFixtures::svgWithScript('galeri.svg'), 1 => ImageFixtures::phpWithJpegHeader('galeri.php.jpg')],
            'gallery_alt' => [0 => 'x', 1 => 'y'],
        ]))->assertSessionHasErrors(['gallery.0', 'gallery.1']);

        $this->assertSame(0, RoomCategory::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_content_admin_manages_room_images_but_registration_officer_cannot(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $a = $this->attachGalleryImage($room, 'Foto A');
        $b = $this->attachGalleryImage($room, 'Foto B');
        $editUrl = route('admin.room-categories.edit', $room);

        $this->actingAs($this->createStaff('registration-officer'));
        $this->patch(route('admin.media.update', $a), ['alt_text' => 'Diubah paksa'])->assertForbidden();
        $this->post(route('admin.media.reorder'), ['ids' => [$b->id, $a->id]])->assertForbidden();
        $this->delete(route('admin.media.destroy', $a))->assertForbidden();
        $this->assertSame('Foto A', $a->fresh()->alt_text);
        $this->assertDatabaseHas('media', ['id' => $a->id]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->createStaff('content-admin'));

        $this->from($editUrl)
            ->patch(route('admin.media.update', $a), ['alt_text' => 'Foto A baru'])
            ->assertRedirect($editUrl);
        $this->assertSame('Foto A baru', $a->fresh()->alt_text);

        $path = $a->directory.'/'.$a->file_name;
        $this->from($editUrl)->delete(route('admin.media.destroy', $a))->assertRedirect($editUrl);
        $this->assertDatabaseMissing('media', ['id' => $a->id]);
        Storage::disk('public')->assertMissing($path);
    }

    // --- Hapus selalu boleh (CRD-13) ------------------------------------------------------------------

    public function test_deleting_a_room_category_removes_its_media_files_crd13(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.room-categories.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
            'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
            'gallery_alt' => [0 => 'Galeri'],
        ]));
        $room = RoomCategory::query()->sole();
        $paths = Media::query()->get()->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])->all();
        $this->assertCount(4, $paths);

        $this->delete(route('admin.room-categories.destroy', $room))
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil dihapus.');

        $this->assertDatabaseMissing('room_categories', ['id' => $room->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'room_category.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_a_room_category_is_always_deletable_by_a_manager_even_when_full_or_active(string $role): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Aktif Penuh', 'bed_capacity' => 10, 'beds_available' => 0, 'is_active' => true]);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.room-categories.destroy', $room))
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil dihapus.');

        $this->assertDatabaseMissing('room_categories', ['id' => $room->id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'room_category.deleted')->count());
    }

    // --- Rute dan penolakan ---------------------------------------------------------------------------

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/kamar/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_the_structure_routes(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Aman', 'bed_capacity' => 12, 'beds_available' => 4]);

        $requests = [
            ['GET', route('admin.room-categories.create'), []],
            ['POST', route('admin.room-categories.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['GET', route('admin.room-categories.edit', $room), []],
            ['PUT', route('admin.room-categories.update', $room), $this->payload(['name' => 'Diubah Paksa'])],
            ['DELETE', route('admin.room-categories.destroy', $room), []],
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

        $this->assertSame('Kamar Aman', $room->fresh()->name);
        $this->assertDatabaseMissing('room_categories', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseHas('room_categories', ['id' => $room->id]);
    }
}
