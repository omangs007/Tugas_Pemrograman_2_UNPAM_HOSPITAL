<?php

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function viewers(): array
    {
        return [
            'super admin' => ['super-admin'],
            'admin konten' => ['content-admin'],
            'petugas pendaftaran' => ['registration-officer'],
        ];
    }

    #[DataProvider('viewers')]
    public function test_every_staff_role_with_doctors_view_can_open_the_list(string $role): void
    {
        $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertSee('Laras Wulandari');
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.doctors.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.doctors.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_doctors_have_no_destroy_route(): void
    {
        $doctor = $this->createDoctor();

        $this->assertSame(url('/admin/dokter'), route('admin.doctors.index'));
        $this->assertSame(url('/admin/dokter/buat'), route('admin.doctors.create'));
        $this->assertSame(url('/admin/dokter/'.$doctor->id), route('admin.doctors.show', $doctor));
        $this->assertSame(url('/admin/dokter/'.$doctor->id.'/ubah'), route('admin.doctors.edit', $doctor));
        $this->assertFalse(Route::has('admin.doctors.destroy'));
    }

    public function test_create_route_is_registered_before_the_show_route(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.doctors.'))
            ->values()
            ->all();

        $this->assertLessThan(array_search('admin.doctors.show', $names, true), array_search('admin.doctors.create', $names, true));
    }

    public function test_delete_to_a_doctor_url_is_not_allowed(): void
    {
        $doctor = $this->createDoctor();

        $this->actingAs($this->createStaff('super-admin'))
            ->delete('/admin/dokter/'.$doctor->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    public function test_list_renders_many_doctors_with_specialties_clinic_and_photo_in_strict_mode(): void
    {
        $urologi = $this->createSpecialty(['name' => 'Urologi']);
        $penyakitDalam = $this->createSpecialty(['name' => 'Penyakit Dalam']);
        $clinicA = $this->createClinic(['name' => 'Poliklinik Urologi']);
        $clinicB = $this->createClinic(['name' => 'Poliklinik Jantung']);

        $raka = $this->createDoctor(['name' => 'Raka Pradipta', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.U'], $clinicA, [$urologi]);
        $maya = $this->createDoctor(['name' => 'Maya Kartika', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.JP'], $clinicB, [$penyakitDalam, $urologi]);
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null], $clinicB, [$penyakitDalam]);
        $this->createDoctor(['name' => 'Teguh Wicaksono'], $clinicA, [$urologi]);

        foreach ([$raka, $maya] as $doctor) {
            $this->createMediaRow(['model_type' => Doctor::class, 'model_id' => $doctor->id, 'collection' => 'photo', 'alt_text' => 'Foto '.$doctor->name]);
        }

        foreach (['super-admin', 'content-admin', 'registration-officer'] as $role) {
            $this->actingAs($this->createStaff($role))
                ->get(route('admin.doctors.index'))
                ->assertOk()
                ->assertSee('dr. Raka Pradipta, Sp.U')
                ->assertSee('dr. Maya Kartika, Sp.JP')
                ->assertSee('dr. Rani Oktaviani')
                ->assertDontSee('dr. Rani Oktaviani,')
                ->assertSee('Teguh Wicaksono')
                ->assertSee('Poliklinik Urologi')
                ->assertSee('Poliklinik Jantung')
                ->assertSee('Penyakit Dalam')
                ->assertSee('(utama)')
                ->assertSee('Aktif');
        }
    }

    public function test_default_status_filter_is_active_and_the_other_statuses_are_filterable(): void
    {
        $this->createDoctor(['name' => 'Dokter Aktif Satu']);
        Doctor::factory()->inactive()->withSpecialties()->create(['name' => 'Dokter Nonaktif Satu']);
        Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Dokter Arsip Satu']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertSee('Dokter Aktif Satu')
            ->assertDontSee('Dokter Nonaktif Satu')
            ->assertDontSee('Dokter Arsip Satu')
            ->assertSee('value="active" selected', false);

        $this->get(route('admin.doctors.index', ['status' => 'inactive']))
            ->assertSee('Dokter Nonaktif Satu')->assertDontSee('Dokter Aktif Satu')->assertDontSee('Dokter Arsip Satu');

        $this->get(route('admin.doctors.index', ['status' => 'archived']))
            ->assertSee('Dokter Arsip Satu')->assertDontSee('Dokter Aktif Satu')->assertDontSee('Dokter Nonaktif Satu');

        $this->get(route('admin.doctors.index', ['status' => 'semua']))
            ->assertSee('Dokter Aktif Satu')->assertSee('Dokter Nonaktif Satu')->assertSee('Dokter Arsip Satu');
    }

    public function test_filters_by_clinic_and_specialty_slug_search_and_sort(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $mata = $this->createSpecialty(['name' => 'Mata']);
        $clinicAnak = $this->createClinic(['name' => 'Poliklinik Anak']);
        $clinicMata = $this->createClinic(['name' => 'Poliklinik Mata']);
        $this->createDoctor(['name' => 'Budi Anak', 'sort_order' => 2], $clinicAnak, [$anak]);
        $this->createDoctor(['name' => 'Citra Mata', 'sort_order' => 1], $clinicMata, [$mata]);
        $this->createDoctor(['name' => 'Agus Gabungan', 'sort_order' => 3], $clinicMata, [$anak, $mata]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.doctors.index', ['poli' => $clinicAnak->slug]))
            ->assertSee('Budi Anak')->assertDontSee('Citra Mata')->assertDontSee('Agus Gabungan');

        $this->get(route('admin.doctors.index', ['spesialisasi' => $anak->slug]))
            ->assertSee('Budi Anak')->assertSee('Agus Gabungan')->assertDontSee('Citra Mata');

        $this->get(route('admin.doctors.index', ['q' => 'citra']))
            ->assertSee('Citra Mata')->assertDontSee('Budi Anak');

        $this->get(route('admin.doctors.index'))
            ->assertSeeInOrder(['Citra Mata', 'Budi Anak', 'Agus Gabungan']);

        $this->get(route('admin.doctors.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['Agus Gabungan', 'Budi Anak', 'Citra Mata']);

        $this->get(route('admin.doctors.index', ['urut' => '-nama']))
            ->assertSeeInOrder(['Citra Mata', 'Budi Anak', 'Agus Gabungan']);

        $this->get(route('admin.doctors.index', ['urut' => '-terbaru']))
            ->assertOk()
            ->assertSeeInOrder(['Agus Gabungan', 'Citra Mata', 'Budi Anak']);
    }

    public function test_invalid_filters_fall_back_to_defaults_without_an_error(): void
    {
        $this->createDoctor(['name' => 'Dokter Aktif Satu']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index', ['status' => 'zzz', 'poli' => 'tidak-ada', 'spesialisasi' => 'tidak-ada', 'urut' => 'x', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Dokter Aktif Satu')
            ->assertDontSee('situs-lain.test');
    }

    public function test_sort_parameter_is_preserved_by_the_search_form(): void
    {
        $this->createDoctor();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index', ['urut' => 'nama']))
            ->assertOk()
            ->assertSee('name="urut"', false)
            ->assertSee('value="nama"', false);
    }

    public function test_sort_links_drop_kembali_and_page_but_keep_the_search(): void
    {
        $this->createDoctor();

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index', ['kembali' => '/admin', 'page' => 2, 'q' => 'x']))
            ->assertOk()
            ->getContent();

        preg_match_all('/<a href="([^"]*urut=[^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            $this->assertStringContainsString('urut=', $href);
            $this->assertStringContainsString('q=x', $href);
            $this->assertStringNotContainsString('kembali=', $href);
            $this->assertStringNotContainsString('page=', $href);
        }
    }

    public function test_manage_buttons_are_shown_only_to_roles_that_can_manage(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index'))
            ->assertSee('Tambah Dokter')
            ->assertSee('href="'.route('admin.doctors.create').'"', false)
            ->assertSee('href="'.route('admin.doctors.edit', $doctor).'"', false)
            ->assertSee('href="'.route('admin.doctors.show', $doctor).'"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertDontSee('Tambah Dokter')
            ->assertDontSee(route('admin.doctors.create'))
            ->assertDontSee(route('admin.doctors.edit', $doctor))
            ->assertSee('href="'.route('admin.doctors.show', $doctor).'"', false);
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertSee('Belum ada dokter');
    }
}
