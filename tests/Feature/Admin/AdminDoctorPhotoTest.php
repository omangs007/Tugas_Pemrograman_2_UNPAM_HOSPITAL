<?php

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorPhotoTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function upload(Doctor $doctor, string $alt = 'Foto dokter', ?int $size = 800): TestResponse
    {
        return $this->from(route('admin.doctors.show', $doctor))->post(route('admin.doctors.photo', $doctor), [
            'photo' => ImageFixtures::jpeg($size, $size, 'foto.jpg'),
            'alt_text' => $alt,
        ]);
    }

    public function test_first_photo_is_stored_for_the_doctor_with_alt_text_med01(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'));
        $this->upload($doctor, 'Foto dr. Laras')
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Foto dokter berhasil diperbarui.');

        $media = Media::query()->where('collection', 'photo')->sole();

        $this->assertSame(Doctor::class, $media->model_type);
        $this->assertSame($doctor->id, $media->model_id);
        $this->assertSame('Foto dr. Laras', $media->alt_text);
        $this->assertNotNull($media->thumb_path);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_replacing_the_photo_removes_the_old_file_and_row_after_commit_med02(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('super-admin'));

        $this->upload($doctor, 'Foto lama');
        $old = Media::query()->where('collection', 'photo')->sole();
        $oldFile = $old->directory.'/'.$old->file_name;

        $this->upload($doctor, 'Foto baru', 700)->assertSessionHas('success');

        $new = Media::query()->where('collection', 'photo')->sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Foto baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldFile);
        Storage::disk('public')->assertMissing($old->thumb_path);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
        $this->assertDatabaseMissing('media', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.replaced']);
    }

    public function test_a_rejected_upload_leaves_the_existing_photo_untouched(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('content-admin'));
        $this->upload($doctor, 'Foto lama');
        $old = Media::query()->where('collection', 'photo')->sole();

        $this->upload($doctor, '')
            ->assertSessionHasErrors(['alt_text' => 'Teks alternatif foto wajib diisi.']);

        $this->from(route('admin.doctors.show', $doctor))
            ->post(route('admin.doctors.photo', $doctor), ['alt_text' => 'Tanpa berkas'])
            ->assertSessionHasErrors(['photo' => 'Foto wajib dipilih.']);

        $this->assertSame($old->id, Media::query()->where('collection', 'photo')->sole()->id);
        Storage::disk('public')->assertExists($old->directory.'/'.$old->file_name);
    }

    public function test_invalid_images_are_rejected_with_indonesian_messages(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('content-admin'))->from(route('admin.doctors.show', $doctor));

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::jpeg(200, 200), 'alt_text' => 'x'])
            ->assertSessionHasErrors(['photo' => 'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.']);

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::oversizedJpeg(2100, 800, 800), 'alt_text' => 'x'])
            ->assertSessionHasErrors(['photo' => 'Ukuran gambar maksimal 2 MB.']);

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::jpeg(800, 800), 'alt_text' => str_repeat('a', 256)])
            ->assertSessionHasErrors(['alt_text' => 'Teks alternatif foto maksimal 255 karakter.']);

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::svgWithScript(), 'alt_text' => 'x'])
            ->assertSessionHasErrors('photo');

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::textNamedJpg(), 'alt_text' => 'x'])
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_registration_officer_patient_and_guest_cannot_upload(): void
    {
        $doctor = $this->createDoctor();
        $url = route('admin.doctors.photo', $doctor);
        $data = ['photo' => ImageFixtures::jpeg(800, 800), 'alt_text' => 'x'];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user)->post($url, $data)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->post($url, $data)->assertRedirect(route('admin.login'));

        $this->assertSame(0, Media::query()->count());
    }

    public function test_detail_page_shows_the_photo_form_only_to_managers(): void
    {
        $doctor = $this->createDoctor();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('Ganti foto')
            ->assertSee('action="'.route('admin.doctors.photo', $doctor).'"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('name="alt_text"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertDontSee('action="'.route('admin.doctors.photo', $doctor).'"', false);
    }

    public function test_pages_stay_ok_with_a_fallback_when_the_photo_file_is_missing_med08(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $this->actingAs($this->createStaff('content-admin'));
        $this->upload($doctor, 'Foto dr. Laras');
        $media = Media::query()->where('collection', 'photo')->sole();
        Storage::disk('public')->delete([$media->directory.'/'.$media->file_name, $media->thumb_path]);

        foreach ([route('admin.doctors.index'), route('admin.doctors.show', $doctor), route('admin.doctors.edit', $doctor)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-fallback=', false)
                ->assertSee('images/placeholders/doctor.svg', false)
                ->assertDontSee('onerror', false);
        }
    }
}
