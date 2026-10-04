<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminClinicIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

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
        $this->createClinic(['name' => 'Poliklinik Anak']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.clinics.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.clinics.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.clinics.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $clinic = $this->createClinic();

        $this->assertSame(url('/admin/poliklinik'), route('admin.clinics.index'));
        $this->assertSame(url('/admin/poliklinik/buat'), route('admin.clinics.create'));
        $this->assertSame(url('/admin/poliklinik/'.$clinic->id.'/ubah'), route('admin.clinics.edit', $clinic));
    }

    public function test_index_renders_many_rows_with_specialty_cover_and_doctor_counts_in_strict_mode(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $mata = $this->createSpecialty(['name' => 'Mata']);
        $clinicA = $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK', 'specialty_id' => $anak->id, 'location' => 'Gedung A Lt. 1']);
        $clinicB = $this->createClinic(['name' => 'Poliklinik Mata', 'code' => 'MTA', 'specialty_id' => $mata->id]);
        $clinicC = Clinic::factory()->support()->create(['name' => 'Laboratorium', 'code' => 'LAB', 'specialty_id' => null]);
        $clinicD = $this->createClinic(['name' => 'Poliklinik Lama', 'code' => 'LMA', 'specialty_id' => null, 'is_active' => false]);

        $this->createDoctor([], $clinicA, [$anak]);
        $this->createDoctor([], $clinicA, [$anak]);
        $this->createDoctor([], $clinicB, [$mata]);

        foreach ([$clinicA, $clinicB] as $clinic) {
            $this->createMediaRow([
                'model_type' => Clinic::class,
                'model_id' => $clinic->id,
                'collection' => 'cover',
                'alt_text' => 'Foto '.$clinic->name,
            ]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.index'))
            ->assertOk()
            ->assertSee('Poliklinik Anak')
            ->assertSee('Poliklinik Mata')
            ->assertSee('Laboratorium')
            ->assertSee('Poliklinik Lama')
            ->assertSee('ANK')
            ->assertSee('Layanan penunjang')
            ->assertSee('Gedung A Lt. 1')
            ->assertSee('Masih dirujuk oleh 2 dokter.')
            ->assertSee('Masih dirujuk oleh 1 dokter.')
            ->assertSee('data-confirm-action="'.route('admin.clinics.destroy', $clinicC).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.clinics.destroy', $clinicD).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.clinics.destroy', $clinicA).'"', false);
    }

    public function test_index_shows_both_module_tabs_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.clinics.index').'"', false)
            ->assertSee('href="'.route('admin.specialties.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Tambah Poliklinik')
            ->assertSee('href="'.route('admin.clinics.create').'"', false)
            ->assertSee('Poliklinik dan Spesialisasi');
    }

    public function test_search_matches_name_and_code_and_filters_by_status_and_sort(): void
    {
        $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK', 'sort_order' => 2]);
        $this->createClinic(['name' => 'Poliklinik Mata', 'code' => 'MTA', 'sort_order' => 1]);
        $this->createClinic(['name' => 'Poliklinik Lama', 'code' => 'LMA', 'sort_order' => 3, 'is_active' => false]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.clinics.index', ['q' => 'mta']))
            ->assertOk()->assertSee('Poliklinik Mata')->assertDontSee('Poliklinik Anak');

        $this->get(route('admin.clinics.index', ['q' => 'anak']))
            ->assertOk()->assertSee('Poliklinik Anak')->assertDontSee('Poliklinik Mata');

        $this->get(route('admin.clinics.index', ['status' => 'nonaktif']))
            ->assertOk()->assertSee('Poliklinik Lama')->assertDontSee('Poliklinik Anak');

        $this->get(route('admin.clinics.index'))
            ->assertSeeInOrder(['Poliklinik Mata', 'Poliklinik Anak', 'Poliklinik Lama']);

        $this->get(route('admin.clinics.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['Poliklinik Anak', 'Poliklinik Lama', 'Poliklinik Mata']);
    }

    public function test_invalid_filters_are_ignored_and_the_sort_is_preserved_by_the_search_form(): void
    {
        $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.clinics.index', ['status' => 'zzz', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Poliklinik Anak')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.clinics.index', ['urut' => 'urutan']))
            ->assertOk()
            ->assertSee('name="urut"', false)
            ->assertSee('value="urutan"', false);
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.index'))
            ->assertOk()
            ->assertSee('Belum ada poliklinik');
    }
}
