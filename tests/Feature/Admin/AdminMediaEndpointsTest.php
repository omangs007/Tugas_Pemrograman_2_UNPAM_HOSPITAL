<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminMediaEndpointsTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->media = app(MediaService::class);
    }

    // ------------------------------------------------------------------ pembantu

    /**
     * @param  list<string>  $permissions
     */
    private function staffWith(array $permissions): User
    {
        $this->seedRoles();

        $user = User::factory()->staff()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function doctorPhoto(): Media
    {
        return $this->media->store(ImageFixtures::jpeg(600, 600), 'photo', $this->createDoctor(), ['alt_text' => 'Alt lama']);
    }

    private function clinicCover(): Media
    {
        return $this->media->store(ImageFixtures::jpeg(1000, 600), 'cover', $this->createClinic(), ['alt_text' => 'Alt lama']);
    }

    /**
     * @return array{0: Clinic, 1: list<Media>}
     */
    private function clinicGallery(int $count = 3): array
    {
        $clinic = $this->createClinic();
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $items[] = $this->media->store(ImageFixtures::jpeg(800, 600), 'gallery', $clinic, ['alt_text' => "Galeri {$i}"]);
        }

        return [$clinic, $items];
    }

    private function logo(): Media
    {
        return $this->media->store(ImageFixtures::jpeg(300, 100), 'logo', null, ['alt_text' => 'Alt lama'], User::factory()->staff()->create());
    }

    private function editorImage(?User $uploader = null): Media
    {
        return $this->media->store(ImageFixtures::jpeg(400, 300), 'editor', null, ['alt_text' => 'Alt lama'], $uploader ?? User::factory()->staff()->create());
    }

    private function mediaFor(string $target): Media
    {
        return match ($target) {
            'doctor' => $this->doctorPhoto(),
            'clinic' => $this->clinicCover(),
            'logo' => $this->logo(),
            'editor' => $this->editorImage(),
        };
    }

    private function useAsSiteLogo(Media $logo): void
    {
        $this->seed(SettingsSeeder::class);

        app(SettingsService::class)->set('site.logo_media_id', $logo->id);
        app(SettingsService::class)->forget();
    }

    // ------------------------------------------------------------------ route

    public function test_base_controller_provides_authorize_for_all_admin_controllers(): void
    {
        // K1: Task 6 adalah satu-satunya pemilik trait ini; Task 9-12 hanya memakainya.
        $this->assertContains(
            AuthorizesRequests::class,
            class_uses_recursive(Controller::class),
        );
        $this->assertTrue(method_exists(MediaController::class, 'authorize'));
    }

    public function test_media_routes_exist_with_the_literal_permission_strings(): void
    {
        $update = 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage|settings.manage';
        $expected = [
            'admin.media.editor-upload' => ['POST', 'admin/media/editor', 'permission:content.manage|clinics.manage|facilities.manage'],
            'admin.media.reorder' => ['POST', 'admin/media/urutkan', 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage'],
            'admin.media.update' => ['PATCH', 'admin/media/{media}', $update],
            'admin.media.destroy' => ['DELETE', 'admin/media/{media}', $update],
        ];

        foreach ($expected as $name => [$method, $uri, $middleware]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum terdaftar");
            $this->assertContains($method, $route->methods());
            $this->assertSame($uri, $route->uri());
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_static_media_segments_are_not_captured_by_the_media_parameter(): void
    {
        $staff = $this->createStaff('super-admin');

        $this->actingAs($staff)->patchJson('/admin/media/urutkan', ['alt_text' => 'x'])->assertStatus(405);
        $this->actingAs($staff)->patchJson('/admin/media/abc', ['alt_text' => 'x'])->assertNotFound();
        $this->actingAs($staff)->patchJson('/admin/media/999999', ['alt_text' => 'x'])->assertNotFound();
    }

    // ------------------------------------------------------------------ editor-upload

    public function test_editor_upload_returns_201_with_a_storage_path_alt_and_media_id(): void
    {
        $user = $this->createStaff('content-admin');

        $response = $this->actingAs($user)->post(route('admin.media.editor-upload'), [
            'file' => ImageFixtures::jpeg(800, 600, 'ruang-periksa.jpg'),
            'alt_text' => '  Ruang periksa poli anak  ',
        ], ['Accept' => 'application/json']);

        $media = Media::query()->sole();

        $response->assertCreated()->assertExactJson([
            'url' => '/storage/'.$media->directory.'/'.$media->file_name,
            'alt' => 'Ruang periksa poli anak',
            'media_id' => $media->id,
        ]);

        $this->assertNull($media->model_type);
        $this->assertNull($media->model_id);
        $this->assertSame('editor', $media->collection);
        $this->assertSame($user->id, (int) $media->uploaded_by);
        $this->assertSame('Ruang periksa poli anak', $media->alt_text);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
        $this->assertSame(1, AuditLog::query()->where('action', 'media.uploaded')->count());
    }

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function editorUploadPermissions(): array
    {
        return [
            'content.manage' => [['content.manage'], 201],
            'clinics.manage' => [['clinics.manage'], 201],
            'facilities.manage' => [['facilities.manage'], 201],
            'doctors.manage saja' => [['doctors.manage'], 403],
            'settings.manage saja' => [['settings.manage'], 403],
            'tanpa izin' => [[], 403],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('editorUploadPermissions')]
    public function test_editor_upload_requires_one_of_the_content_permissions(array $permissions, int $status): void
    {
        $this->actingAs($this->staffWith($permissions))
            ->post(route('admin.media.editor-upload'), [
                'file' => ImageFixtures::jpeg(800, 600),
                'alt_text' => 'Gambar uji',
            ], ['Accept' => 'application/json'])
            ->assertStatus($status);

        $this->assertDatabaseCount('media', $status === 201 ? 1 : 0);
    }

    public function test_registration_officer_gets_a_json_403_and_guest_a_401(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->post(route('admin.media.editor-upload'), [
                'file' => ImageFixtures::jpeg(800, 600),
                'alt_text' => 'Gambar uji',
            ], ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.']);

        // actingAs bertahan di instans aplikasi yang sama; lepaskan agar permintaan berikut benar-benar tamu.
        $this->app['auth']->forgetGuards();

        $this->postJson(route('admin.media.editor-upload'), [])->assertUnauthorized();
    }

    public function test_editor_upload_validation_errors_are_422_with_indonesian_messages(): void
    {
        $user = $this->createStaff('content-admin');
        $send = fn (array $data) => $this->actingAs($user)->post(route('admin.media.editor-upload'), $data, ['Accept' => 'application/json']);

        $send(['file' => ImageFixtures::jpeg(800, 600)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alt_text' => 'Teks alternatif gambar wajib diisi.']);

        $send(['file' => ImageFixtures::jpeg(800, 600), 'alt_text' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alt_text' => 'Teks alternatif gambar maksimal 255 karakter.']);

        $send(['alt_text' => 'Tanpa berkas'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => 'File gambar wajib dipilih.']);

        $send(['file' => ImageFixtures::jpeg(100, 50), 'alt_text' => 'Terlalu kecil'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => 'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.']);

        $send(['file' => ImageFixtures::svgWithScript(), 'alt_text' => 'SVG ditolak'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => 'File harus berupa gambar.']);

        $send(['file' => ImageFixtures::phpWithJpegHeader(), 'alt_text' => 'PHP berkepala JPEG'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_editor_upload_of_a_corrupt_png_is_a_validation_error_not_a_500(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.media.editor-upload'), [
                'file' => ImageFixtures::corruptPng(600, 400),
                'alt_text' => 'Rusak',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('media', 0);
    }

    // ------------------------------------------------------------------ update (alt/keterangan)

    /**
     * @return array<string, array{list<string>, string, int}>
     */
    public static function updateMatrix(): array
    {
        return [
            'foto dokter: doctors.manage' => [['doctors.manage'], 'doctor', 200],
            'foto dokter: clinics.manage saja' => [['clinics.manage'], 'doctor', 403],
            'foto dokter: content.manage saja' => [['content.manage'], 'doctor', 403],
            'cover poliklinik: clinics.manage' => [['clinics.manage'], 'clinic', 200],
            'cover poliklinik: doctors.manage saja' => [['doctors.manage'], 'clinic', 403],
            'logo: settings.manage' => [['settings.manage'], 'logo', 200],
            'logo: Admin Konten (tanpa settings.manage)' => [['content.manage', 'clinics.manage', 'doctors.manage', 'facilities.manage'], 'logo', 403],
            'editor milik orang lain: content.manage' => [['content.manage'], 'editor', 200],
            'editor milik orang lain: facilities.manage' => [['facilities.manage'], 'editor', 200],
            'editor milik orang lain: settings.manage saja' => [['settings.manage'], 'editor', 403],
            'editor milik orang lain: doctors.manage saja' => [['doctors.manage'], 'editor', 403],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('updateMatrix')]
    public function test_update_alt_is_authorised_by_the_owner_of_the_media(array $permissions, string $target, int $status): void
    {
        $media = $this->mediaFor($target);

        $this->actingAs($this->staffWith($permissions))
            ->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt baru'])
            ->assertStatus($status);

        $this->assertSame($status === 200 ? 'Alt baru' : 'Alt lama', $media->fresh()->alt_text);
    }

    public function test_super_admin_may_update_media_of_any_owner(): void
    {
        $super = $this->createStaff('super-admin');

        foreach (['doctor', 'clinic', 'logo', 'editor'] as $target) {
            $media = $this->mediaFor($target);

            $this->actingAs($super)->patchJson(route('admin.media.update', $media), ['alt_text' => "Alt {$target}"])->assertOk();
            $this->assertSame("Alt {$target}", $media->fresh()->alt_text);
        }
    }

    public function test_the_uploader_may_update_and_delete_their_own_editor_image_without_group_permission(): void
    {
        $uploader = $this->staffWith(['settings.manage']);
        $media = $this->editorImage($uploader);

        $this->actingAs($uploader)->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt sendiri'])->assertOk();
        $this->assertSame('Alt sendiri', $media->fresh()->alt_text);

        $this->actingAs($uploader)->deleteJson(route('admin.media.destroy', $media))->assertOk();
        $this->assertModelMissing($media);
    }

    public function test_update_json_shape_and_caption_handling(): void
    {
        $media = $this->doctorPhoto();
        $media->update(['caption' => 'Keterangan lama']);
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => '  Dokter sedang praktik  '])
            ->assertOk()
            ->assertExactJson([
                'media_id' => $media->id,
                'alt' => 'Dokter sedang praktik',
                'caption' => 'Keterangan lama',
                'message' => 'Gambar berhasil diperbarui.',
            ]);

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt', 'caption' => ''])
            ->assertOk()
            ->assertJsonPath('caption', null);
        $this->assertNull($media->fresh()->caption);
    }

    public function test_update_validation_errors(): void
    {
        $media = $this->doctorPhoto();
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alt_text' => 'Teks alternatif gambar wajib diisi.']);

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt', 'caption' => str_repeat('k', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['caption' => 'Keterangan gambar maksimal 255 karakter.']);

        $this->assertSame('Alt lama', $media->fresh()->alt_text);
    }

    public function test_update_from_a_plain_form_redirects_back_with_flash_or_errors(): void
    {
        $media = $this->doctorPhoto();
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->from('/admin/dokter')
            ->patch(route('admin.media.update', $media), ['alt_text' => 'Alt dari form'])
            ->assertRedirect('/admin/dokter')
            ->assertSessionHas('success', 'Gambar berhasil diperbarui.');

        $this->actingAs($user)->from('/admin/dokter')
            ->patch(route('admin.media.update', $media), ['alt_text' => ''])
            ->assertRedirect('/admin/dokter')
            ->assertSessionHasErrors(['alt_text' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame('Alt dari form', $media->fresh()->alt_text);
    }

    // ------------------------------------------------------------------ Review Focus 5

    public function test_content_admin_cannot_change_the_alt_of_the_site_logo(): void
    {
        $logo = $this->logo();

        $this->actingAs($this->createStaff('content-admin'))
            ->patchJson(route('admin.media.update', $logo), ['alt_text' => 'Diubah Admin Konten'])
            ->assertForbidden();

        $this->assertSame('Alt lama', $logo->fresh()->alt_text);
    }

    public function test_registration_officer_cannot_delete_a_doctor_photo(): void
    {
        $photo = $this->doctorPhoto();

        $this->actingAs($this->createStaff('registration-officer'))
            ->deleteJson(route('admin.media.destroy', $photo))
            ->assertForbidden();

        $this->assertModelExists($photo);
        Storage::disk('public')->assertExists($photo->directory.'/'.$photo->file_name);
    }

    public function test_super_admin_cannot_delete_media_that_is_the_site_logo(): void
    {
        $logo = $this->logo();
        $this->useAsSiteLogo($logo);
        $super = $this->createStaff('super-admin');

        $this->actingAs($super)->deleteJson(route('admin.media.destroy', $logo))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gambar tidak dapat dihapus karena sedang dipakai.')
            ->assertJsonPath('errors.media.0', 'Gambar tidak dapat dihapus karena sedang dipakai.');

        $this->actingAs($super)->from('/admin/pengaturan')
            ->delete(route('admin.media.destroy', $logo))
            ->assertRedirect('/admin/pengaturan')
            ->assertSessionHas('error', 'Gambar tidak dapat dihapus karena sedang dipakai.');

        $this->assertModelExists($logo);
        Storage::disk('public')->assertExists($logo->directory.'/'.$logo->file_name);
    }

    // ------------------------------------------------------------------ destroy

    /**
     * @return array<string, array{list<string>, string, int}>
     */
    public static function destroyMatrix(): array
    {
        return [
            'foto dokter: doctors.manage' => [['doctors.manage'], 'doctor', 200],
            'foto dokter: facilities.manage saja' => [['facilities.manage'], 'doctor', 403],
            'cover poliklinik: clinics.manage' => [['clinics.manage'], 'clinic', 200],
            'cover poliklinik: doctors.manage saja' => [['doctors.manage'], 'clinic', 403],
            'logo yang tidak dipakai: settings.manage' => [['settings.manage'], 'logo', 200],
            'logo: Admin Konten (tanpa settings.manage)' => [['content.manage', 'clinics.manage', 'doctors.manage', 'facilities.manage'], 'logo', 403],
            'editor milik orang lain: content.manage' => [['content.manage'], 'editor', 200],
            'editor milik orang lain: settings.manage saja' => [['settings.manage'], 'editor', 403],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('destroyMatrix')]
    public function test_destroy_is_authorised_by_the_owner_and_removes_the_files(array $permissions, string $target, int $status): void
    {
        $media = $this->mediaFor($target);
        $paths = [$media->directory.'/'.$media->file_name, $media->thumb_path];

        $this->actingAs($this->staffWith($permissions))
            ->deleteJson(route('admin.media.destroy', $media))
            ->assertStatus($status);

        if ($status === 200) {
            $this->assertModelMissing($media);

            foreach ($paths as $path) {
                Storage::disk('public')->assertMissing($path);
            }
        } else {
            $this->assertModelExists($media);

            foreach ($paths as $path) {
                Storage::disk('public')->assertExists($path);
            }
        }
    }

    public function test_destroying_a_doctor_photo_keeps_the_doctor_and_writes_an_audit_row(): void
    {
        $doctor = $this->createDoctor();
        $photo = $this->media->store(ImageFixtures::jpeg(600, 600), 'photo', $doctor, ['alt_text' => 'Foto']);

        $this->actingAs($this->createStaff('content-admin'))
            ->deleteJson(route('admin.media.destroy', $photo))
            ->assertOk()
            ->assertExactJson(['message' => 'Gambar berhasil dihapus.']);

        $this->assertModelExists($doctor);
        $this->assertSame(1, AuditLog::query()->where('action', 'media.deleted')->count());
    }

    public function test_destroy_from_a_plain_form_redirects_back_with_a_flash(): void
    {
        $photo = $this->doctorPhoto();

        $this->actingAs($this->createStaff('content-admin'))->from('/admin/dokter')
            ->delete(route('admin.media.destroy', $photo))
            ->assertRedirect('/admin/dokter')
            ->assertSessionHas('success', 'Gambar berhasil dihapus.');

        $this->assertModelMissing($photo);
    }

    public function test_an_unused_logo_can_be_deleted_by_a_super_admin(): void
    {
        $logo = $this->logo();
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->createStaff('super-admin'))
            ->deleteJson(route('admin.media.destroy', $logo))
            ->assertOk();

        $this->assertModelMissing($logo);
    }

    // ------------------------------------------------------------------ reorder

    public function test_reorder_saves_the_new_positions(): void
    {
        [, $items] = $this->clinicGallery(3);
        [$first, $second, $third] = $items;

        $this->actingAs($this->createStaff('content-admin'))
            ->postJson(route('admin.media.reorder'), ['ids' => [$third->id, $first->id, $second->id]])
            ->assertOk()
            ->assertExactJson(['message' => 'Urutan gambar berhasil disimpan.']);

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $second->fresh()->sort_order);
    }

    public function test_reorder_from_a_plain_form_redirects_back_with_a_flash(): void
    {
        [, $items] = $this->clinicGallery(2);

        $this->actingAs($this->createStaff('content-admin'))->from('/admin/poliklinik/1/ubah')
            ->post(route('admin.media.reorder'), ['ids' => [$items[1]->id, $items[0]->id]])
            ->assertRedirect('/admin/poliklinik/1/ubah')
            ->assertSessionHas('success', 'Urutan gambar berhasil disimpan.');

        $this->assertSame(1, $items[1]->fresh()->sort_order);
    }

    public function test_reorder_validation_error_from_a_plain_form_redirects_back_with_errors(): void
    {
        $this->clinicGallery(2);

        $this->actingAs($this->createStaff('content-admin'))->from('/admin/poliklinik/1/ubah')
            ->post(route('admin.media.reorder'), ['ids' => []])
            ->assertRedirect('/admin/poliklinik/1/ubah')
            ->assertSessionHasErrors(['ids' => 'Daftar gambar wajib diisi.']);
    }

    public function test_reorder_rejects_media_of_different_owners_or_collections(): void
    {
        [, $galleryA] = $this->clinicGallery(2);
        [, $galleryB] = $this->clinicGallery(2);
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->postJson(route('admin.media.reorder'), ['ids' => [$galleryA[0]->id, $galleryB[0]->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Urutan gambar tidak valid.']);

        $cover = $this->media->store(ImageFixtures::jpeg(1000, 600), 'cover', $galleryA[0]->model()->first(), ['alt_text' => 'Cover']);

        $this->actingAs($user)->postJson(route('admin.media.reorder'), ['ids' => [$galleryA[0]->id, $cover->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Urutan gambar tidak valid.']);

        $this->assertSame(1, $galleryA[0]->fresh()->sort_order);
    }

    public function test_reorder_rejects_media_without_an_owner(): void
    {
        $one = $this->editorImage();
        $two = $this->editorImage();

        $this->actingAs($this->createStaff('super-admin'))
            ->postJson(route('admin.media.reorder'), ['ids' => [$one->id, $two->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Urutan gambar tidak valid.']);
    }

    public function test_reorder_validation_messages(): void
    {
        [, $items] = $this->clinicGallery(2);
        $user = $this->createStaff('content-admin');
        $send = fn (array $data) => $this->actingAs($user)->postJson(route('admin.media.reorder'), $data);

        $send([])->assertStatus(422)->assertJsonValidationErrors(['ids' => 'Daftar gambar wajib diisi.']);
        $send(['ids' => []])->assertStatus(422)->assertJsonValidationErrors(['ids' => 'Daftar gambar wajib diisi.']);
        $send(['ids' => range(1, 101)])->assertStatus(422)->assertJsonValidationErrors(['ids' => 'Maksimal 100 gambar dapat diurutkan sekaligus.']);
        $send(['ids' => [999999]])->assertStatus(422)->assertJsonValidationErrors(['ids.0' => 'Gambar tidak ditemukan.']);
        $send(['ids' => [$items[0]->id, $items[0]->id]])->assertStatus(422)->assertJsonValidationErrors(['ids.0' => 'Urutan gambar tidak valid.']);
        $send(['ids' => ['abc']])->assertStatus(422)->assertJsonValidationErrors(['ids.0' => 'Urutan gambar tidak valid.']);
    }

    public function test_reorder_is_authorised_for_every_media_in_the_list(): void
    {
        [, $gallery] = $this->clinicGallery(2);
        $photo = $this->doctorPhoto();

        // clinics.manage cukup untuk galeri poliklinik, tetapi tidak untuk foto dokter yang ikut dikirim.
        $clinicsOnly = $this->staffWith(['clinics.manage']);

        $this->actingAs($clinicsOnly)->postJson(route('admin.media.reorder'), ['ids' => [$gallery[1]->id, $gallery[0]->id]])->assertOk();
        $this->actingAs($clinicsOnly)->postJson(route('admin.media.reorder'), ['ids' => [$gallery[0]->id, $photo->id]])->assertForbidden();

        // doctors.manage meloloskan middleware tetapi bukan pemilik galeri poliklinik.
        $this->actingAs($this->staffWith(['doctors.manage']))
            ->postJson(route('admin.media.reorder'), ['ids' => [$gallery[0]->id, $gallery[1]->id]])
            ->assertForbidden();

        $this->actingAs($this->createStaff('registration-officer'))
            ->postJson(route('admin.media.reorder'), ['ids' => [$gallery[0]->id]])
            ->assertForbidden();
    }
}
