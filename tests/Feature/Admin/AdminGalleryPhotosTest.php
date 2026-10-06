<?php

namespace Tests\Feature\Admin;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\User;
use App\Rules\ImageRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Foto album (T9, F4A-R23): unggah lewat `admin.galleries.photos.store` (5 slot, V-34), batas 10 berkas per kirim
 * dan 100 foto per album, unggahan berbahaya (SEK-09/SEK-10), serta alt/urutan/hapus lewat `admin.media.*`
 * oleh content-admin (pemetaan MediaPolicy T3).
 */
class AdminGalleryPhotosTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->gallery = $this->createGallery(['title' => 'Album Unggah']);
    }

    private function uploadUrl(): string
    {
        return route('admin.galleries.photos.store', $this->gallery);
    }

    private function editUrl(): string
    {
        return route('admin.galleries.edit', $this->gallery);
    }

    /**
     * Isian $count slot berurutan (indeks 0..): foto 700x500 beserta teks alternatif.
     *
     * @return array<string, array<int, mixed>>
     */
    private function photoPayload(int $count): array
    {
        $photos = [];
        $alts = [];

        for ($index = 0; $index < $count; $index++) {
            $photos[$index] = ImageFixtures::jpeg(700, 500, "foto-{$index}.jpg");
            $alts[$index] = 'Foto album '.($index + 1);
        }

        return ['photos' => $photos, 'alt_texts' => $alts];
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        return Storage::disk('public')->allFiles();
    }

    public function test_two_photos_are_stored_in_slot_order_with_their_own_alt_and_caption_crd09(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->from($this->editUrl())
            ->post($this->uploadUrl(), [
                'photos' => [0 => ImageFixtures::jpeg(900, 600, 'g1.jpg'), 2 => ImageFixtures::jpeg(900, 600, 'g2.jpg')],
                'alt_texts' => [0 => 'Ruang tunggu', 1 => '', 2 => 'Ruang periksa'],
                'captions' => [0 => 'Lantai satu', 2 => ''],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->editUrl())
            ->assertSessionHas('success', '2 foto berhasil ditambahkan ke album.');

        $photos = Media::query()->where('collection', 'photos')->orderBy('sort_order')->orderBy('id')->get();

        $this->assertCount(2, $photos);
        $this->assertSame(['Ruang tunggu', 'Ruang periksa'], $photos->pluck('alt_text')->all());
        $this->assertSame(['Lantai satu', null], $photos->pluck('caption')->all());
        $this->assertSame([1, 2], $photos->pluck('sort_order')->all());

        foreach ($photos as $photo) {
            $this->assertSame(Gallery::class, $photo->model_type);
            $this->assertSame($this->gallery->id, $photo->model_id);
            $this->assertSame($admin->id, $photo->uploaded_by);
            $this->assertStringStartsWith('photos/'.now()->format('Y/m'), $photo->directory);
            Storage::disk('public')->assertExists($photo->directory.'/'.$photo->file_name);
            Storage::disk('public')->assertExists($photo->thumb_path);
        }

        $this->assertSame(2, DB::table('audit_logs')->where('action', 'media.uploaded')->count());
    }

    public function test_new_photos_continue_after_the_existing_sort_order(): void
    {
        $this->addGalleryPhoto($this->gallery, ['sort_order' => 5, 'alt_text' => 'Lama']);

        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), $this->photoPayload(1))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Lama', 'Foto album 1'],
            Media::query()->where('collection', 'photos')->orderBy('sort_order')->pluck('alt_text')->all(),
        );
        $this->assertSame(6, (int) Media::query()->where('alt_text', 'Foto album 1')->value('sort_order'));
    }

    public function test_an_album_cover_is_its_first_photo_by_sort_order(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), $this->photoPayload(2))
            ->assertSessionHasNoErrors();

        $gallery = Gallery::query()->with('media')->findOrFail($this->gallery->id);

        $this->assertSame('Foto album 1', $gallery->coverMedia()?->alt_text);
    }

    public function test_a_photo_without_alt_text_is_rejected_and_nothing_is_stored_vl06(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), [
                'photos' => [0 => ImageFixtures::jpeg(700, 500)],
                'alt_texts' => [0 => ''],
            ])
            ->assertSessionHasErrors(['alt_texts.0' => 'Teks alternatif setiap foto wajib diisi.']);

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_upload_without_any_file_is_rejected(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), ['alt_texts' => [0 => 'Tanpa berkas']])
            ->assertSessionHasErrors(['photos' => 'Pilih minimal satu foto.']);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_eleven_files_in_one_upload_are_rejected_but_ten_are_accepted(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post($this->uploadUrl(), $this->photoPayload(11))
            ->assertSessionHasErrors(['photos' => 'Maksimal 10 foto per unggahan.']);

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], $this->storedFiles());

        $this->post($this->uploadUrl(), $this->photoPayload(10))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '10 foto berhasil ditambahkan ke album.');

        $this->assertSame(10, Media::query()->where('collection', 'photos')->count());
    }

    public function test_an_album_that_holds_100_photos_accepts_no_more_and_hides_the_upload_form(): void
    {
        for ($position = 1; $position <= 100; $position++) {
            $this->addGalleryPhoto($this->gallery, ['sort_order' => $position]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), $this->photoPayload(1))
            ->assertSessionHasErrors(['photos' => 'Album sudah mencapai batas 100 foto.']);

        $this->assertSame(100, Media::query()->where('collection', 'photos')->count());
        $this->assertSame([], $this->storedFiles());

        $this->get($this->editUrl())
            ->assertOk()
            ->assertSee('Foto album (100 dari 100)')
            ->assertSee('Album sudah mencapai batas 100 foto.')
            ->assertDontSee('name="photos[0]"', false);
    }

    public function test_the_limit_counts_existing_and_new_photos_together(): void
    {
        for ($position = 1; $position <= 99; $position++) {
            $this->addGalleryPhoto($this->gallery, ['sort_order' => $position]);
        }

        $this->actingAs($this->createStaff('content-admin'));

        $this->post($this->uploadUrl(), $this->photoPayload(2))
            ->assertSessionHasErrors(['photos' => 'Album sudah mencapai batas 100 foto.']);

        $this->assertSame(99, Media::query()->where('collection', 'photos')->count());

        $this->post($this->uploadUrl(), $this->photoPayload(1))->assertSessionHasNoErrors();

        $this->assertSame(100, Media::query()->where('collection', 'photos')->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dangerousFiles(): array
    {
        return [
            'SVG berskrip' => ['svgWithScript'],
            'PHP berkepala JPEG (shell.php.jpg)' => ['phpWithJpegHeader'],
            'teks bernama jpg' => ['textNamedJpg'],
        ];
    }

    #[DataProvider('dangerousFiles')]
    public function test_dangerous_files_are_rejected_without_media_rows_or_files_sek09_sek10(string $factory): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), [
                'photos' => [0 => call_user_func([ImageFixtures::class, $factory])],
                'alt_texts' => [0 => 'Berkas berbahaya'],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_svg_is_rejected_with_the_not_an_image_message(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), [
                'photos' => [0 => ImageFixtures::svgWithScript()],
                'alt_texts' => [0 => 'SVG'],
            ])
            ->assertSessionHasErrors(['photos.0' => ImageRules::NOT_IMAGE]);
    }

    public function test_a_corrupt_png_is_a_validation_error_not_a_server_error(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), [
                'photos' => [0 => ImageFixtures::corruptPng(700, 500)],
                'alt_texts' => [0 => 'Rusak'],
            ])
            ->assertSessionHasErrors(['file' => ImageRules::INVALID_IMAGE]);

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_real_png_with_a_jpg_name_is_accepted_and_stored_as_png(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), [
                'photos' => [0 => ImageFixtures::pngNamedJpg(800, 600)],
                'alt_texts' => [0 => 'PNG sungguhan'],
            ])
            ->assertSessionHasNoErrors();

        $photo = Media::query()->where('collection', 'photos')->sole();

        $this->assertSame('png', $photo->extension);
        $this->assertSame('image/png', $photo->mime_type);
        $this->assertStringEndsWith('.png', $photo->file_name);
    }

    public function test_size_and_dimension_limits_use_the_photos_collection_messages_med05(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post($this->uploadUrl(), [
            'photos' => [0 => ImageFixtures::jpeg(500, 300)],
            'alt_texts' => [0 => 'Terlalu kecil'],
        ])->assertSessionHasErrors(['photos.0' => 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.']);

        $this->post($this->uploadUrl(), [
            'photos' => [0 => ImageFixtures::oversizedJpeg(2100, 700, 500)],
            'alt_texts' => [0 => 'Terlalu besar'],
        ])->assertSessionHasErrors(['photos.0' => 'Ukuran gambar maksimal 2 MB.']);

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_alt_and_caption_length_limits(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), [
                'photos' => [0 => ImageFixtures::jpeg(700, 500)],
                'alt_texts' => [0 => str_repeat('a', 256)],
                'captions' => [0 => str_repeat('b', 256)],
            ])
            ->assertSessionHasErrors([
                'alt_texts.0' => 'Teks alternatif maksimal 255 karakter.',
                'captions.0' => 'Keterangan foto maksimal 255 karakter.',
            ]);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_content_admin_updates_alt_and_caption_through_the_media_endpoint(): void
    {
        $photo = $this->addGalleryPhoto($this->gallery, ['sort_order' => 1, 'alt_text' => 'Alt lama']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from($this->editUrl())
            ->patch(route('admin.media.update', $photo), ['alt_text' => 'Alt baru', 'caption' => 'Keterangan baru'])
            ->assertRedirect($this->editUrl())
            ->assertSessionHas('success', 'Gambar berhasil diperbarui.');

        $photo->refresh();
        $this->assertSame('Alt baru', $photo->alt_text);
        $this->assertSame('Keterangan baru', $photo->caption);

        $this->patch(route('admin.media.update', $photo), ['alt_text' => ''])
            ->assertSessionHasErrors(['alt_text' => 'Teks alternatif gambar wajib diisi.']);
    }

    public function test_content_admin_reorders_photos_and_the_cover_follows_the_new_first_photo(): void
    {
        $a = $this->addGalleryPhoto($this->gallery, ['sort_order' => 1, 'alt_text' => 'A']);
        $b = $this->addGalleryPhoto($this->gallery, ['sort_order' => 2, 'alt_text' => 'B']);
        $c = $this->addGalleryPhoto($this->gallery, ['sort_order' => 3, 'alt_text' => 'C']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from($this->editUrl())
            ->post(route('admin.media.reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect($this->editUrl())
            ->assertSessionHas('success', 'Urutan gambar berhasil disimpan.');

        $this->assertSame(
            [$c->id, $a->id, $b->id],
            Media::query()->where('collection', 'photos')->orderBy('sort_order')->pluck('id')->all(),
        );

        $this->assertSame($c->id, Gallery::query()->with('media')->findOrFail($this->gallery->id)->coverMedia()?->id);
    }

    public function test_content_admin_deletes_one_photo_and_its_files_but_keeps_the_others(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post($this->uploadUrl(), $this->photoPayload(2))
            ->assertSessionHasNoErrors();

        [$first, $second] = Media::query()->where('collection', 'photos')->orderBy('sort_order')->get()->all();
        $firstPath = $first->directory.'/'.$first->file_name;
        $secondPath = $second->directory.'/'.$second->file_name;

        $this->from($this->editUrl())
            ->delete(route('admin.media.destroy', $first))
            ->assertRedirect($this->editUrl())
            ->assertSessionHas('success', 'Gambar berhasil dihapus.');

        $this->assertDatabaseMissing('media', ['id' => $first->id]);
        $this->assertDatabaseHas('media', ['id' => $second->id]);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertMissing($first->thumb_path);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertDatabaseHas('galleries', ['id' => $this->gallery->id]);
    }

    public function test_registration_officer_and_patient_cannot_change_gallery_photos_and_a_guest_is_redirected(): void
    {
        $photo = $this->addGalleryPhoto($this->gallery, ['sort_order' => 1, 'alt_text' => 'Tetap']);

        $requests = [
            ['POST', $this->uploadUrl(), ['alt_texts' => [0 => 'x']]],
            ['PATCH', route('admin.media.update', $photo), ['alt_text' => 'Diubah paksa']],
            ['POST', route('admin.media.reorder'), ['ids' => [$photo->id]]],
            ['DELETE', route('admin.media.destroy', $photo), []],
        ];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user);

            foreach ($requests as [$method, $url, $data]) {
                $this->call($method, $url, $data)->assertForbidden();
            }
        }

        $this->app['auth']->forgetGuards();

        // Upload album = halaman web (alihkan ke login); endpoint media fase 2 = JSON (401), lihat AdminMediaEndpointsTest.
        foreach ($requests as [$method, $url, $data]) {
            $response = $this->call($method, $url, $data);

            $url === $this->uploadUrl() ? $response->assertRedirect(route('admin.login')) : $response->assertUnauthorized();
        }

        $this->assertSame('Tetap', $photo->fresh()->alt_text);
        $this->assertSame(1, Media::query()->where('collection', 'photos')->count());
    }
}
