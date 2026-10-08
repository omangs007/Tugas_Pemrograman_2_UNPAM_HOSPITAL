<?php

namespace Tests\Feature\Seeding;

use App\Enums\ClinicType;
use App\Enums\DoctorStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Clinic;
use App\Models\ContactMessage;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\PatientProfile;
use App\Models\RoomCategory;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SEED-01 (bagian master data fase 2 dan invarian I15 sampai I17 fase 5), SEED-03. Kueri K-5/K-23 dari 09 §12; invarian lengkap
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
        $this->assertSame(36, Media::query()->where('collection', 'cover')->count()); // 16 layanan + 6 artikel + 10 fasilitas + 4 kamar
        $this->assertSame(12, Media::query()->where('collection', 'gallery')->count()); // 4 fasilitas + 8 kamar (fase 4b)
        $this->assertSame(83, Media::query()->count()); // 32 master + 25 konten (4a) + 26 fasilitas dan kamar (4b)
        $this->assertSame(16, Doctor::query()->publiclyVisible()->count());
    }

    public function test_content_counts_match_the_reference_numbers(): void
    {
        $this->seedDemo();

        $this->assertSame(4, ArticleCategory::query()->count());
        $this->assertSame(8, Article::query()->count());
        $this->assertSame(7, Article::query()->published()->count());
        $this->assertSame(3, Banner::query()->count());
        $this->assertSame(3, Gallery::query()->count());
        $this->assertSame(12, Faq::query()->count());
        $this->assertSame(4, InsurancePartner::query()->count());
        $this->assertSame(6, Media::query()->where('collection', 'cover')->where('model_type', (new Article)->getMorphClass())->count());
        $this->assertSame(3, Media::query()->where('collection', 'image')->count());
        $this->assertSame(12, Media::query()->where('collection', 'photos')->count());
        $this->assertSame(4, Media::query()->where('collection', 'logo')->count());
        $this->assertSame(15, DB::table('pages')->count());
    }

    public function test_phase_4b_counts_and_availability_match_the_reference_numbers(): void
    {
        $this->seedDemo();

        $this->assertSame(4, RoomCategory::query()->count());
        $this->assertSame(10, Facility::query()->count());
        $this->assertSame(5, ContactMessage::query()->count());
        $this->assertSame(2, ContactMessage::query()->unread()->count());
        $this->assertSame(10, Media::query()->where('collection', 'cover')->where('model_type', (new Facility)->getMorphClass())->count());
        $this->assertSame(4, Media::query()->where('collection', 'gallery')->where('model_type', (new Facility)->getMorphClass())->count());
        $this->assertSame(4, Media::query()->where('collection', 'cover')->where('model_type', (new RoomCategory)->getMorphClass())->count());
        $this->assertSame(8, Media::query()->where('collection', 'gallery')->where('model_type', (new RoomCategory)->getMorphClass())->count());
        $this->assertSame(58, (int) RoomCategory::query()->sum('bed_capacity'));
        $this->assertSame(16, (int) RoomCategory::query()->sum('beds_available'));
        $this->assertSame(1, RoomCategory::query()->where('beds_available', 0)->count());
        $this->assertSame([], DB::select('SELECT id FROM room_categories WHERE beds_available > bed_capacity'));
        $this->assertSame([], DB::select('SELECT id FROM room_categories WHERE availability_updated_at IS NULL OR availability_updated_by IS NULL'));
        $this->assertSame([], DB::select("SELECT id FROM contact_messages WHERE (status IN ('replied', 'archived')) <> (handled_by IS NOT NULL)"));
        $this->assertSame([], DB::select('SELECT id FROM contact_messages WHERE CHAR_LENGTH(ip_hash) <> 64'));
    }

    /** SEED-01 fase 5: angka acuan 13 §11.2 untuk pasien berakun dan invarian I15, I16, I17 (13 §11.1) sebagai kueri. */
    public function test_phase_5_counts_and_patient_invariants_match_the_reference_numbers(): void
    {
        $this->seedDemo();

        $this->assertSame(10, User::query()->count());
        $this->assertSame(3, User::query()->where('account_type', 'staff')->count());
        $this->assertSame(7, User::query()->where('account_type', 'patient')->count());
        $this->assertSame(10, User::query()->where('email', 'like', '%.test')->count());
        $this->assertSame(3, DB::table('model_has_roles')->count(), 'Akun pasien tidak memegang peran.');
        $this->assertSame(7, PatientProfile::query()->count());
        $this->assertSame(7, PatientProfile::query()->where('registration_channel', 'online')->whereNotNull('user_id')->count());
        $this->assertSame(0, PatientProfile::query()->where('registration_channel', 'loket')->count(), 'Walk-in menyusul di fase 6/7.');
        $this->assertSame(3, PatientProfile::query()->whereNotNull('bpjs_number_last4')->count());
        $this->assertSame('Anisa Rahmawati', PatientProfile::query()->where('email', 'pasien@unpamhospital.test')->value('full_name'));

        // I15, I16, I17 (13 §11.1); I15 diperketat dengan IS NULL karena kolom `patient_number` NULLable.
        $this->assertSame([], DB::select("SELECT id FROM patient_profiles WHERE patient_number IS NULL OR patient_number <> CONCAT('P', LPAD(id, 6, '0'))"));
        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.email IS NULL OR p.email <> u.email'));
        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.is_active <> u.is_active'));
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

        // Gambar konten (ContentSeeder) punya ukuran, format, dan alt sendiri; lihat ContentSeederTest.
        $masterTypes = [(new Doctor)->getMorphClass(), (new Clinic)->getMorphClass()];

        foreach (Media::query()->whereIn('model_type', $masterTypes)->get() as $media) {
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

        $this->assertSame([13, 17, 18, 20, 83], [
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

        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'PatientSeeder', 'ContactMessageSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--no-interaction' => true])->assertExitCode(0);
        }

        $this->assertSame([10, 7], [User::query()->count(), PatientProfile::query()->count()]);

        $this->assertSame([13, 17, 18, 20, 83], [
            Specialty::query()->count(),
            Clinic::query()->count(),
            Doctor::query()->count(),
            DB::table('doctor_specialties')->count(),
            Media::query()->count(),
        ]);
    }
}
