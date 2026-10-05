<?php

namespace Tests\Feature\Seeding;

use App\Enums\ClinicType;
use App\Enums\DoctorStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SEED-01 (bagian master data, fase 2), SEED-03. Kueri K-5/K-23 dari 09 §12; invarian lengkap
 * (`scripts/sql/seed-invariants.sql`) menyusul di fase 9.
 */
class DemoSeederInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function seedDemo(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_master_data_counts_match_the_reference_numbers(): void
    {
        $this->seedDemo();

        $this->assertSame(13, Specialty::query()->count());
        $this->assertSame(17, Clinic::query()->count());
        $this->assertSame(13, Clinic::query()->where('type', ClinicType::Poliklinik->value)->where('is_bookable', true)->count());
        $this->assertSame(4, Clinic::query()->where('type', ClinicType::Penunjang->value)->where('is_bookable', false)->whereNull('specialty_id')->count());
        $this->assertSame(18, Doctor::query()->count());
        $this->assertSame(16, Doctor::query()->where('status', DoctorStatus::Active->value)->count());
        $this->assertSame(1, Doctor::query()->where('status', DoctorStatus::Inactive->value)->count());
        $this->assertSame(1, Doctor::query()->where('status', DoctorStatus::Archived->value)->count());
        $this->assertSame(20, DB::table('doctor_specialties')->count());
        $this->assertSame(18, DB::table('doctor_specialties')->where('is_primary', true)->count());
        $this->assertSame(16, Media::query()->where('collection', 'photo')->count());
        $this->assertSame(16, Media::query()->where('collection', 'cover')->count());
        $this->assertSame(32, Media::query()->count());
        $this->assertSame(16, Doctor::query()->publiclyVisible()->count());
    }

    public function test_every_doctor_has_exactly_one_primary_specialty_and_archive_state_is_consistent(): void
    {
        $this->seedDemo();

        // K-5 dan K-23 (09 §12).
        $this->assertSame([], DB::select('SELECT d.id FROM doctors d LEFT JOIN doctor_specialties ds ON ds.doctor_id = d.id AND ds.is_primary = 1 GROUP BY d.id HAVING COUNT(ds.doctor_id) <> 1'));
        $this->assertSame([], DB::select("SELECT id FROM doctors WHERE (status = 'archived') <> (archived_at IS NOT NULL)"));

        $lilis = Doctor::query()->where('slug', 'lilis-marlina')->firstOrFail();
        $this->assertTrue($lilis->archived_at->isSameDay(now()->subDays(30)));
    }

    public function test_penyakit_dalam_filter_returns_sekar_bagas_and_maya(): void
    {
        $this->seedDemo();

        $this->assertEqualsCanonicalizing(
            ['sekar-ayuningtyas', 'bagas-setiawan-nugroho', 'maya-kartika-sari'],
            Doctor::query()->inSpecialty('penyakit-dalam')->pluck('slug')->all(),
        );
    }

    public function test_doctor_names_and_short_bio_follow_the_templates(): void
    {
        $this->seedDemo();

        $laras = Doctor::query()->where('slug', 'laras-wulandari')->firstOrFail();
        $rani = Doctor::query()->where('slug', 'rani-oktaviani')->firstOrFail();
        $ayu = Doctor::query()->where('slug', 'ayu-larasati-hakim')->firstOrFail();

        $this->assertSame('dr. Laras Wulandari, Sp.A', $laras->display_name);
        $this->assertSame('dr. Rani Oktaviani', $rani->display_name);
        $this->assertSame('drg. Ayu Larasati Hakim', $ayu->display_name);
        $this->assertSame('dr. Laras Wulandari, Sp.A melayani konsultasi Anak di UNPAM HOSPITAL. (Profil contoh).', $laras->short_bio);
        $this->assertSame(2, substr_count((string) $laras->bio, "\n\n") + 1);
        $this->assertSame(1, Doctor::query()->where('slug', 'raka-pradipta-wibowo')->value('sort_order'));
        $this->assertSame(15, Doctor::query()->where('slug', 'teguh-wicaksono')->value('sort_order'));
    }

    public function test_images_are_stored_through_the_media_service_with_alt_text_and_files(): void
    {
        $this->seedDemo();

        foreach (Media::query()->get() as $media) {
            Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
            Storage::disk('public')->assertExists((string) $media->thumb_path);
            $this->assertSame('jpg', $media->extension);
            $this->assertStringStartsWith('Foto contoh ', $media->alt_text);
            $this->assertSame($media->collection === 'photo' ? [600, 600] : [1200, 800], [$media->width, $media->height]);
        }

        $raka = Doctor::query()->with('media')->where('slug', 'raka-pradipta-wibowo')->firstOrFail();
        $this->assertSame('Foto contoh dr. Raka Pradipta Wibowo, Sp.U', $raka->firstMedia('photo')->alt_text);

        $withoutPhoto = Doctor::query()->with('media')->whereIn('slug', ['teguh-wicaksono', 'ayu-larasati-hakim'])->get();
        $this->assertCount(2, $withoutPhoto);
        $withoutPhoto->each(fn (Doctor $doctor) => $this->assertNull($doctor->firstMedia('photo')));

        $gigi = Clinic::query()->with('media')->where('code', 'GG')->firstOrFail();
        $this->assertNull($gigi->firstMedia('cover'));
        $this->assertStringContainsString('images/placeholders/clinic.svg', $gigi->cover_url);
        $this->assertSame('Foto contoh Poliklinik Urologi', Clinic::query()->with('media')->where('code', 'URO')->firstOrFail()->firstMedia('cover')->alt_text);
    }

    public function test_media_prune_dry_run_reports_no_orphans_after_seeding(): void
    {
        $this->seedDemo();

        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 0')
            ->expectsOutputToContain('Berkas yatim: 0')
            ->assertExitCode(0);
    }

    public function test_seeded_clinic_descriptions_are_sanitized_html_with_no_seo_overrides(): void
    {
        $this->seedDemo();

        foreach (Clinic::query()->get() as $clinic) {
            $this->assertStringContainsString('<p>', (string) $clinic->description, $clinic->code);
            $this->assertStringContainsString('<ul>', (string) $clinic->description, $clinic->code);
            $this->assertStringNotContainsString('<script', (string) $clinic->description);
            $this->assertNull($clinic->meta_title);
            $this->assertNull($clinic->meta_description);
        }

        $this->assertStringContainsString('Jam layanan dan nomor darurat adalah contoh prototipe', (string) Clinic::query()->where('code', 'IGD')->value('description'));
    }

    public function test_demo_seeder_is_idempotent_through_its_guard(): void
    {
        $this->seedDemo();

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\DemoSeeder', '--no-interaction' => true])
            ->expectsOutputToContain('Data demo sudah ada')
            ->assertExitCode(0);

        $this->assertSame([13, 17, 18, 20, 32], [
            Specialty::query()->count(),
            Clinic::query()->count(),
            Doctor::query()->count(),
            DB::table('doctor_specialties')->count(),
            Media::query()->count(),
        ]);
    }

    public function test_each_master_seeder_skips_rows_that_already_exist(): void
    {
        $this->seedDemo();

        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--no-interaction' => true])->assertExitCode(0);
        }

        $this->assertSame([13, 17, 18, 20, 32], [
            Specialty::query()->count(),
            Clinic::query()->count(),
            Doctor::query()->count(),
            DB::table('doctor_specialties')->count(),
            Media::query()->count(),
        ]);
    }
}
