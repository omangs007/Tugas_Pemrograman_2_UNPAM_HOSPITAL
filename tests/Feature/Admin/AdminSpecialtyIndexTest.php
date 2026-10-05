<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminSpecialtyIndexTest extends TestCase
{
    use InteractsWithMasterData;
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
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.specialties.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.specialties.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.specialties.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $specialty = $this->createSpecialty();

        $this->assertSame(url('/admin/spesialisasi'), route('admin.specialties.index'));
        $this->assertSame(url('/admin/spesialisasi/buat'), route('admin.specialties.create'));
        $this->assertSame(url('/admin/spesialisasi/'.$specialty->id.'/ubah'), route('admin.specialties.edit', $specialty));
        $this->assertFalse(Route::has('admin.specialties.show'));
    }

    public function test_index_renders_many_rows_with_counts_in_strict_mode(): void
    {
        $neutral = $this->createClinic(['specialty_id' => null]);
        $urologi = $this->createSpecialty(['name' => 'Urologi', 'sort_order' => 1]);
        $anak = $this->createSpecialty(['name' => 'Anak', 'sort_order' => 2]);
        $mata = $this->createSpecialty(['name' => 'Mata', 'sort_order' => 3]);
        $kulit = $this->createSpecialty(['name' => 'Kulit dan Kelamin', 'sort_order' => 4]);

        $this->createDoctor([], $neutral, [$urologi]);
        $this->createDoctor([], $neutral, [$urologi, $anak]);
        $this->createClinic(['specialty_id' => $mata->id]);

        $response = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Urologi')
            ->assertSee('Anak')
            ->assertSee('Mata')
            ->assertSee('Kulit dan Kelamin')
            ->assertSee('Masih dipakai oleh 2 dokter dan 0 poliklinik.')
            ->assertSee('Masih dipakai oleh 1 dokter dan 0 poliklinik.')
            ->assertSee('Masih dipakai oleh 0 dokter dan 1 poliklinik.');

        $response->assertSee('data-confirm-action="'.route('admin.specialties.destroy', $kulit).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.specialties.destroy', $urologi).'"', false);
    }

    public function test_index_has_breadcrumb_module_tab_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Poliklinik dan Spesialisasi')
            ->assertSee('href="'.route('admin.specialties.create').'"', false)
            ->assertSee('Tambah Spesialisasi')
            ->assertSee('href="'.route('admin.specialties.index').'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_search_status_and_sort_filters(): void
    {
        $this->createSpecialty(['name' => 'Urologi', 'sort_order' => 2]);
        $this->createSpecialty(['name' => 'Anak', 'sort_order' => 1]);
        $this->createSpecialty(['name' => 'Bedah Lama', 'is_active' => false, 'sort_order' => 3]);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->get(route('admin.specialties.index', ['q' => 'uro']))
            ->assertOk()->assertSee('Urologi')->assertDontSee('Bedah Lama');

        $this->get(route('admin.specialties.index', ['status' => 'nonaktif']))
            ->assertOk()->assertSee('Bedah Lama')->assertDontSee('Urologi');

        $this->get(route('admin.specialties.index', ['status' => 'aktif']))
            ->assertOk()->assertSee('Urologi')->assertDontSee('Bedah Lama');

        $this->get(route('admin.specialties.index'))
            ->assertSeeInOrder(['Anak', 'Urologi', 'Bedah Lama']);

        $this->get(route('admin.specialties.index', ['urut' => '-nama']))
            ->assertSeeInOrder(['Urologi', 'Bedah Lama', 'Anak']);

        $this->get(route('admin.specialties.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['Anak', 'Bedah Lama', 'Urologi']);
    }

    public function test_like_wildcards_in_search_are_escaped(): void
    {
        $this->createSpecialty(['name' => 'Urologi']);
        $this->createSpecialty(['name' => 'Anak']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('Urologi')
            ->assertDontSee('Anak');
    }

    public function test_invalid_filters_are_ignored_and_external_redirect_is_not_echoed(): void
    {
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['status' => 'zzz', 'urut' => 'drop table', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Urologi')
            ->assertDontSee('situs-lain.test');
    }

    public function test_data_table_preserves_the_sort_parameter_in_the_search_form(): void
    {
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['urut' => 'urutan']))
            ->assertOk()
            ->assertSee('name="urut"', false)
            ->assertSee('value="urutan"', false);
    }

    public function test_page_beyond_the_last_page_explains_and_links_back_to_page_one(): void
    {
        foreach (range(1, 26) as $number) {
            $this->createSpecialty(['name' => 'Spesialisasi '.$number]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['page' => 5]))
            ->assertOk()
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1');
    }

    public function test_default_page_size_is_25_rows(): void
    {
        foreach (range(1, 26) as $number) {
            $this->createSpecialty(['name' => 'Spesialisasi '.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'sort_order' => $number]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Spesialisasi 25')
            ->assertDontSee('Spesialisasi 26')
            ->assertSee('Menampilkan 1–25 dari 26 data');
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Belum ada spesialisasi')
            ->assertSee('Tambah Spesialisasi');
    }

    public function test_array_valued_query_params_do_not_cause_a_server_error(): void
    {
        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.specialties.index', ['urut' => ['x'], 'status' => ['x'], 'cari' => ['x']]))
            ->assertOk();
    }
}
