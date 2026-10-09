<?php

namespace Tests\Feature\Admin;

use App\Models\PatientProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Daftar pasien admin (#37, F7A-R15/R16): WLK-02 (cari nama, no. pasien, telepon, tanggal lahir dd/mm/yyyy; BPJS tidak
 * terbuka), filter kanal dan status, urutan nama, input larik/liar tanpa 500 (VAL-07, E3), akses (AKS-03/04/05, PAS-07),
 * EMP-03, dan SEK-15 (nama ber-HTML ter-escape).
 */
class AdminPatientsIndexTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function profile(array $attributes = []): PatientProfile
    {
        return PatientProfile::factory()->create($attributes);
    }

    /** Tiga profil walk-in bernomor tetap (nomor eksplisit agar tidak bentrok dengan nomor acak factory). */
    private function seedThree(): void
    {
        $this->profile(['full_name' => 'Siti Rahma', 'patient_number' => 'P000123', 'phone' => '081200000001', 'birth_date' => '1990-03-12']);
        $this->profile(['full_name' => 'Budi Santoso', 'patient_number' => 'P000124', 'phone' => '081200000002', 'birth_date' => '1985-07-03']);
        $this->profile(['full_name' => 'Dewi Lestari', 'patient_number' => 'P000125', 'phone' => '081300000003', 'birth_date' => '2001-01-20']);
    }

    // --- akses -------------------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function viewers(): array
    {
        return [
            'super admin' => ['super-admin'],
            'petugas pendaftaran' => ['registration-officer'],
        ];
    }

    #[DataProvider('viewers')]
    public function test_staff_with_patients_view_can_open_the_list(string $role): void
    {
        $this->seedThree();

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.patients.index'))
            ->assertOk()
            ->assertSee('Siti Rahma')
            ->assertSee('P000123');
    }

    public function test_admin_konten_gets_403_and_sees_no_patient_data_pas_07(): void
    {
        $this->seedThree();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.patients.index'))
            ->assertForbidden()
            ->assertDontSee('Siti Rahma')
            ->assertDontSee('P000123');
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs($this->createPatient())
            ->get(route('admin.patients.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.patients.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_patients_have_no_destroy_route(): void
    {
        $profile = $this->profile();

        $this->assertSame(url('/admin/pasien'), route('admin.patients.index'));
        $this->assertSame(url('/admin/pasien/'.$profile->id), route('admin.patients.show', $profile));
        $this->assertFalse(Route::has('admin.patients.destroy'));
    }

    public function test_the_sidebar_shows_the_patient_menu_item_to_the_registration_officer(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.patients.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.patients.index').'"', false);
    }

    // --- WLK-02: pencarian -------------------------------------------------------------------

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function searches(): array
    {
        $all = ['Siti Rahma', 'Budi Santoso', 'Dewi Lestari'];

        return [
            'nama sebagian' => ['siti', ['Siti Rahma'], ['Budi Santoso', 'Dewi Lestari']],
            'nama huruf besar' => ['SANTOSO', ['Budi Santoso'], ['Siti Rahma', 'Dewi Lestari']],
            'no. pasien lengkap' => ['P000124', ['Budi Santoso'], ['Siti Rahma', 'Dewi Lestari']],
            'no. pasien huruf kecil' => ['p000124', ['Budi Santoso'], ['Siti Rahma', 'Dewi Lestari']],
            'no. pasien angka saja' => ['125', ['Dewi Lestari'], ['Siti Rahma', 'Budi Santoso']],
            'telepon' => ['081200000002', ['Budi Santoso'], ['Siti Rahma', 'Dewi Lestari']],
            'telepon dengan tanda hubung' => ['0813-0000-0003', ['Dewi Lestari'], ['Siti Rahma', 'Budi Santoso']],
            'tanggal lahir dd/mm/yyyy' => ['12/03/1990', ['Siti Rahma'], ['Budi Santoso', 'Dewi Lestari']],
            'tanggal tidak sah dicari sebagai nama' => ['31/02/1990', [], $all],
            'tidak ada yang cocok' => ['zzzz', [], $all],
        ];
    }

    /**
     * @param  list<string>  $visible
     * @param  list<string>  $hidden
     */
    #[DataProvider('searches')]
    public function test_the_search_box_finds_patients_by_name_number_phone_or_birth_date(string $term, array $visible, array $hidden): void
    {
        $this->seedThree();

        $response = $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.patients.index', ['q' => $term]))
            ->assertOk();

        foreach ($visible as $name) {
            $response->assertSee($name);
        }

        foreach ($hidden as $name) {
            $response->assertDontSee($name);
        }
    }

    public function test_a_search_without_results_shows_the_empty_state_with_a_reset_link(): void
    {
        $this->seedThree();

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.patients.index', ['q' => 'zzzz']))
            ->assertOk()
            ->assertSee('Pasien tidak ditemukan')
            ->assertSee('Hapus filter');
    }

    public function test_the_list_never_shows_the_full_bpjs_number_and_it_is_not_searchable(): void
    {
        PatientProfile::factory()->withBpjs('0009900000011')->create(['full_name' => 'Pasien Berbpjs', 'patient_number' => 'P000130']);

        $officer = $this->createStaff('registration-officer');

        $this->actingAs($officer)
            ->get(route('admin.patients.index'))
            ->assertOk()
            ->assertSee('Pasien Berbpjs')
            ->assertDontSee('0009900000011');

        $this->get(route('admin.patients.index', ['q' => '0009900000011']))
            ->assertOk()
            ->assertDontSee('Pasien Berbpjs');
    }

    // --- filter dan urutan -------------------------------------------------------------------

    public function test_the_channel_and_status_filters_narrow_the_list(): void
    {
        $this->profile(['full_name' => 'Walk In Aktif', 'patient_number' => 'P000201']);
        $this->profile(['full_name' => 'Walk In Nonaktif', 'patient_number' => 'P000202', 'is_active' => false]);
        PatientProfile::factory()->withAccount()->create(['full_name' => 'Akun Online Aktif', 'patient_number' => 'P000203']);

        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.patients.index', ['kanal' => 'online']))
            ->assertOk()
            ->assertSee('Akun Online Aktif')
            ->assertDontSee('Walk In Aktif')
            ->assertDontSee('Walk In Nonaktif')
            ->assertSee('<option value="online" selected>', false);

        $this->get(route('admin.patients.index', ['kanal' => 'loket']))
            ->assertSee('Walk In Aktif')
            ->assertSee('Walk In Nonaktif')
            ->assertDontSee('Akun Online Aktif');

        $this->get(route('admin.patients.index', ['status' => 'nonaktif']))
            ->assertSee('Walk In Nonaktif')
            ->assertDontSee('Walk In Aktif')
            ->assertDontSee('Akun Online Aktif')
            ->assertSee('<option value="nonaktif" selected>', false);

        $this->get(route('admin.patients.index', ['status' => 'aktif']))
            ->assertSee('Walk In Aktif')
            ->assertSee('Akun Online Aktif')
            ->assertDontSee('Walk In Nonaktif');

        $this->get(route('admin.patients.index', ['status' => 'aktif', 'kanal' => 'loket', 'q' => 'walk']))
            ->assertSee('Walk In Aktif')
            ->assertDontSee('Walk In Nonaktif')
            ->assertDontSee('Akun Online Aktif');
    }

    public function test_the_columns_show_the_channel_badge_and_whether_an_account_is_linked(): void
    {
        $this->profile(['full_name' => 'Walk In Satu', 'patient_number' => 'P000211']);
        PatientProfile::factory()->withAccount()->create(['full_name' => 'Akun Satu', 'patient_number' => 'P000212']);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.patients.index'))
            ->assertOk()
            ->assertSee('<th scope="col">No. pasien</th>', false)
            ->assertSee('<th scope="col">Akun terhubung</th>', false)
            ->assertSee('data-label="Akun terhubung">Ya</td>', false)
            ->assertSee('data-label="Akun terhubung">Tidak</td>', false)
            ->assertSee('Online')
            ->assertSee('Loket');
    }

    public function test_the_list_is_ordered_by_name_and_paginated_in_strict_mode(): void
    {
        $names = ['Budi Santoso', 'Dewi Lestari', 'Siti Rahma'];

        foreach (array_reverse($names) as $index => $name) {
            $this->profile(['full_name' => $name, 'patient_number' => 'P0003'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
        }

        foreach (range(1, 9) as $index) {
            $this->profile(['full_name' => 'Zeta Pasien '.$index, 'patient_number' => 'P0004'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
        }

        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.patients.index', ['per_page' => 10]))
            ->assertOk()
            ->assertSeeInOrder($names)
            ->assertSee('Menampilkan 1–10 dari 12 data')
            ->assertDontSee('Zeta Pasien 9');

        $this->get(route('admin.patients.index', ['per_page' => 10, 'page' => 2]))
            ->assertOk()
            ->assertSee('Zeta Pasien 9')
            ->assertDontSee('Budi Santoso');
    }

    // --- E3 / VAL-07: input liar -------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function hostileQueries(): array
    {
        return [
            'q larik' => ['q[]=x'],
            'q hash larik' => ['q[a][b]=x'],
            'kanal liar' => ['kanal=zzz'],
            'kanal larik' => ['kanal[]=online'],
            'status larik' => ['status[]=1'],
            'status liar' => ['status=zzz'],
            'per_page liar' => ['per_page=7'],
            'page bukan angka' => ['page=abc'],
            'page di luar rentang' => ['page=999'],
            'kembali eksternal' => ['kembali=https://situs-lain.test'],
            'q null byte' => ['q=%00'],
            'q sangat panjang' => ['q='.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'.'aaaaaaaaaa'],
        ];
    }

    #[DataProvider('hostileQueries')]
    public function test_hostile_query_strings_never_cause_a_500(string $query): void
    {
        $this->seedThree();

        $this->actingAs($this->createStaff('registration-officer'))
            ->get('/admin/pasien?'.$query)
            ->assertOk()
            ->assertDontSee('situs-lain.test');
    }

    public function test_an_array_search_term_is_ignored_with_the_standard_warning(): void
    {
        $this->seedThree();

        $this->actingAs($this->createStaff('registration-officer'))
            ->get('/admin/pasien?q[]=x')
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Siti Rahma')
            ->assertSee('Budi Santoso')
            ->assertSee('Dewi Lestari');
    }

    // --- EMP-03 dan tombol tambah -------------------------------------------------------------

    public function test_an_empty_list_shows_the_empty_state_without_errors(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.patients.index'))
            ->assertOk()
            ->assertSee('Belum ada pasien')
            ->assertSee('Tidak ada data untuk ditampilkan.');
    }

    public function test_the_walk_in_button_is_hidden_until_the_create_route_exists(): void
    {
        $this->assertFalse(Route::has('admin.patients.create'), 'T6 mendaftarkan route ini dan mengganti tes ini.');

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.patients.index'))
            ->assertOk()
            ->assertDontSee('Tambah Pasien Walk-in');
    }

    // --- SEK-15 --------------------------------------------------------------------------------

    public function test_html_in_a_patient_name_is_escaped_sek_15(): void
    {
        $this->profile(['full_name' => '<script>alert(1)</script>', 'patient_number' => 'P000250']);

        $response = $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.patients.index'))
            ->assertOk()
            ->assertSee('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getContent());
    }
}
