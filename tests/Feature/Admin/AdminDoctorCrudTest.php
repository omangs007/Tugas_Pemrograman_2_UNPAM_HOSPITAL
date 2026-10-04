<?php

namespace Tests\Feature\Admin;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorCrudTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Clinic $clinic;

    private Specialty $specialtyA;

    private Specialty $specialtyB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->specialtyA = $this->createSpecialty(['name' => 'Anak']);
        $this->specialtyB = $this->createSpecialty(['name' => 'Dokter Umum']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Laras Wulandari',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'gender' => 'P',
            'clinic_id' => (string) $this->clinic->id,
            'specialty_ids' => [(string) $this->specialtyA->id, (string) $this->specialtyB->id],
            'primary_specialty_id' => (string) $this->specialtyA->id,
            'short_bio' => 'Melayani konsultasi anak.',
            'bio' => 'Biodata contoh tanpa klaim.',
            'sort_order' => '4',
            'meta_title' => '',
            'meta_description' => '',
        ], $overrides);
    }

    public function test_create_form_lists_only_active_choices_and_has_no_status_or_slug_field(): void
    {
        $inactiveClinic = $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false]);
        $inactiveSpecialty = $this->createSpecialty(['name' => 'Spesialisasi Tutup', 'is_active' => false]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.create'))
            ->assertOk()
            ->assertSee('Tambah Dokter')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="title_prefix"', false)
            ->assertSee('name="title_suffix"', false)
            ->assertSee('name="gender"', false)
            ->assertSee('name="clinic_id"', false)
            ->assertSee('name="specialty_ids[]"', false)
            ->assertSee('name="primary_specialty_id"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('name="photo_alt"', false)
            ->assertSee('Poliklinik Anak')
            ->assertSee('Dokter Umum')
            ->assertDontSee($inactiveClinic->name)
            ->assertDontSee($inactiveSpecialty->name)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_doctor_with_specialties_and_one_primary_crd01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload())
            ->assertRedirect(route('admin.doctors.index'))
            ->assertSessionHas('success', 'Dokter berhasil ditambahkan.');

        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();

        $this->assertSame('laras-wulandari', $doctor->slug);
        $this->assertSame(DoctorStatus::Active, $doctor->status);
        $this->assertSame(Gender::Female, $doctor->gender);
        $this->assertSame($this->clinic->id, $doctor->clinic_id);
        $this->assertNull($doctor->archived_at);
        $this->assertSame(4, $doctor->sort_order);

        $pivot = DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->orderBy('specialty_id')->get();
        $this->assertCount(2, $pivot);
        $this->assertSame([$this->specialtyA->id], $pivot->where('is_primary', 1)->pluck('specialty_id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.created']);
    }

    public function test_two_doctors_with_the_same_name_get_numbered_slugs(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.doctors.store'), $this->payload(['name' => 'Andi Pratama']))->assertSessionHasNoErrors();
        $this->post(route('admin.doctors.store'), $this->payload(['name' => 'Andi Pratama']))->assertSessionHasNoErrors();

        $this->assertSame(['andi-pratama', 'andi-pratama-2'], Doctor::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_status_slug_and_archived_at_cannot_be_forced_through_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload(['status' => 'archived', 'slug' => 'dipaksa', 'archived_at' => '2026-01-01']))
            ->assertRedirect(route('admin.doctors.index'));

        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();

        $this->assertSame(DoctorStatus::Active, $doctor->status);
        $this->assertSame('laras-wulandari', $doctor->slug);
        $this->assertNull($doctor->archived_at);
    }

    public function test_photo_uploaded_on_create_is_stored_for_the_doctor_med01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload([
                'photo' => ImageFixtures::jpeg(800, 800, 'foto.jpg'),
                'photo_alt' => 'Foto dr. Laras Wulandari',
            ]))
            ->assertRedirect(route('admin.doctors.index'));

        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();
        $media = Media::query()->where('collection', 'photo')->sole();

        $this->assertSame(Doctor::class, $media->model_type);
        $this->assertSame($doctor->id, $media->model_id);
        $this->assertSame('public', $media->disk);
        $this->assertSame('Foto dr. Laras Wulandari', $media->alt_text);
        $this->assertSame('jpg', $media->extension);
        $this->assertStringStartsWith('photo/'.now()->format('Y/m'), $media->directory);
        $this->assertNotNull($media->thumb_path);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_edit_form_shows_current_values_and_the_primary_specialty(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A', 'short_bio' => 'Bio singkat lama.'], $this->clinic, [$this->specialtyB, $this->specialtyA]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.doctors.edit', $doctor))
            ->assertOk()
            ->assertSee('Ubah')
            ->assertSee('value="Laras Wulandari"', false)
            ->assertSee('value="Sp.A"', false)
            ->assertSee('Bio singkat lama.')
            ->assertSee('action="'.route('admin.doctors.update', $doctor).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('id="primary-specialty-'.$this->specialtyB->id.'" value="'.$this->specialtyB->id.'" checked', false);
    }

    public function test_update_changes_data_clinic_and_specialties_but_never_slug_or_status(): void
    {
        $other = $this->createClinic(['name' => 'Poliklinik Mata']);
        $doctor = Doctor::factory()->inactive()->create(['name' => 'Nama Lama', 'clinic_id' => $this->clinic->id]);
        $doctor->specialties()->attach($this->specialtyA->id, ['is_primary' => true]);
        $slug = $doctor->slug;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.doctors.update', $doctor), $this->payload([
                'name' => 'Nama Baru',
                'clinic_id' => (string) $other->id,
                'specialty_ids' => [(string) $this->specialtyB->id],
                'primary_specialty_id' => (string) $this->specialtyB->id,
                'status' => 'active',
                'slug' => 'dipaksa',
            ]))
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Dokter berhasil diperbarui.');

        $doctor->refresh();
        $this->assertSame('Nama Baru', $doctor->name);
        $this->assertSame($other->id, $doctor->clinic_id);
        $this->assertSame($slug, $doctor->slug);
        $this->assertSame(DoctorStatus::Inactive, $doctor->status);
        $this->assertSame(
            [[$this->specialtyB->id, 1]],
            DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->get()->map(fn ($row) => [(int) $row->specialty_id, (int) $row->is_primary])->all(),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.updated']);
    }

    public function test_update_may_keep_a_clinic_and_specialty_that_became_inactive_but_cannot_pick_new_inactive_ones(): void
    {
        $doctor = $this->createDoctor([], $this->clinic, [$this->specialtyA]);
        $this->clinic->update(['is_active' => false]);
        $this->specialtyA->update(['is_active' => false]);
        $otherInactive = $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false]);
        $this->actingAs($this->createStaff('content-admin'));

        $keep = $this->payload([
            'specialty_ids' => [(string) $this->specialtyA->id],
            'primary_specialty_id' => (string) $this->specialtyA->id,
        ]);

        $this->put(route('admin.doctors.update', $doctor), $keep)->assertSessionHasNoErrors();

        $this->put(route('admin.doctors.update', $doctor), array_merge($keep, ['clinic_id' => (string) $otherInactive->id]))
            ->assertSessionHasErrors(['clinic_id' => 'Poliklinik yang dipilih tidak tersedia.']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.edit', $doctor))
            ->assertOk()
            ->assertSee('Poliklinik Anak')
            ->assertSee('Anak');
    }

    public function test_update_rejects_a_new_inactive_specialty_that_was_not_attached_before(): void
    {
        $doctor = $this->createDoctor([], $this->clinic, [$this->specialtyA]);
        $newInactive = $this->createSpecialty(['name' => 'Spesialisasi Tutup', 'is_active' => false]);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.doctors.update', $doctor), $this->payload([
                'specialty_ids' => [(string) $this->specialtyA->id, (string) $newInactive->id],
                'primary_specialty_id' => (string) $this->specialtyA->id,
            ]))
            ->assertSessionHasErrors(['specialty_ids' => 'Spesialisasi yang dipilih tidak tersedia.']);

        $this->assertSame(1, DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->count());
    }

    public function test_update_with_a_new_photo_replaces_the_existing_one(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload(['photo' => ImageFixtures::jpeg(800, 800), 'photo_alt' => 'Foto lama']));
        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();
        $old = Media::query()->where('collection', 'photo')->sole();

        $this->put(route('admin.doctors.update', $doctor), $this->payload(['photo' => ImageFixtures::jpeg(700, 700, 'baru.jpg'), 'photo_alt' => 'Foto baru']))
            ->assertSessionHasNoErrors();

        $new = Media::query()->where('collection', 'photo')->sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Foto baru', $new->alt_text);
        Storage::disk('public')->assertMissing($old->directory.'/'.$old->file_name);
    }

    public function test_update_with_only_a_changed_photo_alt_updates_the_alt_of_the_existing_photo(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload(['photo' => ImageFixtures::jpeg(800, 800), 'photo_alt' => 'Foto lama']));
        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();
        $media = Media::query()->where('collection', 'photo')->sole();

        $this->put(route('admin.doctors.update', $doctor), $this->payload(['photo_alt' => ' Foto baru ']))
            ->assertSessionHasNoErrors();

        $same = Media::query()->where('collection', 'photo')->sole();
        $this->assertSame($media->id, $same->id);
        $this->assertSame('Foto baru', $same->alt_text);
    }

    public function test_update_with_an_empty_photo_alt_is_rejected_while_a_photo_exists_and_keeps_the_old_alt(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload(['photo' => ImageFixtures::jpeg(800, 800), 'photo_alt' => 'Foto lama']));
        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();

        $this->put(route('admin.doctors.update', $doctor), $this->payload(['name' => 'Nama Tidak Tersimpan', 'photo_alt' => '']))
            ->assertSessionHasErrors(['photo_alt' => 'Teks alternatif foto wajib diisi.']);

        $this->assertSame('Foto lama', Media::query()->where('collection', 'photo')->sole()->alt_text);
        $this->assertSame('Laras Wulandari', $doctor->fresh()->name);
    }

    public function test_update_with_empty_photo_alt_and_no_photo_has_no_new_error(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.doctors.store'), $this->payload());
        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();

        $this->put(route('admin.doctors.update', $doctor), $this->payload(['photo_alt' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Media::query()->where('collection', 'photo')->count());
    }

    public function test_registration_officer_patient_and_guest_cannot_use_manage_routes(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $requests = [
            ['GET', route('admin.doctors.create'), []],
            ['POST', route('admin.doctors.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['GET', route('admin.doctors.edit', $doctor), []],
            ['PUT', route('admin.doctors.update', $doctor), $this->payload(['name' => 'Diubah Paksa'])],
        ];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user);

            foreach ($requests as [$method, $url, $data]) {
                $this->call($method, $url, $data)->assertForbidden();
            }
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url, $data]) {
            $this->call($method, $url, $data)->assertRedirect(route('admin.login'));
        }

        $this->assertSame('Laras Wulandari', $doctor->fresh()->name);
        $this->assertDatabaseMissing('doctors', ['name' => 'Tidak Boleh']);
        $this->assertSame(1, Doctor::query()->count());
    }

    public function test_super_admin_can_open_the_manage_routes(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('super-admin'));

        $this->get(route('admin.doctors.create'))->assertOk();
        $this->get(route('admin.doctors.edit', $doctor))->assertOk();
    }
}
