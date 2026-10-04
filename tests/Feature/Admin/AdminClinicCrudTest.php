<?php

namespace Tests\Feature\Admin;

use App\Enums\ClinicType;
use App\Http\Requests\Admin\ClinicRequest;
use App\Models\Clinic;
use App\Models\Media;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminClinicCrudTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->specialty = $this->createSpecialty(['name' => 'Anak']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Poliklinik Contoh',
            'code' => 'XYZ',
            'type' => 'poliklinik',
            'is_bookable' => '1',
            'specialty_id' => (string) $this->specialty->id,
            'short_description' => 'Layanan contoh untuk pengujian.',
            'description' => '<p>Deskripsi <strong>lengkap</strong>.</p>',
            'location' => 'Gedung A Lt. 1',
            'is_active' => '1',
            'sort_order' => '3',
        ], $overrides);
    }

    public function test_create_form_renders_with_rich_text_cover_gallery_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.create'))
            ->assertOk()
            ->assertSee('Tambah Poliklinik')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('name="code"', false)
            ->assertSee('name="type"', false)
            ->assertSee('name="cover"', false)
            ->assertSee('name="cover_alt"', false)
            ->assertSee('name="gallery[0]"', false)
            ->assertSee('name="gallery_alt[0]"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_clinic_with_unique_code_crd02(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload())
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('success', 'Poliklinik berhasil ditambahkan.');

        $clinic = Clinic::query()->where('code', 'XYZ')->sole();

        $this->assertSame('poliklinik-contoh', $clinic->slug);
        $this->assertSame(ClinicType::Poliklinik, $clinic->type);
        $this->assertTrue($clinic->is_bookable);
        $this->assertTrue($clinic->is_active);
        $this->assertSame($this->specialty->id, $clinic->specialty_id);
        $this->assertSame(3, $clinic->sort_order);
        $this->assertNull($clinic->meta_title);
        $this->assertStringContainsString('<strong>lengkap</strong>', (string) $clinic->description);
        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.created']);
    }

    public function test_code_is_normalized_to_uppercase_and_duplicates_are_rejected_case_insensitively(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->post(route('admin.clinics.store'), $this->payload(['code' => 'xyz']))
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertDatabaseHas('clinics', ['code' => 'XYZ']);

        $this->post(route('admin.clinics.store'), $this->payload(['name' => 'Poliklinik Lain', 'code' => 'xyz']))
            ->assertSessionHasErrors(['code' => 'Kode poliklinik sudah digunakan.']);

        $this->assertSame(1, Clinic::query()->count());
    }

    public function test_description_is_sanitized_when_saved_through_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>',
            ]))
            ->assertRedirect(route('admin.clinics.index'));

        $description = (string) Clinic::query()->where('code', 'XYZ')->value('description');

        $this->assertStringContainsString('Aman', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('javascript:', $description);
    }

    public function test_support_service_cannot_be_bookable_but_can_be_saved_as_not_bookable(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.clinics.store'), $this->payload(['type' => 'penunjang', 'is_bookable' => '1']))
            ->assertSessionHasErrors(['is_bookable' => 'Layanan penunjang tidak dapat dibuka untuk pendaftaran kunjungan.']);

        $this->post(route('admin.clinics.store'), $this->payload(['type' => 'penunjang', 'is_bookable' => '0', 'specialty_id' => null]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.clinics.index'));

        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $this->assertSame(ClinicType::Penunjang, $clinic->type);
        $this->assertFalse($clinic->is_bookable);
        $this->assertNull($clinic->specialty_id);
    }

    public function test_update_changes_fields_keeps_the_slug_and_can_close_online_booking_crd02(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Lama', 'code' => 'LMA', 'is_bookable' => true]);
        $slug = $clinic->slug;
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Baru', 'code' => 'LMA', 'is_bookable' => '0']))
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('success', 'Poliklinik berhasil diperbarui.');

        $clinic->refresh();
        $this->assertSame('Poliklinik Baru', $clinic->name);
        $this->assertSame($slug, $clinic->slug);
        $this->assertFalse($clinic->is_bookable);
        $this->assertNotContains('Poliklinik Baru', Clinic::query()->bookable()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.updated']);

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Baru', 'code' => 'LMA', 'is_active' => '0']))
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertNotContains('Poliklinik Baru', Clinic::query()->active()->pluck('name')->all());
    }

    public function test_code_cannot_change_once_the_clinic_has_appointments(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Terkunci', 'code' => 'ANK']);
        $this->app->bind(ClinicRequest::class, fn () => new class extends ClinicRequest
        {
            protected function clinicHasAppointments(Clinic $clinic): bool
            {
                return true;
            }
        });
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Terkunci', 'code' => 'ZZZ']))
            ->assertSessionHasErrors(['code' => 'Kode poliklinik tidak dapat diubah karena sudah dipakai pada nomor antrean.']);

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Terkunci', 'code' => 'ANK']))
            ->assertSessionHasNoErrors();

        $this->assertSame('ANK', $clinic->fresh()->code);
    }

    public function test_cover_upload_on_create_stores_media_for_the_clinic_med01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'cover' => ImageFixtures::jpeg(1200, 800, 'cover.jpg'),
                'cover_alt' => 'Foto sampul poliklinik',
            ]))
            ->assertRedirect(route('admin.clinics.index'));

        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $media = Media::query()->where('collection', 'cover')->sole();

        $this->assertSame(Clinic::class, $media->model_type);
        $this->assertSame($clinic->id, $media->model_id);
        $this->assertSame('Foto sampul poliklinik', $media->alt_text);
        $this->assertSame('public', $media->disk);
        $this->assertStringStartsWith('cover/'.now()->format('Y/m'), $media->directory);
        $this->assertNotNull($media->thumb_path);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_replacing_the_cover_removes_the_old_file_after_commit(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.clinics.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800, 'lama.jpg'),
            'cover_alt' => 'Sampul lama',
        ]));
        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $old = Media::query()->where('collection', 'cover')->sole();
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->put(route('admin.clinics.update', $clinic), $this->payload([
            'cover' => ImageFixtures::jpeg(1000, 700, 'baru.jpg'),
            'cover_alt' => 'Sampul baru',
        ]))->assertRedirect(route('admin.clinics.index'));

        $new = Media::query()->where('collection', 'cover')->sole();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Sampul baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_cover_keeps_the_current_one(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.clinics.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
        ]));
        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $media = Media::query()->where('collection', 'cover')->sole();

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Nama Baru']))
            ->assertSessionHasNoErrors();

        $this->assertSame($media->id, Media::query()->where('collection', 'cover')->sole()->id);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
    }

    public function test_gallery_images_are_added_in_input_order_with_their_own_alt_text(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600, 'g1.jpg'), 2 => ImageFixtures::jpeg(900, 600, 'g2.jpg')],
                'gallery_alt' => [0 => 'Ruang tunggu', 1 => '', 2 => 'Ruang periksa'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertSame(
            ['Ruang tunggu', 'Ruang periksa'],
            Media::query()->where('collection', 'gallery')->orderBy('sort_order')->orderBy('id')->pluck('alt_text')->all(),
        );
    }

    public function test_gallery_image_without_alt_text_is_rejected_and_nothing_is_stored(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => ''],
            ]))
            ->assertSessionHasErrors(['gallery_alt.0' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame(0, Clinic::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_gallery_is_limited_to_20_images_including_existing_ones(): void
    {
        $clinic = $this->createClinic(['code' => 'GAL']);

        foreach (range(1, 19) as $position) {
            $this->createMediaRow([
                'model_type' => Clinic::class,
                'model_id' => $clinic->id,
                'collection' => 'gallery',
                'sort_order' => $position,
            ]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.clinics.update', $clinic), $this->payload([
                'name' => $clinic->name,
                'code' => 'GAL',
                'gallery' => [0 => ImageFixtures::jpeg(900, 600), 1 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => 'Satu', 1 => 'Dua'],
            ]))
            ->assertSessionHasErrors(['gallery' => 'Galeri maksimal 20 gambar.']);

        $this->assertSame(19, Media::query()->where('collection', 'gallery')->count());
    }

    public function test_edit_form_shows_values_cover_and_gallery_management_forms(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK']);
        $first = $this->createMediaRow(['model_type' => Clinic::class, 'model_id' => $clinic->id, 'collection' => 'gallery', 'alt_text' => 'Foto pertama', 'sort_order' => 1]);
        $second = $this->createMediaRow(['model_type' => Clinic::class, 'model_id' => $clinic->id, 'collection' => 'gallery', 'alt_text' => 'Foto kedua', 'sort_order' => 2]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.clinics.edit', $clinic))
            ->assertOk()
            ->assertSee('Ubah Poliklinik')
            ->assertSee('value="Poliklinik Anak"', false)
            ->assertSee('value="ANK"', false)
            ->assertSee('action="'.route('admin.clinics.update', $clinic).'"', false)
            ->assertSee('Foto pertama')
            ->assertSee('Foto kedua')
            ->assertSee('action="'.route('admin.media.update', $first).'"', false)
            ->assertSee('action="'.route('admin.media.reorder').'"', false)
            ->assertSee('data-confirm-action="'.route('admin.media.destroy', $second).'"', false);
    }

    public function test_deleting_an_unreferenced_clinic_removes_its_media_files_crd13(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.clinics.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
            'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
            'gallery_alt' => [0 => 'Galeri'],
        ]));
        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $paths = Media::query()->get()->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])->all();
        $this->assertCount(4, $paths);

        $this->delete(route('admin.clinics.destroy', $clinic))
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('success', 'Poliklinik berhasil dihapus.');

        $this->assertDatabaseMissing('clinics', ['id' => $clinic->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_clinic_referenced_by_a_doctor_is_not_deleted_even_for_super_admin(string $role): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Dirujuk']);
        $this->createDoctor([], $clinic);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.clinics.destroy', $clinic))
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('error', 'Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja.');

        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
        $this->assertDatabaseCount('doctors', 1);
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/poliklinik/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_clinic_routes(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Aman']);

        $requests = [
            ['GET', route('admin.clinics.create'), []],
            ['POST', route('admin.clinics.store'), $this->payload(['name' => 'Tidak Boleh', 'code' => 'TBH'])],
            ['GET', route('admin.clinics.edit', $clinic), []],
            ['PUT', route('admin.clinics.update', $clinic), $this->payload(['name' => 'Diubah Paksa', 'code' => 'DPK'])],
            ['DELETE', route('admin.clinics.destroy', $clinic), []],
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

        $this->assertSame('Poliklinik Aman', $clinic->fresh()->name);
        $this->assertDatabaseMissing('clinics', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
    }
}
