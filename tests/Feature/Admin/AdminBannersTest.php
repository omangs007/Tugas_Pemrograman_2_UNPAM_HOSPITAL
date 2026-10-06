<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Banner;
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
 * Admin #39, tab Banner (T6, V-31): CRUD, periode tayang, tautan tombol, unggahan gambar (Review Focus 4),
 * akses per peran, dan mode ketat pada daftar.
 */
class AdminBannersTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Senin, 5 Oktober 2026 pukul 10.00 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Promo Pemeriksaan Gratis',
            'subtitle' => 'Berlaku sampai akhir bulan',
            'button_label' => 'Lihat Dokter',
            'button_url' => '/dokter',
            'sort_order' => '2',
            'is_active' => '1',
            'starts_at' => '',
            'ends_at' => '',
            'image' => ImageFixtures::jpeg(1600, 600, 'banner.jpg'),
            'image_alt' => 'Banner promo pemeriksaan',
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
        $banner = $this->createBanner();
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.banners.index'))->assertStatus($status);
        $this->get(route('admin.banners.create'))->assertStatus($status);
        $this->get(route('admin.banners.edit', $banner))->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $banner = $this->createBanner();

        $this->get(route('admin.banners.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.banners.edit', $banner))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.banners.index'))
            ->assertForbidden();
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $banner = $this->createBanner();

        $this->assertSame(url('/admin/konten/banner'), route('admin.banners.index'));
        $this->assertSame(url('/admin/konten/banner/buat'), route('admin.banners.create'));
        $this->assertSame(url('/admin/konten/banner/'.$banner->id.'/ubah'), route('admin.banners.edit', $banner));
        $this->assertSame(url('/admin/konten/banner/'.$banner->id), route('admin.banners.update', $banner));
        $this->assertFalse(Route::has('admin.banners.show'));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/konten/banner/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_cannot_store_update_or_destroy(): void
    {
        $banner = $this->createBanner(['title' => 'Judul Asli']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->post(route('admin.banners.store'), $this->payload())->assertForbidden();
        $this->put(route('admin.banners.update', $banner), $this->payload(['title' => 'Diubah Petugas']))->assertForbidden();
        $this->delete(route('admin.banners.destroy', $banner))->assertForbidden();

        $this->assertSame(1, Banner::query()->count());
        $this->assertSame('Judul Asli', $banner->fresh()->title);
        $this->assertSame(0, Media::query()->count());
    }

    // --- Daftar ---------------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_with_period_and_status_labels_in_strict_mode(): void
    {
        $tayang = $this->createBanner(['title' => 'Banner Tayang', 'sort_order' => 1]);
        $terjadwal = $this->createBanner([
            'title' => 'Banner Terjadwal',
            'sort_order' => 2,
            'starts_at' => '2026-10-10 08:00:00',
            'ends_at' => '2026-10-20 17:30:00',
        ]);
        $berakhir = $this->createBanner(['title' => 'Banner Berakhir', 'sort_order' => 3, 'ends_at' => '2026-10-01 00:00:00']);
        $nonaktif = $this->createBanner(['title' => 'Banner Nonaktif', 'sort_order' => 4, 'is_active' => false]);
        $mulai = $this->createBanner(['title' => 'Banner Mulai', 'sort_order' => 5, 'starts_at' => '2026-10-01 09:00:00']);

        foreach ([$tayang, $terjadwal, $mulai] as $banner) {
            $this->createMediaRow([
                'model_type' => Banner::class,
                'model_id' => $banner->id,
                'collection' => 'image',
                'alt_text' => 'Gambar '.$banner->title,
            ]);
        }

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSee('data-confirm-action="'.route('admin.banners.destroy', $tayang).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.banners.destroy', $nonaktif).'"', false)
            ->assertSee('href="'.route('admin.banners.edit', $berakhir).'"', false)
            ->getContent();

        $this->assertSame(
            ['Banner Tayang', 'Banner Terjadwal', 'Banner Berakhir', 'Banner Nonaktif', 'Banner Mulai'],
            $this->cells($html, 'Judul'),
        );
        $this->assertSame(['1', '2', '3', '4', '5'], $this->cells($html, 'Urutan'));
        $this->assertSame(
            [
                'Tanpa batas',
                '10 Okt 2026 08.00 – 20 Okt 2026 17.30',
                'Hingga 01 Okt 2026 00.00',
                'Tanpa batas',
                'Mulai 01 Okt 2026 09.00',
            ],
            $this->cells($html, 'Periode'),
        );
        $this->assertSame(['Tayang', 'Terjadwal', 'Berakhir', 'Nonaktif', 'Tayang'], $this->cells($html, 'Status'));
    }

    public function test_index_shows_module_tabs_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.pages.index').'"', false)
            ->assertSee('href="'.route('admin.banners.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Tambah Banner')
            ->assertSee('href="'.route('admin.banners.create').'"', false)
            ->assertSee('Konten');
    }

    public function test_index_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSee('Belum ada banner');
    }

    public function test_index_filters_by_status_keyword_and_sort(): void
    {
        $this->createBanner(['title' => 'Alfa Promo', 'sort_order' => 3]);
        $this->createBanner(['title' => 'Beta Promo', 'sort_order' => 1]);
        $this->createBanner(['title' => 'Gamma Lama', 'sort_order' => 2, 'is_active' => false]);
        $this->createBanner(['title' => '100% Gratis', 'sort_order' => 4]);
        $admin = $this->createStaff('content-admin');

        $titles = fn (array $query): array => $this->cells(
            $this->actingAs($admin)->get(route('admin.banners.index', $query))->assertOk()->getContent(),
            'Judul',
        );

        $this->assertSame(['Beta Promo', 'Gamma Lama', 'Alfa Promo', '100% Gratis'], $titles([]));
        $this->assertSame(['Beta Promo', 'Alfa Promo', '100% Gratis'], $titles(['status' => 'aktif']));
        $this->assertSame(['Gamma Lama'], $titles(['status' => 'nonaktif']));
        $this->assertSame(['Alfa Promo', 'Beta Promo'], $titles(['q' => 'promo', 'urut' => 'judul']));
        $this->assertSame(['100% Gratis'], $titles(['q' => '%']), 'Tanda % pada kata kunci di-escape.');
        $this->assertSame(['Gamma Lama', 'Beta Promo', 'Alfa Promo', '100% Gratis'], $titles(['urut' => '-judul']));
        $this->assertSame(['100% Gratis', 'Alfa Promo', 'Gamma Lama', 'Beta Promo'], $titles(['urut' => '-urutan']));
    }

    public function test_invalid_filters_are_ignored_with_a_warning_and_array_params_do_not_cause_a_500(): void
    {
        $this->createBanner(['title' => 'Banner Satu']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.banners.index', ['status' => 'zzz', 'urut' => 'kolom-liar']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Banner Satu');

        $this->get(route('admin.banners.index', ['q' => ['x'], 'status' => ['x'], 'urut' => ['x'], 'page' => ['x']]))
            ->assertOk();
    }

    // --- Form ---------------------------------------------------------------------------------------------------

    public function test_create_form_renders_all_fields_and_no_slug(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.banners.create'))
            ->assertOk()
            ->assertSee('Tambah Banner')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="subtitle"', false)
            ->assertSee('name="button_label"', false)
            ->assertSee('name="button_url"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('type="datetime-local"', false)
            ->assertSee('name="starts_at"', false)
            ->assertSee('name="ends_at"', false)
            ->assertSee('name="image"', false)
            ->assertSee('name="image_alt"', false)
            ->assertSee('data-max-kb="4096"', false)
            ->assertDontSee('name="slug"', false)
            ->assertSee('action="'.route('admin.banners.store').'"', false);
    }

    public function test_edit_form_shows_current_values_period_and_current_image(): void
    {
        $banner = $this->createBanner([
            'title' => 'Banner Lama',
            'subtitle' => 'Sub lama',
            'button_label' => 'Jadwal Dokter',
            'button_url' => '/jadwal-dokter',
            'starts_at' => '2026-10-10 08:00:00',
            'ends_at' => '2026-10-20 17:30:00',
        ]);
        $this->createMediaRow([
            'model_type' => Banner::class,
            'model_id' => $banner->id,
            'collection' => 'image',
            'alt_text' => 'Alt banner lama',
        ]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.banners.edit', $banner))
            ->assertOk()
            ->assertSee('Ubah Banner Lama')
            ->assertSee('value="Banner Lama"', false)
            ->assertSee('value="Sub lama"', false)
            ->assertSee('value="Jadwal Dokter"', false)
            ->assertSee('value="/jadwal-dokter"', false)
            ->assertSee('value="2026-10-10T08:00"', false)
            ->assertSee('value="2026-10-20T17:30"', false)
            ->assertSee('value="Alt banner lama"', false)
            ->assertSee('Gambar saat ini')
            ->assertSee('action="'.route('admin.banners.update', $banner).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    // --- Tambah -------------------------------------------------------------------------------------------------

    public function test_content_admin_adds_a_banner_with_an_image_and_the_audit_row_is_written(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.banners.index'))
            ->assertSessionHas('success', 'Banner berhasil ditambahkan.');

        $banner = Banner::query()->sole();
        $this->assertSame('Promo Pemeriksaan Gratis', $banner->title);
        $this->assertSame('Berlaku sampai akhir bulan', $banner->subtitle);
        $this->assertSame('Lihat Dokter', $banner->button_label);
        $this->assertSame('/dokter', $banner->button_url);
        $this->assertSame(2, $banner->sort_order);
        $this->assertTrue($banner->is_active);
        $this->assertNull($banner->starts_at);
        $this->assertNull($banner->ends_at);

        $media = Media::query()->sole();
        $this->assertSame('image', $media->collection);
        $this->assertSame(Banner::class, $media->model_type);
        $this->assertSame($banner->id, $media->model_id);
        $this->assertSame('Banner promo pemeriksaan', $media->alt_text);
        $this->assertSame('public', $media->disk);
        $this->assertStringStartsWith('image/'.now()->format('Y/m'), $media->directory);
        $this->assertNotNull($media->thumb_path);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);

        $audit = AuditLog::query()->where('action', 'banner.created')->sole();
        $this->assertSame('Banner "Promo Pemeriksaan Gratis" ditambahkan.', $audit->description);
        $this->assertSame($admin->id, $audit->user_id);
    }

    public function test_period_inputs_are_stored_in_the_application_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.banners.store'), $this->payload([
                'starts_at' => '2026-10-10T08:00',
                'ends_at' => '2026-10-20T17:30',
            ]))
            ->assertSessionHasNoErrors();

        $banner = Banner::query()->sole();
        $this->assertSame('2026-10-10 08:00', $banner->starts_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-10-20 17:30', $banner->ends_at?->format('Y-m-d H:i'));
        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'starts_at' => '2026-10-10 08:00:00', 'ends_at' => '2026-10-20 17:30:00']);
        $this->assertSame('Terjadwal', $banner->scheduleLabel());
    }

    public function test_button_may_be_left_out_entirely_and_https_urls_are_accepted(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin);

        $this->post(route('admin.banners.store'), $this->payload(['title' => 'Tanpa Tombol', 'button_label' => '', 'button_url' => '']))
            ->assertSessionHasNoErrors();
        $this->post(route('admin.banners.store'), $this->payload(['title' => 'Tombol Eksternal', 'button_label' => 'Buka', 'button_url' => 'https://contoh.test/promo?x=1']))
            ->assertSessionHasNoErrors();
        $this->post(route('admin.banners.store'), $this->payload(['title' => 'Tombol Internal', 'button_label' => 'Jadwal', 'button_url' => '/jadwal-dokter?poli=anak']))
            ->assertSessionHasNoErrors();

        $none = Banner::query()->where('title', 'Tanpa Tombol')->sole();
        $this->assertNull($none->button_label);
        $this->assertNull($none->button_url);
        $this->assertSame('https://contoh.test/promo?x=1', Banner::query()->where('title', 'Tombol Eksternal')->sole()->button_url);
        $this->assertSame('/jadwal-dokter?poli=anak', Banner::query()->where('title', 'Tombol Internal')->sole()->button_url);
    }

    /**
     * Pesan `null` = cukup ada galat pada field itu (pesan persisnya bergantung pada aturan Laravel mana yang gagal lebih dulu).
     *
     * @return array<string, array{array<string, mixed>, string, string|null}>
     */
    public static function invalidInputs(): array
    {
        $linkMessage = 'Tautan harus berupa alamat internal (diawali /) atau URL https.';

        return [
            'judul kosong' => [['title' => ''], 'title', 'Judul banner wajib diisi.'],
            'tautan javascript' => [['button_url' => 'javascript:alert(1)'], 'button_url', $linkMessage],
            'tautan data' => [['button_url' => 'data:text/html,x'], 'button_url', $linkMessage],
            'tautan http' => [['button_url' => 'http://contoh.test/a'], 'button_url', $linkMessage],
            'tautan protokol relatif' => [['button_url' => '//evil.test/a'], 'button_url', $linkMessage],
            'tautan tanpa garis miring' => [['button_url' => 'dokter'], 'button_url', $linkMessage],
            'label tanpa tautan' => [['button_label' => 'Lihat', 'button_url' => ''], 'button_url', 'Tautan tombol wajib diisi bila label diisi.'],
            'tautan tanpa label' => [['button_label' => '', 'button_url' => '/dokter'], 'button_label', 'Label tombol wajib diisi bila tautan diisi.'],
            'selesai sebelum mulai' => [['starts_at' => '2026-10-20T08:00', 'ends_at' => '2026-10-10T08:00'], 'ends_at', 'Waktu selesai tayang harus setelah waktu mulai tayang.'],
            'selesai sama dengan mulai' => [['starts_at' => '2026-10-20T08:00', 'ends_at' => '2026-10-20T08:00'], 'ends_at', 'Waktu selesai tayang harus setelah waktu mulai tayang.'],
            'mulai bukan tanggal' => [['starts_at' => 'kapan-saja'], 'starts_at', 'Format waktu mulai tayang tidak valid.'],
            'gambar wajib' => [['image' => null], 'image', 'Gambar banner wajib dipilih.'],
            'alt wajib' => [['image_alt' => ''], 'image_alt', 'Teks alternatif banner wajib diisi.'],
            'banner terlalu kecil' => [['image' => 'SMALL'], 'image', 'Dimensi banner minimal 1200 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'banner lebih dari 4 MB' => [['image' => 'BIG'], 'image', 'Ukuran banner maksimal 4 MB.'],
            'banner SVG berskrip' => [['image' => 'SVG'], 'image', null],
            'banner teks bernama jpg' => [['image' => 'TEXT'], 'image', null],
            'banner PHP berkepala JPEG' => [['image' => 'PHP'], 'image', null],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_and_stores_nothing(array $override, string $field, ?string $message): void
    {
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $response = $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.banners.create'))
            ->post(route('admin.banners.store'), $this->payload($override))
            ->assertRedirect(route('admin.banners.create'));

        $response->assertSessionHasErrors($field);
        $errors = session('errors')->get($field);

        if ($message === null) {
            $this->assertNotEmpty($errors);
        } else {
            $this->assertContains($message, $errors);
        }

        $this->assertSame(0, Banner::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_png_with_a_jpg_name_is_accepted_and_stored_with_its_real_extension(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.banners.store'), $this->payload(['image' => ImageFixtures::pngNamedJpg(1600, 600, 'banner.jpg')]))
            ->assertSessionHasNoErrors();

        $media = Media::query()->sole();
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame('png', $media->extension);
        $this->assertStringEndsWith('.png', $media->file_name);
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.banners.create'))
            ->post(route('admin.banners.store'), $this->payload(['title' => '', 'subtitle' => 'Subjudul Lama', 'image' => null]))
            ->assertRedirect(route('admin.banners.create'));

        $this->get(route('admin.banners.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Judul banner wajib diisi.')
            ->assertSee('Gambar banner wajib dipilih.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="Subjudul Lama"', false);
    }

    public function test_unknown_fields_and_ids_are_not_mass_assignable_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.banners.store'), $this->payload(['id' => 999, 'created_at' => '2000-01-01 00:00:00']))
            ->assertSessionHasNoErrors();

        $banner = Banner::query()->sole();
        $this->assertNotSame(999, $banner->id);
        $this->assertNotSame('2000-01-01', $banner->created_at?->toDateString());
    }

    // --- Ubah ------------------------------------------------------------------------------------------------------

    public function test_update_changes_fields_toggles_status_and_clears_the_button(): void
    {
        $banner = $this->createBanner(['title' => 'Banner Lama', 'button_label' => 'Lama', 'button_url' => '/dokter', 'is_active' => true]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.banners.update', $banner), $this->payload([
            'title' => 'Banner Baru',
            'button_label' => '',
            'button_url' => '',
            'is_active' => '0',
            'image' => null,
            'image_alt' => '',
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.banners.index'))
            ->assertSessionHas('success', 'Banner berhasil diperbarui.');

        $banner->refresh();
        $this->assertSame('Banner Baru', $banner->title);
        $this->assertNull($banner->button_label);
        $this->assertNull($banner->button_url);
        $this->assertFalse($banner->is_active);
        $this->assertSame('Nonaktif', $banner->scheduleLabel());
        $this->assertNotContains($banner->id, Banner::query()->currentlyActive()->pluck('id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'banner.updated']);
        $this->assertSame(0, Media::query()->count(), 'Update tanpa berkas tidak membuat media.');
    }

    public function test_replacing_the_image_removes_the_old_file_after_commit(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.banners.store'), $this->payload([
            'image' => ImageFixtures::jpeg(1600, 600, 'lama.jpg'),
            'image_alt' => 'Gambar lama',
        ]));
        $banner = Banner::query()->sole();
        $old = Media::query()->sole();
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->put(route('admin.banners.update', $banner), $this->payload([
            'image' => ImageFixtures::jpeg(1500, 700, 'baru.jpg'),
            'image_alt' => 'Gambar baru',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index'));

        $new = Media::query()->sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Gambar baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_image_keeps_the_current_one_and_a_changed_alt_updates_it(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.banners.store'), $this->payload(['image_alt' => 'Alt lama']));
        $banner = Banner::query()->sole();
        $media = Media::query()->sole();

        $this->put(route('admin.banners.update', $banner), $this->payload(['image' => null, 'image_alt' => '  Alt baru  ']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.banners.index'));

        $same = Media::query()->sole();
        $this->assertSame($media->id, $same->id);
        $this->assertSame('Alt baru', $same->alt_text);
        Storage::disk('public')->assertExists($same->directory.'/'.$same->file_name);
    }

    public function test_update_with_an_empty_alt_is_rejected_while_an_image_exists_and_nothing_is_saved(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.banners.store'), $this->payload(['image_alt' => 'Alt lama']));
        $banner = Banner::query()->sole();

        $this->put(route('admin.banners.update', $banner), $this->payload(['title' => 'Judul Tidak Tersimpan', 'image' => null, 'image_alt' => '  ']))
            ->assertSessionHasErrors(['image_alt' => 'Teks alternatif banner wajib diisi.']);

        $this->assertSame('Alt lama', Media::query()->sole()->alt_text);
        $this->assertSame('Promo Pemeriksaan Gratis', $banner->fresh()->title);
    }

    public function test_update_may_leave_the_image_out_when_the_banner_has_none_yet(): void
    {
        $banner = $this->createBanner();

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.banners.update', $banner), $this->payload(['image' => null, 'image_alt' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.banners.index'));

        $this->assertSame(0, Media::query()->count());
    }

    public function test_update_with_a_dangerous_upload_is_rejected_and_the_old_image_stays(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.banners.store'), $this->payload());
        $banner = Banner::query()->sole();
        $media = Media::query()->sole();
        $files = Storage::disk('public')->allFiles();

        $this->put(route('admin.banners.update', $banner), $this->payload(['image' => ImageFixtures::svgWithScript('banner.svg')]))
            ->assertSessionHasErrors('image');
        $this->put(route('admin.banners.update', $banner), $this->payload(['image' => ImageFixtures::phpWithJpegHeader()]))
            ->assertSessionHasErrors('image');

        $this->assertSame($media->id, Media::query()->sole()->id);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    // --- Hapus ---------------------------------------------------------------------------------------------------------

    public function test_deleting_a_banner_removes_the_row_its_media_rows_and_files(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.banners.store'), $this->payload(['title' => 'Banner Dihapus']));
        $banner = Banner::query()->sole();
        $media = Media::query()->sole();
        $paths = [$media->directory.'/'.$media->file_name, $media->thumb_path];

        $this->delete(route('admin.banners.destroy', $banner))
            ->assertRedirect(route('admin.banners.index'))
            ->assertSessionHas('success', 'Banner berhasil dihapus.');

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $audit = AuditLog::query()->where('action', 'banner.deleted')->sole();
        $this->assertSame('Banner "Banner Dihapus" dihapus.', $audit->description);
    }

    public function test_super_admin_can_delete_an_active_banner_too(): void
    {
        $banner = $this->createBanner(['title' => 'Banner Aktif']);

        $this->actingAs($this->createStaff('super-admin'))
            ->delete(route('admin.banners.destroy', $banner))
            ->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider berjalan sebelum GD siap).
     */
    private function materialize(mixed $value): mixed
    {
        return match ($value) {
            'SMALL' => ImageFixtures::jpeg(1000, 300, 'kecil.jpg'),
            'BIG' => ImageFixtures::oversizedJpeg(4200, 1600, 600, 'besar.jpg'),
            'SVG' => ImageFixtures::svgWithScript('banner.svg'),
            'TEXT' => ImageFixtures::textNamedJpg(),
            'PHP' => ImageFixtures::phpWithJpegHeader(),
            default => $value,
        };
    }
}
