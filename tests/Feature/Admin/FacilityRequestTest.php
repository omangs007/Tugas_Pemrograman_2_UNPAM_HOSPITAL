<?php

namespace Tests\Feature\Admin;

use App\Models\Facility;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-27 (dengan F4B-R3, F4B-R4, F4B-R5, F4B-R21): pesan Indonesia per field untuk form fasilitas, tambah dan ubah.
 */
class FacilityRequestTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama fasilitas wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama fasilitas harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama fasilitas minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama fasilitas maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Laboratorium Contoh'], 'name', 'Nama fasilitas sudah digunakan.'],
            'nama ganda beda huruf besar kecil' => [['name' => 'LABORATORIUM CONTOH'], 'name', 'Nama fasilitas sudah digunakan.'],
            'kategori kosong' => [['category' => ''], 'category', 'Kategori fasilitas wajib dipilih.'],
            'kategori tidak dikenal' => [['category' => 'vip'], 'category', 'Kategori fasilitas tidak valid.'],
            'kategori berupa larik' => [['category' => ['medis']], 'category', 'Kategori fasilitas tidak valid.'],
            'deskripsi singkat kosong' => [['short_description' => ''], 'short_description', 'Deskripsi singkat wajib diisi.'],
            'deskripsi singkat bukan teks' => [['short_description' => ['a']], 'short_description', 'Deskripsi singkat harus berupa teks.'],
            'deskripsi singkat terlalu panjang' => [['short_description' => str_repeat('a', 256)], 'short_description', 'Deskripsi singkat maksimal 255 karakter.'],
            'deskripsi bukan teks' => [['description' => ['a']], 'description', 'Deskripsi harus berupa teks.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 30001)], 'description', 'Deskripsi maksimal 30.000 karakter.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'status aktif tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'urutan negatif' => [['sort_order' => '-1'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'meta title terlalu panjang' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description terlalu panjang' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt sampul wajib' => [['cover' => 'FILE', 'cover_alt' => ''], 'cover_alt', 'Teks alternatif gambar wajib diisi.'],
            'alt sampul terlalu panjang' => [['cover' => 'FILE', 'cover_alt' => str_repeat('a', 256)], 'cover_alt', 'Teks alternatif gambar maksimal 255 karakter.'],
            'sampul terlalu kecil' => [['cover' => 'SMALL', 'cover_alt' => 'x'], 'cover', 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'sampul terlalu besar' => [['cover' => 'BIG', 'cover_alt' => 'x'], 'cover', 'Ukuran gambar maksimal 2 MB.'],
            'galeri terlalu kecil' => [['gallery' => ['SMALL'], 'gallery_alt' => ['x']], 'gallery.0', 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'alt galeri wajib' => [['gallery' => ['GALLERY'], 'gallery_alt' => ['']], 'gallery_alt.0', 'Teks alternatif gambar wajib diisi.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Fasilitas Baru Contoh',
            'category' => 'medis',
            'short_description' => 'Ringkasan fasilitas.',
            'is_active' => '1',
        ];
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider dijalankan sebelum GD siap dipakai).
     */
    private function materialize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->materialize($item), $value);
        }

        return match ($value) {
            'FILE' => ImageFixtures::jpeg(1200, 800),
            'SMALL' => ImageFixtures::jpeg(400, 300),
            'GALLERY' => ImageFixtures::jpeg(900, 600),
            'BIG' => ImageFixtures::oversizedJpeg(2100, 1200, 800),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createFacility(['name' => 'Laboratorium Contoh']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.facilities.create'))
            ->post(route('admin.facilities.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.facilities.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, Facility::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_update_applies_the_same_rules_and_keeps_the_old_row(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createFacility(['name' => 'Laboratorium Contoh']);
        $target = $this->createFacility(['name' => 'Fasilitas Sasaran']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.facilities.edit', $target))
            ->put(route('admin.facilities.update', $target), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.facilities.edit', $target))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame('Fasilitas Sasaran', $target->fresh()->name);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_update_with_an_array_cover_alt_is_a_validation_error_not_a_server_error(): void
    {
        Storage::fake('public');
        $target = $this->createFacility(['name' => 'Fasilitas Sasaran']);
        app(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $target, ['alt_text' => 'Alt lama']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.facilities.edit', $target))
            ->put(route('admin.facilities.update', $target), array_merge($this->valid(), ['cover_alt' => ['x']]))
            ->assertRedirect(route('admin.facilities.edit', $target))
            ->assertSessionHasErrors(['cover_alt' => 'Teks alternatif gambar harus berupa teks.']);

        $this->assertSame('Fasilitas Sasaran', $target->fresh()->name);
        $this->assertSame('Alt lama', Media::query()->where('collection', 'cover')->sole()->alt_text);
    }

    public function test_the_slug_field_is_not_accepted_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), array_merge($this->valid(), ['slug' => 'slug-dari-form']))
            ->assertSessionHasNoErrors();

        $this->assertSame('fasilitas-baru-contoh', Facility::query()->sole()->slug);
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.facilities.create'))
            ->post(route('admin.facilities.store'), array_merge($this->valid(), ['name' => '', 'short_description' => 'Ringkasan Lama']))
            ->assertRedirect(route('admin.facilities.create'));

        $this->get(route('admin.facilities.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama fasilitas wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('name-error', false)
            ->assertSee('Ringkasan Lama');
    }
}
