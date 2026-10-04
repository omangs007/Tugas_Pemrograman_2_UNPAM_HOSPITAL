<?php

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * VAL-01 dan 12 V-19: pesan Indonesia per field, nilai lama dipertahankan, ringkasan galat.
 * Penanda '@…' pada data provider diganti id nyata di dalam tes; penanda huruf besar menjadi berkas gambar.
 */
class AdminDoctorValidationTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->ids['clinic'] = $this->createClinic(['name' => 'Poliklinik Anak'])->id;
        $this->ids['clinic_inactive'] = $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false])->id;
        $this->ids['specialty_a'] = $this->createSpecialty(['name' => 'Anak'])->id;
        $this->ids['specialty_b'] = $this->createSpecialty(['name' => 'Dokter Umum'])->id;
        $this->ids['specialty_inactive'] = $this->createSpecialty(['name' => 'Spesialisasi Tutup', 'is_active' => false])->id;
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama dokter wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama dokter harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama dokter minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama dokter maksimal 100 karakter.'],
            'nama berisi angka' => [['name' => 'Andi 123'], 'name', 'Nama dokter hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.'],
            'awalan terlalu panjang' => [['title_prefix' => str_repeat('a', 21)], 'title_prefix', 'Awalan gelar maksimal 20 karakter.'],
            'awalan berisi angka' => [['title_prefix' => 'dr1'], 'title_prefix', 'Awalan gelar hanya boleh berisi huruf, titik, dan spasi.'],
            'akhiran terlalu panjang' => [['title_suffix' => str_repeat('a', 51)], 'title_suffix', 'Akhiran gelar maksimal 50 karakter.'],
            'akhiran berisi simbol' => [['title_suffix' => 'Sp.A<b>'], 'title_suffix', 'Akhiran gelar hanya boleh berisi huruf, angka, titik, koma, spasi, dan tanda hubung.'],
            'jenis kelamin kosong' => [['gender' => ''], 'gender', 'Jenis kelamin wajib dipilih.'],
            'jenis kelamin salah' => [['gender' => 'X'], 'gender', 'Jenis kelamin tidak valid.'],
            'poliklinik kosong' => [['clinic_id' => ''], 'clinic_id', 'Poliklinik utama wajib dipilih.'],
            'poliklinik tidak ada' => [['clinic_id' => '999999'], 'clinic_id', 'Poliklinik yang dipilih tidak tersedia.'],
            'poliklinik nonaktif' => [['clinic_id' => '@clinic_inactive'], 'clinic_id', 'Poliklinik yang dipilih tidak tersedia.'],
            'poliklinik bukan angka' => [['clinic_id' => 'abc'], 'clinic_id', 'Poliklinik utama harus berupa angka bulat.'],
            'spesialisasi kosong' => [['specialty_ids' => []], 'specialty_ids', 'Pilih minimal satu spesialisasi.'],
            'spesialisasi bukan larik' => [['specialty_ids' => 'x'], 'specialty_ids', 'Spesialisasi tidak valid.'],
            'spesialisasi lebih dari lima' => [['specialty_ids' => ['901', '902', '903', '904', '905', '906']], 'specialty_ids', 'Spesialisasi maksimal 5.'],
            'spesialisasi ganda' => [['specialty_ids' => ['@specialty_a', '@specialty_a']], 'specialty_ids', 'Spesialisasi tidak boleh dipilih dua kali.'],
            'spesialisasi nonaktif' => [['specialty_ids' => ['@specialty_inactive'], 'primary_specialty_id' => '@specialty_inactive'], 'specialty_ids', 'Spesialisasi yang dipilih tidak tersedia.'],
            'spesialisasi bukan angka' => [['specialty_ids' => ['abc']], 'specialty_ids', 'Spesialisasi tidak valid.'],
            'utama kosong' => [['primary_specialty_id' => ''], 'primary_specialty_id', 'Spesialisasi utama wajib dipilih.'],
            'utama bukan angka' => [['primary_specialty_id' => 'abc'], 'primary_specialty_id', 'Spesialisasi utama harus berupa angka bulat.'],
            'utama bukan salah satu pilihan' => [['specialty_ids' => ['@specialty_a'], 'primary_specialty_id' => '@specialty_b'], 'primary_specialty_id', 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.'],
            'profil singkat terlalu panjang' => [['short_bio' => str_repeat('a', 501)], 'short_bio', 'Profil singkat maksimal 500 karakter.'],
            'profil lengkap terlalu panjang' => [['bio' => str_repeat('a', 5001)], 'bio', 'Profil lengkap maksimal 5000 karakter.'],
            'profil lengkap berisi html' => [['bio' => 'Halo <b>tebal</b>'], 'bio', 'Profil lengkap tidak boleh mengandung tag HTML.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'meta title 71 karakter' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description 161 karakter' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt foto wajib' => [['photo' => 'FOTO', 'photo_alt' => ''], 'photo_alt', 'Teks alternatif foto wajib diisi.'],
            'alt foto terlalu panjang' => [['photo' => 'FOTO', 'photo_alt' => str_repeat('a', 256)], 'photo_alt', 'Teks alternatif foto maksimal 255 karakter.'],
            'foto terlalu kecil' => [['photo' => 'KECIL', 'photo_alt' => 'x'], 'photo', 'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.'],
            'foto terlalu besar' => [['photo' => 'BESAR', 'photo_alt' => 'x'], 'photo', 'Ukuran gambar maksimal 2 MB.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.create'))
            ->post(route('admin.doctors.store'), array_merge($this->valid(), $this->resolve($override)))
            ->assertRedirect(route('admin.doctors.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_missing_specialties_and_primary_report_the_required_messages(): void
    {
        $payload = $this->valid();
        unset($payload['specialty_ids'], $payload['primary_specialty_id']);

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $payload)
            ->assertSessionHasErrors([
                'specialty_ids' => 'Pilih minimal satu spesialisasi.',
                'primary_specialty_id' => 'Spesialisasi utama wajib dipilih.',
            ]);
    }

    public function test_specialty_error_is_rendered_exactly_once_and_the_summary_links_to_the_fieldset(): void
    {
        $this->createSpecialty(['name' => 'Bedah']);
        $payload = $this->valid();
        unset($payload['specialty_ids'], $payload['primary_specialty_id']);

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.create'))
            ->followingRedirects()
            ->post(route('admin.doctors.store'), $payload)
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Pilih minimal satu spesialisasi.</div>'));
        $this->assertSame(1, substr_count($html, 'id="specialty_ids-error"'));
        $this->assertSame(1, substr_count($html, '<fieldset id="specialty_ids" tabindex="-1"'));
        $this->assertStringContainsString('href="#specialty_ids"', $html);
    }

    public function test_empty_and_partly_wrong_form_shows_messages_summary_and_old_input_val01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.create'))
            ->post(route('admin.doctors.store'), [
                'name' => '',
                'gender' => 'X',
                'clinic_id' => '999999',
                'short_bio' => 'Bio lama yang dipertahankan',
                'title_prefix' => 'dr.',
                'specialty_ids' => [(string) $this->ids['specialty_a']],
                'primary_specialty_id' => (string) $this->ids['specialty_a'],
            ])
            ->assertRedirect(route('admin.doctors.create'))
            ->assertSessionHasErrors(['name', 'gender', 'clinic_id']);

        $this->get(route('admin.doctors.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama dokter wajib diisi.')
            ->assertSee('Jenis kelamin tidak valid.')
            ->assertSee('Poliklinik yang dipilih tidak tersedia.')
            ->assertSee('href="#name"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="name-help name-error"', false)
            ->assertSee('Bio lama yang dipertahankan')
            ->assertSee('value="dr."', false);

        $this->assertSame(0, Doctor::query()->count());
    }

    public function test_completely_empty_submission_lists_every_required_message(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), [])
            ->assertSessionHasErrors([
                'name' => 'Nama dokter wajib diisi.',
                'gender' => 'Jenis kelamin wajib dipilih.',
                'clinic_id' => 'Poliklinik utama wajib dipilih.',
                'specialty_ids' => 'Pilih minimal satu spesialisasi.',
                'primary_specialty_id' => 'Spesialisasi utama wajib dipilih.',
            ]);
    }

    public function test_svg_and_fake_images_are_rejected_for_the_doctor_photo_sek09(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        foreach ([ImageFixtures::svgWithScript(), ImageFixtures::textNamedJpg()] as $file) {
            $this->post(route('admin.doctors.store'), array_merge($this->valid(), ['photo' => $file, 'photo_alt' => 'x']))
                ->assertSessionHasErrors('photo');
        }

        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_duplicate_specialty_error_is_centralised_so_the_summary_link_is_valid(): void
    {
        $html = $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.create'))
            ->followingRedirects()
            ->post(route('admin.doctors.store'), array_merge($this->valid(), [
                'specialty_ids' => [(string) $this->ids['specialty_a'], (string) $this->ids['specialty_a']],
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="#specialty_ids"', $html);
        $this->assertStringNotContainsString('href="#specialty_ids-', $html);
        $this->assertSame(1, substr_count($html, 'Spesialisasi tidak boleh dipilih dua kali.</div>'));
        $this->assertSame(1, substr_count($html, 'id="specialty_ids-error"'));
    }

    public function test_update_applies_the_same_rules(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.edit', $doctor))
            ->put(route('admin.doctors.update', $doctor), array_merge($this->valid(), ['name' => 'Ab']))
            ->assertRedirect(route('admin.doctors.edit', $doctor))
            ->assertSessionHasErrors(['name' => 'Nama dokter minimal 3 karakter.']);

        $this->assertSame('Laras Wulandari', $doctor->fresh()->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Laras Wulandari',
            'gender' => 'P',
            'clinic_id' => (string) $this->ids['clinic'],
            'specialty_ids' => [(string) $this->ids['specialty_a']],
            'primary_specialty_id' => (string) $this->ids['specialty_a'],
        ];
    }

    private function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->resolve($item), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return match (true) {
            str_starts_with($value, '@') => (string) $this->ids[substr($value, 1)],
            $value === 'FOTO' => ImageFixtures::jpeg(800, 800),
            $value === 'KECIL' => ImageFixtures::jpeg(200, 200),
            $value === 'BESAR' => ImageFixtures::oversizedJpeg(2100, 800, 800),
            default => $value,
        };
    }
}
