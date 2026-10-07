<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\RoomCategory;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-28 (dengan F4B-R3, F4B-R4, F4B-R5, F4B-R18, F4B-R19, F4B-R21): pesan Indonesia per field untuk form kategori kamar.
 * `beds_available` hanya divalidasi saat buat; saat ubah ketersediaan hanya lewat aksi terpisah (F4B-R18).
 */
class RoomCategoryRequestTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * Aturan yang berlaku untuk tambah dan ubah.
     *
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        $thirtyOne = implode("\n", array_map(fn (int $number): string => 'Butir '.$number, range(1, 31)));

        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama kategori kamar wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama kategori kamar harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama kategori kamar minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama kategori kamar maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'VIP Contoh'], 'name', 'Nama kategori kamar sudah digunakan.'],
            'nama ganda beda huruf besar kecil' => [['name' => 'vip contoh'], 'name', 'Nama kategori kamar sudah digunakan.'],
            'deskripsi singkat kosong' => [['short_description' => ''], 'short_description', 'Deskripsi singkat wajib diisi.'],
            'deskripsi singkat bukan teks' => [['short_description' => ['a']], 'short_description', 'Deskripsi singkat harus berupa teks.'],
            'deskripsi singkat terlalu panjang' => [['short_description' => str_repeat('a', 256)], 'short_description', 'Deskripsi singkat maksimal 255 karakter.'],
            'deskripsi bukan teks' => [['description' => ['a']], 'description', 'Deskripsi harus berupa teks.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 30001)], 'description', 'Deskripsi maksimal 30.000 karakter.'],
            'fasilitas lebih dari 30 butir' => [['amenities' => $thirtyOne], 'amenities', 'Fasilitas kamar maksimal 30 butir.'],
            'butir fasilitas terlalu pendek' => [['amenities' => "AC\nA"], 'amenities.1', 'Setiap butir fasilitas harus 2 sampai 80 karakter.'],
            'butir fasilitas terlalu panjang' => [['amenities' => str_repeat('a', 81)], 'amenities.0', 'Setiap butir fasilitas harus 2 sampai 80 karakter.'],
            'butir fasilitas bukan teks' => [['amenities' => [['x']]], 'amenities.0', 'Setiap butir fasilitas harus 2 sampai 80 karakter.'],
            'butir fasilitas ganda' => [['amenities' => "AC\nTV\nAC"], 'amenities.2', 'Butir fasilitas tidak boleh sama.'],
            'kapasitas kosong' => [['bed_capacity' => ''], 'bed_capacity', 'Kapasitas tempat tidur wajib diisi.'],
            'kapasitas bukan angka bulat' => [['bed_capacity' => 'abc'], 'bed_capacity', 'Kapasitas tempat tidur harus berupa angka bulat.'],
            'kapasitas desimal' => [['bed_capacity' => '5.5'], 'bed_capacity', 'Kapasitas tempat tidur harus berupa angka bulat.'],
            'kapasitas nol' => [['bed_capacity' => '0'], 'bed_capacity', 'Kapasitas tempat tidur harus antara 1 dan 500.'],
            'kapasitas terlalu besar' => [['bed_capacity' => '501'], 'bed_capacity', 'Kapasitas tempat tidur harus antara 1 dan 500.'],
            'tarif bukan angka' => [['sample_rate' => 'abc'], 'sample_rate', 'Tarif contoh harus berupa angka.'],
            'tarif negatif' => [['sample_rate' => '-1'], 'sample_rate', 'Tarif contoh tidak boleh negatif.'],
            'tarif terlalu besar' => [['sample_rate' => '10000000000'], 'sample_rate', 'Tarif contoh terlalu besar.'],
            'tarif tiga desimal' => [['sample_rate' => '12.345'], 'sample_rate', 'Tarif contoh maksimal 2 angka desimal.'],
            'satuan tarif kosong' => [['rate_unit' => ''], 'rate_unit', 'Satuan tarif wajib diisi.'],
            'satuan tarif bukan teks' => [['rate_unit' => ['a']], 'rate_unit', 'Satuan tarif harus berupa teks.'],
            'satuan tarif terlalu panjang' => [['rate_unit' => str_repeat('a', 31)], 'rate_unit', 'Satuan tarif maksimal 30 karakter.'],
            'catatan tarif bukan teks' => [['rate_note' => ['a']], 'rate_note', 'Catatan tarif harus berupa teks.'],
            'catatan tarif terlalu panjang' => [['rate_note' => str_repeat('a', 256)], 'rate_note', 'Catatan tarif maksimal 255 karakter.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'status aktif tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
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
     * Aturan `beds_available` yang hanya berlaku saat buat (V-28).
     *
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidCreateOnlyInputs(): array
    {
        return [
            'tersedia kosong' => [['beds_available' => ''], 'beds_available', 'Jumlah tempat tidur tersedia wajib diisi.'],
            'tersedia bukan angka bulat' => [['beds_available' => 'abc'], 'beds_available', 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
            'tersedia negatif' => [['beds_available' => '-1'], 'beds_available', 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.'],
            'tersedia melebihi kapasitas' => [['bed_capacity' => '10', 'beds_available' => '11'], 'beds_available', 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Kategori Baru Contoh',
            'short_description' => 'Ringkasan kategori.',
            'bed_capacity' => '10',
            'beds_available' => '4',
            'rate_unit' => 'per malam',
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
        $this->assertStoreRejects($override, $field, $message);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidCreateOnlyInputs')]
    public function test_store_rejects_invalid_initial_availability(array $override, string $field, string $message): void
    {
        $this->assertStoreRejects($override, $field, $message);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function assertStoreRejects(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createRoomCategory(['name' => 'VIP Contoh']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.create'))
            ->post(route('admin.room-categories.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.room-categories.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, RoomCategory::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_update_applies_the_same_rules_and_keeps_the_old_row(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createRoomCategory(['name' => 'VIP Contoh']);
        $target = $this->createRoomCategory(['name' => 'Kamar Sasaran', 'bed_capacity' => 12, 'beds_available' => 4]);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.edit', $target))
            ->put(route('admin.room-categories.update', $target), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.room-categories.edit', $target))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame('Kamar Sasaran', $target->fresh()->name);
        $this->assertSame(12, $target->fresh()->bed_capacity);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_update_with_an_array_cover_alt_is_a_validation_error_not_a_server_error(): void
    {
        Storage::fake('public');
        $target = $this->createRoomCategory(['name' => 'Kamar Sasaran']);
        app(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $target, ['alt_text' => 'Alt lama']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.edit', $target))
            ->put(route('admin.room-categories.update', $target), array_merge($this->valid(), ['cover_alt' => ['x']]))
            ->assertRedirect(route('admin.room-categories.edit', $target))
            ->assertSessionHasErrors(['cover_alt' => 'Teks alternatif gambar harus berupa teks.']);

        $this->assertSame('Kamar Sasaran', $target->fresh()->name);
        $this->assertSame('Alt lama', Media::query()->where('collection', 'cover')->sole()->alt_text);
    }

    public function test_beds_available_is_ignored_on_update_even_when_it_is_invalid(): void
    {
        $target = $this->createRoomCategory(['name' => 'Kamar Sasaran', 'bed_capacity' => 12, 'beds_available' => 4]);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $target), array_merge($this->valid(), ['bed_capacity' => '12', 'beds_available' => '999']))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $target->fresh()->beds_available);
    }

    public function test_capacity_error_for_a_non_integer_does_not_also_report_the_below_availability_error(): void
    {
        $target = $this->createRoomCategory(['name' => 'Kamar Sasaran', 'bed_capacity' => 12, 'beds_available' => 4]);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.edit', $target))
            ->put(route('admin.room-categories.update', $target), array_merge($this->valid(), ['bed_capacity' => 'abc']))
            ->assertSessionHasErrors(['bed_capacity' => 'Kapasitas tempat tidur harus berupa angka bulat.']);

        $errors = session('errors')->get('bed_capacity');

        $this->assertSame(['Kapasitas tempat tidur harus berupa angka bulat.'], $errors);
    }

    public function test_the_slug_field_is_not_accepted_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), array_merge($this->valid(), ['slug' => 'slug-dari-form']))
            ->assertSessionHasNoErrors();

        $this->assertSame('kategori-baru-contoh', RoomCategory::query()->sole()->slug);
    }

    public function test_invalid_form_keeps_old_input_and_renders_the_amenities_back_one_per_line(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.create'))
            ->post(route('admin.room-categories.store'), array_merge($this->valid(), [
                'name' => '',
                'amenities' => "AC\nTelevisi\nA",
                'rate_note' => 'Catatan Lama',
            ]))
            ->assertRedirect(route('admin.room-categories.create'));

        $this->get(route('admin.room-categories.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama kategori kamar wajib diisi.')
            ->assertSee('Setiap butir fasilitas harus 2 sampai 80 karakter.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('>AC'."\n".'Televisi'."\n".'A</textarea>', false)
            ->assertSee('value="Catatan Lama"', false);
    }
}
