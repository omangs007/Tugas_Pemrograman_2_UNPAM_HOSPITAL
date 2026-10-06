<?php

namespace Tests\Feature\Admin;

use App\Models\InsurancePartner;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-36 (BR-128, BR-129): pesan Indonesia per field untuk form mitra asuransi, termasuk unggahan logo
 * (SEK-09, SEK-10, MED-05, VAL-06).
 */
class AdminInsurancePartnerValidationTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama mitra wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama mitra harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama mitra minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama mitra maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Asuransi Contoh Sehat'], 'name', 'Nama mitra sudah digunakan.'],
            'nama ganda beda huruf besar kecil' => [['name' => 'ASURANSI CONTOH SEHAT'], 'name', 'Nama mitra sudah digunakan.'],
            'jenis kosong' => [['type' => ''], 'type', 'Jenis mitra wajib dipilih.'],
            'jenis tidak dikenal' => [['type' => 'koperasi'], 'type', 'Jenis mitra tidak valid.'],
            'deskripsi bukan teks' => [['description' => ['a']], 'description', 'Deskripsi harus berupa teks.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 501)], 'description', 'Deskripsi maksimal 500 karakter.'],
            'deskripsi berisi html' => [['description' => 'Mitra <b>resmi</b>'], 'description', 'Deskripsi tidak boleh mengandung tag HTML.'],
            'situs http' => [['website_url' => 'http://contoh.test'], 'website_url', 'Alamat situs harus berupa URL https yang valid.'],
            'situs bukan url' => [['website_url' => 'contoh.test'], 'website_url', 'Alamat situs harus berupa URL https yang valid.'],
            'situs javascript' => [['website_url' => 'javascript:alert(1)'], 'website_url', 'Alamat situs harus berupa URL https yang valid.'],
            'situs tanpa host' => [['website_url' => 'https://'], 'website_url', 'Alamat situs harus berupa URL https yang valid.'],
            'situs terlalu panjang' => [['website_url' => 'https://contoh.test/'.str_repeat('a', 250)], 'website_url', 'Alamat situs maksimal 255 karakter.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'status aktif tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Mitra Baru Contoh',
            'type' => 'asuransi',
            'is_active' => '1',
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->createInsurancePartner(['name' => 'Asuransi Contoh Sehat']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.insurance-partners.create'))
            ->post(route('admin.insurance-partners.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.insurance-partners.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, InsurancePartner::query()->count());
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_update_applies_the_same_rules_and_keeps_the_old_row(array $override, string $field, string $message): void
    {
        $this->createInsurancePartner(['name' => 'Asuransi Contoh Sehat']);
        $target = $this->createInsurancePartner(['name' => 'Mitra Sasaran', 'type' => 'perusahaan']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.insurance-partners.edit', $target))
            ->put(route('admin.insurance-partners.update', $target), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.insurance-partners.edit', $target))
            ->assertSessionHasErrors([$field => $message]);

        $target->refresh();
        $this->assertSame('Mitra Sasaran', $target->name);
        $this->assertSame('perusahaan', $target->type);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidLogos(): array
    {
        return [
            'logo terlalu kecil' => [['logo' => 'SMALL', 'logo_alt' => 'Logo'], 'logo', 'Dimensi logo minimal 120 x 40 piksel dan maksimal 6000 x 6000 piksel.'],
            'logo terlalu besar' => [['logo' => 'BIG', 'logo_alt' => 'Logo'], 'logo', 'Ukuran gambar maksimal 2 MB.'],
            'alt logo wajib' => [['logo' => 'LOGO', 'logo_alt' => ''], 'logo_alt', 'Teks alternatif logo wajib diisi.'],
            'alt logo terlalu panjang' => [['logo' => 'LOGO', 'logo_alt' => 'ZZZ'], 'logo_alt', 'Teks alternatif logo maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidLogos')]
    public function test_invalid_logo_uploads_are_rejected_without_media_rows_or_files(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.insurance-partners.store'), array_merge($this->valid(), $override))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, InsurancePartner::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
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
    public function test_dangerous_logo_files_are_rejected_without_media_rows_or_files_sek09_sek10(string $factory): void
    {
        Storage::fake('public');

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.insurance-partners.store'), array_merge($this->valid(), [
                'logo' => call_user_func([ImageFixtures::class, $factory]),
                'logo_alt' => 'Berkas berbahaya',
            ]))
            ->assertSessionHasErrors('logo');

        $this->assertSame(0, InsurancePartner::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_svg_logo_is_rejected_with_the_not_an_image_message(): void
    {
        Storage::fake('public');

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.insurance-partners.store'), array_merge($this->valid(), [
                'logo' => ImageFixtures::svgWithScript(),
                'logo_alt' => 'SVG',
            ]))
            ->assertSessionHasErrors(['logo' => 'File harus berupa gambar.']);
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.insurance-partners.create'))
            ->post(route('admin.insurance-partners.store'), array_merge($this->valid(), ['name' => '', 'description' => 'Deskripsi lama saya']))
            ->assertRedirect(route('admin.insurance-partners.create'));

        $this->get(route('admin.insurance-partners.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama mitra wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Deskripsi lama saya');
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider dijalankan sebelum GD siap dipakai).
     */
    private function materialize(mixed $value): mixed
    {
        return match ($value) {
            'LOGO' => ImageFixtures::jpeg(400, 200),
            'SMALL' => ImageFixtures::jpeg(100, 30),
            'BIG' => ImageFixtures::oversizedJpeg(2100, 400, 200),
            'ZZZ' => str_repeat('a', 256),
            default => $value,
        };
    }
}
