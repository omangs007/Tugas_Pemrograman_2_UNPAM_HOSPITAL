<?php

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorShowTest extends TestCase
{
    use InteractsWithMasterData;
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
    public function test_profile_shows_display_name_clinic_specialties_and_status(string $role): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $doctor = $this->createDoctor([
            'name' => 'Laras Wulandari',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'gender' => 'P',
            'short_bio' => 'Melayani konsultasi anak.',
            'bio' => "Paragraf pertama.\n\nParagraf kedua.",
        ], $clinic, [$anak, $umum]);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('dr. Laras Wulandari, Sp.A')
            ->assertSee('Poliklinik Anak')
            ->assertSee('Anak')
            ->assertSee('Dokter Umum')
            ->assertSee('Perempuan')
            ->assertSee('Melayani konsultasi anak.')
            ->assertSee('Paragraf pertama.')
            ->assertSee('Paragraf kedua.')
            ->assertSee('Aktif')
            ->assertSee('Dasbor');
    }

    public function test_display_name_has_no_dangling_comma_without_a_suffix_crd04(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('dr. Rani Oktaviani')
            ->assertDontSee('dr. Rani Oktaviani,');
    }

    public function test_content_admin_sees_manage_buttons_but_registration_officer_does_not(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertSee('href="'.route('admin.doctors.edit', $doctor).'"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertDontSee(route('admin.doctors.edit', $doctor));
    }

    public function test_inactive_and_archived_doctors_can_still_be_viewed_by_staff(): void
    {
        $inactive = Doctor::factory()->inactive()->withSpecialties()->create(['name' => 'Dokter Nonaktif Satu']);
        $archived = Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Dokter Arsip Satu']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.doctors.show', $inactive))->assertOk()->assertSee('Nonaktif');
        $this->get(route('admin.doctors.show', $archived))->assertOk()->assertSee('Diarsipkan');
    }

    public function test_patient_gets_403_unknown_or_non_numeric_ids_are_404_and_guest_is_redirected(): void
    {
        $doctor = $this->createDoctor();

        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.doctors.show', $doctor))
            ->assertForbidden();

        $this->actingAs($this->createStaff('content-admin'));
        $this->get('/admin/dokter/999999')->assertNotFound();
        $this->get('/admin/dokter/abc')->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->get(route('admin.doctors.show', $doctor))->assertRedirect(route('admin.login'));
    }
}
