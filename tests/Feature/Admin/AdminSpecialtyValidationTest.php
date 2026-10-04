<?php

namespace Tests\Feature\Admin;

use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-21: pesan Indonesia per field, nilai lama dipertahankan, ringkasan galat (VAL-01 pola yang sama).
 */
class AdminSpecialtyValidationTest extends TestCase
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
            'nama kosong' => [['name' => ''], 'name', 'Nama spesialisasi wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama spesialisasi harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama spesialisasi minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama spesialisasi maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Urologi'], 'name', 'Nama spesialisasi sudah digunakan.'],
            'nama ganda beda huruf' => [['name' => 'UROLOGI'], 'name', 'Nama spesialisasi sudah digunakan.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 1001)], 'description', 'Deskripsi maksimal 1000 karakter.'],
            'status tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'status hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'urutan negatif' => [['sort_order' => '-1'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.specialties.create'))
            ->post(route('admin.specialties.store'), array_merge(['name' => 'Spesialisasi Baru', 'is_active' => '1'], $override))
            ->assertRedirect(route('admin.specialties.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, Specialty::query()->count());
    }

    public function test_html_in_description_is_rejected(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.specialties.store'), ['name' => 'Spesialisasi Baru', 'is_active' => '1', 'description' => '<b>tebal</b>'])
            ->assertSessionHasErrors('description');

        $this->assertStringContainsString('tag HTML', (string) session('errors')->first('description'));
        $this->assertSame(0, Specialty::query()->count());
    }

    public function test_update_applies_the_same_rules(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.specialties.edit', $specialty))
            ->put(route('admin.specialties.update', $specialty), ['name' => 'Ab', 'is_active' => '1'])
            ->assertRedirect(route('admin.specialties.edit', $specialty))
            ->assertSessionHasErrors(['name' => 'Nama spesialisasi minimal 3 karakter.']);

        $this->assertSame('Urologi', $specialty->fresh()->name);
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary_with_aria_links(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.specialties.create'))
            ->post(route('admin.specialties.store'), ['name' => '', 'description' => 'Isi lama yang dipertahankan', 'is_active' => '1', 'sort_order' => '12'])
            ->assertRedirect(route('admin.specialties.create'));

        $this->get(route('admin.specialties.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama spesialisasi wajib diisi.')
            ->assertSee('href="#name"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('name-error', false)
            ->assertSee('Isi lama yang dipertahankan')
            ->assertSee('value="12"', false);
    }
}
