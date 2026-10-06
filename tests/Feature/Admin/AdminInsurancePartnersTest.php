<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #39 tab Mitra Asuransi (T11): daftar, CRUD, logo, nonaktifkan, dan aturan hapus BR-180 yang berlaku
 * juga untuk Super Admin (F4A-R21, CRD-13, Review Focus 5).
 */
class AdminInsurancePartnersTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithSchedules;
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
            'name' => 'Asuransi Contoh Sehat',
            'type' => 'asuransi',
            'description' => 'Mitra contoh untuk pengujian.',
            'website_url' => 'https://contoh-sehat.test',
            'is_active' => '1',
            'sort_order' => '2',
        ], $overrides);
    }

    private function attachLogo(InsurancePartner $partner, string $alt = 'Logo contoh'): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(400, 200, 'logo.jpg'), 'logo', $partner, ['alt_text' => $alt]);
    }

    /**
     * Membuat $times appointment asuransi yang merujuk mitra (FK RESTRICT).
     */
    private function referenceFrom(InsurancePartner $partner, int $times): void
    {
        $session = $this->createSession();

        for ($index = 0; $index < $times; $index++) {
            $this->bookAppointment($session, AppointmentStatus::Confirmed, [
                'payment_type' => PaymentType::Asuransi,
                'insurance_partner_id' => $partner->id,
            ]);
        }
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
        $this->createInsurancePartner(['name' => 'Mitra Akses']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.insurance-partners.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.insurance-partners.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.insurance-partners.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $partner = $this->createInsurancePartner();

        $this->assertSame(url('/admin/konten/asuransi'), route('admin.insurance-partners.index'));
        $this->assertSame(url('/admin/konten/asuransi/buat'), route('admin.insurance-partners.create'));
        $this->assertSame(url('/admin/konten/asuransi/'.$partner->id.'/ubah'), route('admin.insurance-partners.edit', $partner));
        $this->assertSame(url('/admin/konten/asuransi/'.$partner->id), route('admin.insurance-partners.update', $partner));
    }

    public function test_index_renders_many_rows_with_logo_type_status_and_an_always_visible_delete_button(): void
    {
        $sehat = $this->createInsurancePartner(['name' => 'Asuransi Sehat Contoh', 'type' => 'asuransi', 'sort_order' => 1]);
        $karya = $this->createInsurancePartner(['name' => 'PT Karya Contoh', 'type' => 'perusahaan', 'sort_order' => 2]);
        $lama = $this->createInsurancePartner(['name' => 'Asuransi Lama Contoh', 'type' => 'asuransi', 'sort_order' => 3, 'is_active' => false]);
        $this->createInsurancePartner(['name' => 'Mitra Tanpa Logo', 'type' => 'perusahaan', 'sort_order' => 4]);

        $sehatLogo = $this->attachLogo($sehat, 'Logo Sehat');
        $this->attachLogo($karya, 'Logo Karya');
        $this->referenceFrom($lama, 1);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.insurance-partners.index'))
            ->assertOk()
            ->assertSeeInOrder(['Asuransi Sehat Contoh', 'PT Karya Contoh', 'Asuransi Lama Contoh', 'Mitra Tanpa Logo'])
            ->assertSee('data-label="Jenis">Asuransi</td>', false)
            ->assertSee('data-label="Jenis">Perusahaan</td>', false)
            ->assertSee('<span class="badge text-bg-success">Aktif</span>', false)
            ->assertSee('<span class="badge text-bg-secondary">Nonaktif</span>', false)
            ->assertSee((string) $sehatLogo->thumb_url, false)
            ->assertSee('images/placeholders/logo.svg', false)
            ->assertSee('data-confirm-action="'.route('admin.insurance-partners.destroy', $sehat).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.insurance-partners.destroy', $lama).'"', false);
    }

    public function test_index_shows_module_tabs_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.insurance-partners.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.insurance-partners.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Konten')
            ->assertSee('Tambah Mitra')
            ->assertSee('href="'.route('admin.insurance-partners.create').'"', false);
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.insurance-partners.index'))
            ->assertOk()
            ->assertSee('Belum ada mitra asuransi');
    }

    public function test_search_status_and_sort_filters(): void
    {
        $this->createInsurancePartner(['name' => 'Asuransi Sehat Contoh', 'sort_order' => 2]);
        $this->createInsurancePartner(['name' => 'PT Karya Contoh', 'type' => 'perusahaan', 'sort_order' => 1]);
        $this->createInsurancePartner(['name' => 'Asuransi Lama Contoh', 'sort_order' => 3, 'is_active' => false]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.insurance-partners.index', ['q' => 'karya']))
            ->assertOk()->assertSee('PT Karya Contoh')->assertDontSee('Asuransi Sehat Contoh');

        $this->get(route('admin.insurance-partners.index', ['status' => 'nonaktif']))
            ->assertOk()->assertSee('Asuransi Lama Contoh')->assertDontSee('PT Karya Contoh');

        $this->get(route('admin.insurance-partners.index', ['status' => 'aktif']))
            ->assertOk()->assertSee('PT Karya Contoh')->assertDontSee('Asuransi Lama Contoh');

        $this->get(route('admin.insurance-partners.index'))
            ->assertSeeInOrder(['PT Karya Contoh', 'Asuransi Sehat Contoh', 'Asuransi Lama Contoh']);

        $this->get(route('admin.insurance-partners.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['Asuransi Lama Contoh', 'Asuransi Sehat Contoh', 'PT Karya Contoh']);
    }

    public function test_invalid_filters_are_ignored_and_array_params_do_not_cause_a_server_error(): void
    {
        $this->createInsurancePartner(['name' => 'Asuransi Sehat Contoh']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.insurance-partners.index', ['status' => 'zzz', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Asuransi Sehat Contoh')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.insurance-partners.index', ['urut' => ['x'], 'status' => ['x'], 'q' => ['x']]))
            ->assertOk();
    }

    public function test_create_form_has_the_partner_fields_logo_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.insurance-partners.create'))
            ->assertOk()
            ->assertSee('Tambah Mitra')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('action="'.route('admin.insurance-partners.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="type"', false)
            ->assertSee('value="asuransi"', false)
            ->assertSee('value="perusahaan"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="website_url"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="logo"', false)
            ->assertSee('name="logo_alt"', false)
            ->assertSee('lencana "Contoh"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_partner_without_a_logo_crd10(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.insurance-partners.store'), $this->payload())
            ->assertRedirect(route('admin.insurance-partners.index'))
            ->assertSessionHas('success', 'Mitra asuransi berhasil ditambahkan.');

        $partner = InsurancePartner::query()->sole();

        $this->assertSame('Asuransi Contoh Sehat', $partner->name);
        $this->assertSame('asuransi-contoh-sehat', $partner->slug);
        $this->assertSame('asuransi', $partner->type);
        $this->assertSame('Asuransi', $partner->type_label);
        $this->assertSame('Mitra contoh untuk pengujian.', $partner->description);
        $this->assertSame('https://contoh-sehat.test', $partner->website_url);
        $this->assertTrue($partner->is_active);
        $this->assertSame(2, $partner->sort_order);
        $this->assertSame(0, Media::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'insurance_partner.created',
            'description' => 'Mitra asuransi "Asuransi Contoh Sehat" ditambahkan.',
        ]);
    }

    public function test_optional_fields_may_be_left_empty_and_the_type_company_is_accepted(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.insurance-partners.store'), $this->payload([
                'name' => 'PT Mitra Kerja Contoh',
                'type' => 'perusahaan',
                'description' => '',
                'website_url' => '',
                'sort_order' => '',
            ]))
            ->assertSessionHasNoErrors();

        $partner = InsurancePartner::query()->sole();

        $this->assertSame('perusahaan', $partner->type);
        $this->assertSame('Perusahaan', $partner->type_label);
        $this->assertNull($partner->description);
        $this->assertNull($partner->website_url);
        $this->assertSame(0, $partner->sort_order);
    }

    public function test_logo_upload_on_create_stores_media_for_the_partner(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.insurance-partners.store'), $this->payload([
                'logo' => ImageFixtures::jpeg(400, 200, 'logo-mitra.jpg'),
                'logo_alt' => 'Logo Asuransi Contoh Sehat',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.insurance-partners.index'));

        $partner = InsurancePartner::query()->sole();
        $logo = Media::query()->where('collection', 'logo')->sole();

        $this->assertSame(InsurancePartner::class, $logo->model_type);
        $this->assertSame($partner->id, $logo->model_id);
        $this->assertSame('Logo Asuransi Contoh Sehat', $logo->alt_text);
        $this->assertSame($admin->id, $logo->uploaded_by);
        $this->assertStringStartsWith('logo/'.now()->format('Y/m'), $logo->directory);
        Storage::disk('public')->assertExists($logo->directory.'/'.$logo->file_name);
        Storage::disk('public')->assertExists($logo->thumb_path);
    }

    public function test_edit_form_shows_values_the_selected_type_and_the_current_logo(): void
    {
        $partner = $this->createInsurancePartner([
            'name' => 'PT Karya Contoh',
            'type' => 'perusahaan',
            'description' => 'Perusahaan contoh.',
            'website_url' => 'https://karya.test',
            'sort_order' => 5,
        ]);
        $this->attachLogo($partner, 'Logo Karya');

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.insurance-partners.edit', $partner))
            ->assertOk()
            ->assertSee('Ubah PT Karya Contoh')
            ->assertSee('action="'.route('admin.insurance-partners.update', $partner).'"', false)
            ->assertSee('value="PT Karya Contoh"', false)
            ->assertSee('value="https://karya.test"', false)
            ->assertSee('Perusahaan contoh.')
            ->assertSee('value="5"', false)
            ->assertSee('value="Logo Karya"', false)
            ->assertSee('Gambar saat ini');
    }

    public function test_update_changes_fields_keeps_the_slug_and_allows_the_unchanged_name(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Asuransi Contoh Sehat', 'type' => 'asuransi']);
        $slug = $partner->slug;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.insurance-partners.update', $partner), $this->payload(['description' => 'Deskripsi baru.']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.insurance-partners.index'))
            ->assertSessionHas('success', 'Mitra asuransi berhasil diperbarui.');

        $this->assertSame('Deskripsi baru.', $partner->fresh()->description);

        $this->put(route('admin.insurance-partners.update', $partner), $this->payload([
            'name' => 'Nama Sudah Berbeda',
            'type' => 'perusahaan',
            'website_url' => '',
        ]))->assertSessionHasNoErrors();

        $partner->refresh();
        $this->assertSame('Nama Sudah Berbeda', $partner->name);
        $this->assertSame($slug, $partner->slug);
        $this->assertSame('perusahaan', $partner->type);
        $this->assertNull($partner->website_url);
        $this->assertDatabaseHas('audit_logs', ['action' => 'insurance_partner.updated']);
    }

    public function test_a_partner_is_deactivated_through_the_edit_form_and_leaves_the_active_list(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Asuransi Contoh Sehat', 'is_active' => true]);
        $this->createInsurancePartner(['name' => 'Mitra Tetap Aktif']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.insurance-partners.update', $partner), $this->payload(['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('insurance_partners', ['id' => $partner->id, 'is_active' => 0]);
        $this->assertSame(['Mitra Tetap Aktif'], InsurancePartner::query()->active()->pluck('name')->all());
    }

    public function test_replacing_the_logo_removes_the_old_file_after_commit(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Mitra Logo']);
        $old = $this->attachLogo($partner, 'Logo lama');
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.insurance-partners.update', $partner), $this->payload([
                'name' => 'Mitra Logo',
                'logo' => ImageFixtures::jpeg(500, 250, 'baru.jpg'),
                'logo_alt' => 'Logo baru',
            ]))
            ->assertSessionHasNoErrors();

        $new = Media::query()->where('collection', 'logo')->sole();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Logo baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_logo_keeps_it_and_a_changed_alt_updates_the_existing_logo(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Mitra Logo']);
        $logo = $this->attachLogo($partner, 'Logo lama');
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.insurance-partners.update', $partner), $this->payload(['name' => 'Mitra Logo']))
            ->assertSessionHasNoErrors();

        $this->assertSame($logo->id, Media::query()->where('collection', 'logo')->sole()->id);

        $this->put(route('admin.insurance-partners.update', $partner), $this->payload(['name' => 'Mitra Logo', 'logo_alt' => '  Logo diperbarui  ']))
            ->assertSessionHasNoErrors();

        $same = Media::query()->where('collection', 'logo')->sole();
        $this->assertSame($logo->id, $same->id);
        $this->assertSame('Logo diperbarui', $same->alt_text);
        Storage::disk('public')->assertExists($same->directory.'/'.$same->file_name);
    }

    public function test_an_empty_logo_alt_is_rejected_while_a_logo_exists_and_keeps_the_old_alt(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Mitra Logo']);
        $this->attachLogo($partner, 'Logo lama');

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.insurance-partners.update', $partner), $this->payload(['name' => 'Nama Tidak Tersimpan', 'logo_alt' => '  ']))
            ->assertSessionHasErrors(['logo_alt' => 'Teks alternatif logo wajib diisi.']);

        $this->assertSame('Logo lama', Media::query()->where('collection', 'logo')->sole()->alt_text);
        $this->assertSame('Mitra Logo', $partner->fresh()->name);
    }

    public function test_an_empty_logo_alt_without_a_logo_is_not_an_error(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Mitra Tanpa Logo']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.insurance-partners.update', $partner), $this->payload(['name' => 'Mitra Tanpa Logo', 'logo_alt' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_deleting_an_unreferenced_partner_removes_its_logo_files_crd13(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.insurance-partners.store'), $this->payload([
                'logo' => ImageFixtures::jpeg(400, 200),
                'logo_alt' => 'Logo',
            ]));
        $partner = InsurancePartner::query()->sole();
        $paths = Media::query()->get()->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])->all();
        $this->assertCount(2, $paths);

        $this->delete(route('admin.insurance-partners.destroy', $partner))
            ->assertRedirect(route('admin.insurance-partners.index'))
            ->assertSessionHas('success', 'Mitra asuransi berhasil dihapus.');

        $this->assertDatabaseMissing('insurance_partners', ['id' => $partner->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'insurance_partner.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_a_partner_referenced_by_appointments_is_not_deleted_even_for_super_admin(string $role): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Mitra Dirujuk']);
        $logo = $this->attachLogo($partner, 'Logo dirujuk');
        $this->referenceFrom($partner, 2);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.insurance-partners.destroy', $partner))
            ->assertRedirect(route('admin.insurance-partners.index'))
            ->assertSessionHas('error', 'Mitra asuransi masih dipakai oleh 2 data. Nonaktifkan saja.');

        $this->assertDatabaseHas('insurance_partners', ['id' => $partner->id]);
        $this->assertSame(2, Appointment::query()->where('insurance_partner_id', $partner->id)->count());
        $this->assertDatabaseHas('media', ['id' => $logo->id]);
        Storage::disk('public')->assertExists($logo->directory.'/'.$logo->file_name);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'insurance_partner.deleted']);
    }

    public function test_a_referenced_partner_can_still_be_deactivated(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Mitra Dirujuk']);
        $this->referenceFrom($partner, 1);

        $this->actingAs($this->createStaff('super-admin'))
            ->put(route('admin.insurance-partners.update', $partner), $this->payload(['name' => 'Mitra Dirujuk', 'is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('insurance_partners', ['id' => $partner->id, 'is_active' => 0]);
        $this->assertSame(
            'Mitra asuransi masih dipakai oleh 1 data. Nonaktifkan saja.',
            $partner->fresh()->deletionBlockedReason(),
        );
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/konten/asuransi/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_partner_routes(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Mitra Aman']);

        $requests = [
            ['GET', route('admin.insurance-partners.create'), []],
            ['POST', route('admin.insurance-partners.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['GET', route('admin.insurance-partners.edit', $partner), []],
            ['PUT', route('admin.insurance-partners.update', $partner), $this->payload(['name' => 'Diubah Paksa'])],
            ['DELETE', route('admin.insurance-partners.destroy', $partner), []],
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

        $this->assertSame('Mitra Aman', $partner->fresh()->name);
        $this->assertDatabaseMissing('insurance_partners', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseHas('insurance_partners', ['id' => $partner->id]);
    }
}
