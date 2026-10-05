<?php

namespace Tests\Feature\View;

use App\Models\Doctor;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class DoctorCardComponentTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function render(Doctor $doctor, string $attributes = ''): string
    {
        $doctor = Doctor::query()->with(['specialties', 'media', 'clinic'])->findOrFail($doctor->id);

        return Blade::render('<x-doctor-card :doctor="$doctor" '.$attributes.' />', ['doctor' => $doctor]);
    }

    public function test_it_shows_display_name_and_only_the_primary_specialty(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], null, [$anak, $umum]);

        $html = $this->render($doctor);

        $this->assertStringContainsString('dr. Laras Wulandari, Sp.A', $html);
        $this->assertStringContainsString('Anak', $html);
        $this->assertStringNotContainsString('Dokter Umum', $html);
    }

    public function test_name_without_suffix_has_no_dangling_comma(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $html = $this->render($doctor);

        $this->assertStringContainsString('dr. Rani Oktaviani', $html);
        $this->assertStringNotContainsString('Rani Oktaviani,', $html);
    }

    public function test_the_name_links_to_the_doctor_page_with_a_stretched_link(): void
    {
        $doctor = $this->createDoctor();

        $html = $this->render($doctor);

        $this->assertStringContainsString('href="'.url('/dokter/'.$doctor->slug).'"', $html);
        $this->assertStringContainsString('class="stretched-link text-decoration-none"', $html);
    }

    public function test_heading_level_is_configurable_and_clamped(): void
    {
        $doctor = $this->createDoctor();

        $this->assertStringContainsString('<h4 class="h6 mb-1">', $this->render($doctor, ':heading-level="4"'));
        $this->assertStringContainsString('<h2 class="h6 mb-1">', $this->render($doctor, ':heading-level="1"'));
        $this->assertStringContainsString('<h3 class="h6 mb-1">', $this->render($doctor));
    }

    public function test_doctor_without_photo_uses_the_local_placeholder(): void
    {
        $doctor = $this->createDoctor();

        $html = $this->render($doctor);

        $this->assertStringContainsString('images/placeholders/doctor.svg', $html);
        $this->assertStringContainsString('alt="Foto '.e($doctor->display_name).'"', $html);
    }

    public function test_doctor_with_photo_uses_the_thumbnail_and_keeps_a_fallback(): void
    {
        $doctor = $this->createDoctor();
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(600, 600, 'foto.jpg'), 'photo', $doctor, ['alt_text' => 'Foto contoh dokter']);

        $html = $this->render($doctor);

        $this->assertStringContainsString('/thumbs/', $html);
        $this->assertStringContainsString('data-fallback="', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_clinic_name_is_shown_only_on_request(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Uji Kartu']);
        $doctor = $this->createDoctor([], $clinic);

        $this->assertStringNotContainsString('Poliklinik Uji Kartu', $this->render($doctor));
        $this->assertStringContainsString('Poliklinik Uji Kartu', $this->render($doctor, ':show-clinic="true"'));
    }
}
