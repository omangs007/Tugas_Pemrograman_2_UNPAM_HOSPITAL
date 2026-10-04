<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-22 (dengan F2-R13, F2-R15, F2-R23): pesan Indonesia per field.
 */
class AdminClinicValidationTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama poliklinik wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama poliklinik harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama poliklinik minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama poliklinik maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Poliklinik Anak'], 'name', 'Nama poliklinik sudah digunakan.'],
            'kode kosong' => [['code' => ''], 'code', 'Kode poliklinik wajib diisi.'],
            'kode satu huruf' => [['code' => 'A'], 'code', 'Kode poliklinik harus 2 sampai 5 huruf kapital.'],
            'kode enam huruf' => [['code' => 'ABCDEF'], 'code', 'Kode poliklinik harus 2 sampai 5 huruf kapital.'],
            'kode berisi angka' => [['code' => 'AB1'], 'code', 'Kode poliklinik harus 2 sampai 5 huruf kapital.'],
            'kode ganda' => [['code' => 'ANK'], 'code', 'Kode poliklinik sudah digunakan.'],
            'jenis kosong' => [['type' => ''], 'type', 'Jenis layanan wajib dipilih.'],
            'jenis tidak dikenal' => [['type' => 'klinik'], 'type', 'Jenis layanan tidak valid.'],
            'pendaftaran tidak valid' => [['is_bookable' => 'mungkin'], 'is_bookable', 'Status pendaftaran tidak valid.'],
            'penunjang tidak boleh didaftar' => [['type' => 'penunjang', 'is_bookable' => '1'], 'is_bookable', 'Layanan penunjang tidak dapat dibuka untuk pendaftaran kunjungan.'],
            'spesialisasi tidak ada' => [['specialty_id' => '999999'], 'specialty_id', 'Spesialisasi yang dipilih tidak tersedia.'],
            'spesialisasi bukan angka' => [['specialty_id' => 'abc'], 'specialty_id', 'Spesialisasi harus berupa angka bulat.'],
            'deskripsi singkat kosong' => [['short_description' => ''], 'short_description', 'Deskripsi singkat wajib diisi.'],
            'deskripsi singkat terlalu panjang' => [['short_description' => str_repeat('a', 256)], 'short_description', 'Deskripsi singkat maksimal 255 karakter.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 30001)], 'description', 'Deskripsi maksimal 30.000 karakter.'],
            'lokasi kosong' => [['location' => ''], 'location', 'Lokasi wajib diisi.'],
            'lokasi terlalu panjang' => [['location' => str_repeat('a', 101)], 'location', 'Lokasi maksimal 100 karakter.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'meta title terlalu panjang' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description terlalu panjang' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt sampul wajib' => [['cover' => 'FILE', 'cover_alt' => ''], 'cover_alt', 'Teks alternatif gambar wajib diisi.'],
            'alt sampul terlalu panjang' => [['cover' => 'FILE', 'cover_alt' => str_repeat('a', 256)], 'cover_alt', 'Teks alternatif gambar maksimal 255 karakter.'],
            'sampul terlalu kecil' => [['cover' => 'SMALL', 'cover_alt' => 'x'], 'cover', 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'sampul terlalu besar' => [['cover' => 'BIG', 'cover_alt' => 'x'], 'cover', 'Ukuran gambar maksimal 2 MB.'],
            'galeri terlalu kecil' => [['gallery' => ['SMALL'], 'gallery_alt' => ['x']], 'gallery.0', 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.clinics.create'))
            ->post(route('admin.clinics.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.clinics.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, Clinic::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_svg_and_non_image_uploads_are_rejected_without_storing_anything(): void
    {
        Storage::fake('public');
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.clinics.store'), array_merge($this->valid(), ['cover' => ImageFixtures::svgWithScript(), 'cover_alt' => 'x']))
            ->assertSessionHasErrors('cover');

        $this->post(route('admin.clinics.store'), array_merge($this->valid(), ['cover' => ImageFixtures::textNamedJpg(), 'cover_alt' => 'x']))
            ->assertSessionHasErrors('cover');

        $this->assertSame(0, Clinic::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.clinics.create'))
            ->post(route('admin.clinics.store'), array_merge($this->valid(), ['name' => '', 'location' => 'Gedung Lama']))
            ->assertRedirect(route('admin.clinics.create'));

        $this->get(route('admin.clinics.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama poliklinik wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('name-error', false)
            ->assertSee('value="Gedung Lama"', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Poliklinik Baru',
            'code' => 'BRU',
            'type' => 'poliklinik',
            'is_bookable' => '1',
            'short_description' => 'Ringkasan layanan.',
            'location' => 'Gedung A Lt. 1',
            'is_active' => '1',
        ];
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider dijalankan sebelum GD siap dipakai di setUp).
     */
    private function materialize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->materialize($item), $value);
        }

        return match ($value) {
            'FILE' => ImageFixtures::jpeg(1200, 800),
            'SMALL' => ImageFixtures::jpeg(400, 300),
            'BIG' => ImageFixtures::oversizedJpeg(2100, 1200, 800),
            default => $value,
        };
    }
}
