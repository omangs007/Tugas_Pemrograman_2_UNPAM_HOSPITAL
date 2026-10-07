<?php

namespace Tests\Feature\Seeding;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\Facility;
use App\Models\Media;
use App\Models\RoomCategory;
use Database\Seeders\ContactMessageSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\RoomCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder demo fase 4b (13 §8.1, §8.2, §10.4, F4B-R36): 4 kategori kamar, 10 fasilitas, 5 pesan kontak, dan 26 gambar.
 * Prasyarat hanya akun demo (tanpa data master), agar tes cepat.
 */
class FacilitySeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-06 07:00:00');
    }

    private function seedAll(): void
    {
        $this->seed(DemoUserSeeder::class);
        $this->seed(RoomCategorySeeder::class);
        $this->seed(FacilitySeeder::class);
        $this->seed(ContactMessageSeeder::class);
    }

    public function test_counts_match_the_reference_numbers(): void
    {
        $this->seedAll();

        $this->assertSame(4, RoomCategory::query()->count());
        $this->assertSame(10, Facility::query()->count());
        $this->assertSame(5, ContactMessage::query()->count());
        $this->assertSame(26, Media::query()->count());
        $this->assertSame(14, Media::query()->where('model_type', (new Facility)->getMorphClass())->count());
        $this->assertSame(12, Media::query()->where('model_type', (new RoomCategory)->getMorphClass())->count());
    }

    public function test_room_categories_use_the_documented_values_and_order(): void
    {
        $this->seedAll();

        $rooms = RoomCategory::query()->with('availabilityUpdater')->ordered()->get();

        $this->assertSame(
            [
                ['Kamar BPJS Kelas 1', 'bpjs-kelas-1', 12, 5, '400000.00', 'per malam', 1],
                ['Kamar BPJS Kelas 2', 'bpjs-kelas-2', 16, 0, '250000.00', 'per malam', 2],
                ['Kamar BPJS Kelas 3', 'bpjs-kelas-3', 24, 9, '150000.00', 'per malam', 3],
                ['Kamar VIP/Pribadi', 'vip', 6, 2, '1200000.00', 'per malam', 4],
            ],
            $rooms->map(fn (RoomCategory $room): array => [
                $room->name,
                $room->slug,
                $room->bed_capacity,
                $room->beds_available,
                (string) $room->sample_rate,
                $room->rate_unit,
                $room->sort_order,
            ])->all(),
        );

        $this->assertSame(58, $rooms->sum('bed_capacity'));
        $this->assertSame(16, $rooms->sum('beds_available'));
        $this->assertTrue($rooms->every(fn (RoomCategory $room): bool => $room->is_active));
        $this->assertSame([], DB::select('SELECT id FROM room_categories WHERE beds_available > bed_capacity'));
    }

    public function test_room_amenities_and_rate_notes_are_exact(): void
    {
        $this->seedAll();

        $rooms = RoomCategory::query()->get()->keyBy('slug');

        $this->assertSame(['2 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Televisi', 'Lemari pasien', 'Kursi pendamping'], $rooms['bpjs-kelas-1']->amenities);
        $this->assertSame(['3–4 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Lemari pasien', 'Kursi pendamping'], $rooms['bpjs-kelas-2']->amenities);
        $this->assertSame(['4–6 tempat tidur per kamar', 'Kamar mandi bersama', 'Kipas/ventilasi', 'Lemari pasien'], $rooms['bpjs-kelas-3']->amenities);
        $this->assertSame(['1 tempat tidur', 'Kamar mandi dalam', 'AC', 'Televisi', 'Sofa pendamping', 'Lemari es kecil', 'Meja kerja'], $rooms['vip']->amenities);

        foreach (['bpjs-kelas-1', 'bpjs-kelas-2', 'bpjs-kelas-3'] as $slug) {
            $this->assertSame('Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.', $rooms[$slug]->rate_note, $slug);
        }

        $this->assertSame('Tarif contoh untuk prototipe; bukan tarif resmi.', $rooms['vip']->rate_note);

        foreach ($rooms as $room) {
            foreach ($room->amenities as $amenity) {
                $this->assertGreaterThanOrEqual(2, mb_strlen($amenity));
                $this->assertLessThanOrEqual(80, mb_strlen($amenity));
            }

            $this->assertLessThanOrEqual(255, mb_strlen($room->short_description));
            $this->assertStringEndsWith('(contoh).', $room->short_description, $room->slug);
        }
    }

    public function test_room_availability_is_two_hours_old_and_attributed_to_the_registration_officer(): void
    {
        $this->seedAll();

        $rooms = RoomCategory::query()->with('availabilityUpdater')->ordered()->get();

        foreach ($rooms as $room) {
            $this->assertTrue($room->availability_updated_at->equalTo(now()->subHours(2)), $room->slug);
            $this->assertSame('pendaftaran@unpamhospital.test', $room->availabilityUpdater->email, $room->slug);
            $this->assertSame('Petugas Pendaftaran Demo', $room->availabilityUpdater->name, $room->slug);
            $this->assertFalse($room->is_availability_stale, $room->slug);
        }

        $this->assertSame('5 dari 12 tempat tidur tersedia', $rooms[0]->occupancy_label);
        $this->assertTrue($rooms[1]->is_full);
        $this->assertFalse($rooms[0]->is_full);
        $this->assertStringContainsString('pukul 05.00 WIB', $rooms[0]->availability_updated_label);
        $this->assertSame('Rp 400.000 per malam', $rooms[0]->sample_rate_label);
        $this->assertSame('Rp 1.200.000 per malam', $rooms[3]->sample_rate_label);
    }

    public function test_room_texts_never_use_the_realtime_wording_and_descriptions_end_with_the_example_marker(): void
    {
        $this->seedAll();

        foreach (RoomCategory::query()->get() as $room) {
            $text = $room->name.' '.$room->short_description.' '.$room->description.' '.$room->rate_note.' '.implode(' ', $room->amenities);

            $this->assertStringNotContainsStringIgnoringCase('real-time', $text, $room->slug);
            $this->assertStringNotContainsStringIgnoringCase('realtime', $text, $room->slug);
            $this->assertStringEndsWith('(deskripsi contoh)', trim(strip_tags((string) $room->description)), $room->slug);
            $this->assertSame(1, substr_count((string) $room->description, '<p>'), $room->slug);
            $this->assertNull($room->meta_title);
            $this->assertNull($room->meta_description);
        }
    }

    public function test_each_room_has_one_cover_and_two_gallery_photos_with_the_documented_alt_text(): void
    {
        $this->seedAll();

        foreach (RoomCategory::query()->with('media')->ordered()->get() as $room) {
            $cover = $room->firstMedia('cover');
            $this->assertNotNull($cover, $room->slug);
            $this->assertSame('Foto contoh '.$room->name, $cover->alt_text);
            $this->assertSame([1200, 800], [$cover->width, $cover->height]);
            $this->assertSame('jpg', $cover->extension);

            $gallery = $room->mediaIn('gallery');
            $this->assertCount(2, $gallery, $room->slug);
            $this->assertSame([1, 2], $gallery->pluck('sort_order')->all());
            $this->assertSame(
                ["Foto contoh {$room->name} 1", "Foto contoh {$room->name} 2"],
                $gallery->pluck('alt_text')->all(),
            );

            foreach ($gallery as $photo) {
                $this->assertSame([1200, 800], [$photo->width, $photo->height]);
                $this->assertSame('jpg', $photo->extension);
            }
        }
    }

    public function test_facilities_use_the_documented_names_slugs_categories_and_order(): void
    {
        $this->seedAll();

        $facilities = Facility::query()->ordered()->get();

        $this->assertSame(
            [
                ['Instalasi Gawat Darurat', 'instalasi-gawat-darurat', 'medis', 'Ruang penanganan awal kondisi gawat darurat (contoh).', 1],
                ['Ruang Tindakan Minor', 'ruang-tindakan-minor', 'medis', 'Ruang untuk tindakan ringan rawat jalan (contoh).', 2],
                ['Laboratorium', 'fasilitas-laboratorium', 'penunjang', 'Ruang pemeriksaan sampel untuk penunjang diagnosis (contoh).', 3],
                ['Radiologi', 'fasilitas-radiologi', 'penunjang', 'Ruang pemeriksaan pencitraan penunjang diagnosis (contoh).', 4],
                ['Apotek', 'apotek', 'penunjang', 'Layanan penyiapan obat sesuai resep dokter (contoh).', 5],
                ['Ruang Pendaftaran dan Informasi', 'ruang-pendaftaran-dan-informasi', 'umum', 'Loket pendaftaran, check-in, dan bantuan informasi (contoh).', 6],
                ['Ruang Tunggu Pasien', 'ruang-tunggu-pasien', 'umum', 'Area tunggu dengan tempat duduk dan pendingin ruangan (contoh).', 7],
                ['Musala', 'musala', 'umum', 'Tempat ibadah untuk pasien dan pendamping (contoh).', 8],
                ['Area Parkir', 'area-parkir', 'umum', 'Area parkir kendaraan roda dua dan roda empat (contoh).', 9],
                ['Akses Ramah Difabel', 'akses-ramah-difabel', 'umum', 'Jalur landai dan toilet yang dapat diakses kursi roda (contoh).', 10],
            ],
            $facilities->map(fn (Facility $facility): array => [
                $facility->name,
                $facility->slug,
                $facility->category->value,
                $facility->short_description,
                $facility->sort_order,
            ])->all(),
        );

        $this->assertSame(['medis' => 2, 'penunjang' => 3, 'umum' => 5], $facilities->countBy(fn (Facility $facility): string => $facility->category->value)->all());
        $this->assertTrue($facilities->every(fn (Facility $facility): bool => $facility->is_active));
    }

    public function test_facility_descriptions_are_neutral_single_paragraphs_ending_with_the_example_marker(): void
    {
        $this->seedAll();

        foreach (Facility::query()->get() as $facility) {
            $this->assertStringEndsWith('(deskripsi contoh)', trim(strip_tags((string) $facility->description)), $facility->slug);
            $this->assertSame(1, substr_count((string) $facility->description, '<p>'), $facility->slug);
            $this->assertStringNotContainsString('<script', (string) $facility->description);
            $this->assertNull($facility->meta_title);
            $this->assertNull($facility->meta_description);
            $this->assertLessThanOrEqual(255, mb_strlen($facility->short_description));
        }
    }

    public function test_each_facility_has_a_cover_and_only_the_waiting_and_registration_rooms_have_two_gallery_photos(): void
    {
        $this->seedAll();

        $withGallery = ['ruang-pendaftaran-dan-informasi', 'ruang-tunggu-pasien'];

        foreach (Facility::query()->with('media')->ordered()->get() as $facility) {
            $cover = $facility->firstMedia('cover');
            $this->assertNotNull($cover, $facility->slug);
            $this->assertSame('Foto contoh '.$facility->name, $cover->alt_text);
            $this->assertSame([1200, 800], [$cover->width, $cover->height]);
            $this->assertSame('jpg', $cover->extension);

            $gallery = $facility->mediaIn('gallery');

            if (in_array($facility->slug, $withGallery, true)) {
                $this->assertCount(2, $gallery, $facility->slug);
                $this->assertSame(["Foto contoh {$facility->name} 1", "Foto contoh {$facility->name} 2"], $gallery->pluck('alt_text')->all());
                $this->assertSame([1, 2], $gallery->pluck('sort_order')->all());

                continue;
            }

            $this->assertCount(0, $gallery, $facility->slug);
        }
    }

    public function test_media_counts_per_collection_and_files_exist(): void
    {
        $this->seedAll();

        $this->assertSame(
            ['cover' => 14, 'gallery' => 12],
            Media::query()->selectRaw('collection, COUNT(*) as total')->groupBy('collection')->orderBy('collection')->pluck('total', 'collection')->map(fn ($total): int => (int) $total)->all(),
        );

        foreach (Media::query()->get() as $media) {
            Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
            Storage::disk('public')->assertExists((string) $media->thumb_path);
        }
    }

    public function test_contact_messages_use_the_documented_senders_subjects_and_statuses(): void
    {
        $this->seedAll();

        $messages = ContactMessage::query()->with('handler')->orderBy('id')->get();

        $this->assertSame(
            [
                ['Rudi Hartono', 'rudi@contoh.test', 'Pertanyaan jam operasional', 'new', null],
                ['Sinta Maharani', 'sinta@contoh.test', 'Menanyakan ketersediaan kamar VIP', 'new', null],
                ['Tono Wibisono', 'tono@contoh.test', 'Saran tampilan jadwal dokter', 'read', null],
                ['Wati Kusuma', 'wati@contoh.test', 'Perubahan data profil', 'replied', 'pendaftaran@unpamhospital.test'],
                ['Vina Oktaviana', 'vina@contoh.test', 'Pesan uji (diarsipkan)', 'archived', 'pendaftaran@unpamhospital.test'],
            ],
            $messages->map(fn (ContactMessage $message): array => [
                $message->name,
                $message->email,
                $message->subject,
                $message->status->value,
                $message->handler?->email,
            ])->all(),
        );

        $this->assertSame(
            [ContactMessageStatus::New, ContactMessageStatus::New, ContactMessageStatus::Read, ContactMessageStatus::Replied, ContactMessageStatus::Archived],
            $messages->pluck('status')->all(),
        );
        $this->assertSame(2, ContactMessage::query()->unread()->count());
    }

    public function test_contact_message_dates_phone_consent_and_ip_hash_follow_the_rules(): void
    {
        $this->seedAll();

        $messages = ContactMessage::query()->orderBy('id')->get();
        $expectedHash = hash('sha256', '127.0.0.1'.config('app.key'));

        foreach ($messages as $index => $message) {
            $number = $index + 1;

            $this->assertTrue($message->created_at->equalTo(now()->subDays(6 - $number)), "pesan {$number}");
            $this->assertTrue($message->consent_at->equalTo($message->created_at), "pesan {$number}");
            $this->assertGreaterThanOrEqual(10, mb_strlen($message->message), "pesan {$number}");
            $this->assertLessThanOrEqual(2000, mb_strlen($message->message), "pesan {$number}");
            $this->assertStringNotContainsString('<', $message->message);
        }

        $this->assertSame([null, '081234567890', null, null, null], $messages->pluck('phone')->all());

        $hashes = DB::table('contact_messages')->pluck('ip_hash')->all();
        $this->assertSame(array_fill(0, 5, $expectedHash), $hashes);
        $this->assertSame(64, strlen($expectedHash));
        $this->assertDoesNotMatchRegularExpression('/127\.0\.0\.1/', implode(' ', $hashes));
    }

    public function test_only_the_replied_and_archived_messages_have_handler_and_handled_at(): void
    {
        $this->seedAll();

        $messages = ContactMessage::query()->orderBy('id')->get();

        foreach ([0, 1, 2] as $index) {
            $this->assertNull($messages[$index]->handled_by, 'pesan '.($index + 1));
            $this->assertNull($messages[$index]->handled_at, 'pesan '.($index + 1));
        }

        foreach ([3, 4] as $index) {
            $this->assertNotNull($messages[$index]->handled_by, 'pesan '.($index + 1));
            $this->assertTrue($messages[$index]->handled_at->equalTo($messages[$index]->created_at->copy()->addHour()), 'pesan '.($index + 1));
        }
    }

    public function test_running_the_seeders_twice_does_not_duplicate_anything(): void
    {
        $this->seedAll();
        $this->seed(RoomCategorySeeder::class);
        $this->seed(FacilitySeeder::class);
        $this->seed(ContactMessageSeeder::class);

        $this->assertSame([4, 10, 5, 26], [
            RoomCategory::query()->count(),
            Facility::query()->count(),
            ContactMessage::query()->count(),
            Media::query()->count(),
        ]);
    }

    public function test_an_existing_slug_is_skipped_and_keeps_its_edited_values(): void
    {
        $this->seedAll();

        RoomCategory::query()->where('slug', 'vip')->firstOrFail()->update(['name' => 'Kamar VIP Baru', 'beds_available' => 1]);
        Facility::query()->where('slug', 'musala')->firstOrFail()->update(['is_active' => false]);

        $this->seed(RoomCategorySeeder::class);
        $this->seed(FacilitySeeder::class);

        $vip = RoomCategory::query()->where('slug', 'vip')->firstOrFail();
        $this->assertSame('Kamar VIP Baru', $vip->name);
        $this->assertSame(1, $vip->beds_available);
        $this->assertFalse(Facility::query()->where('slug', 'musala')->firstOrFail()->is_active);
        $this->assertSame(4, RoomCategory::query()->count());
        $this->assertSame(10, Facility::query()->count());
    }

    public function test_a_message_with_the_same_email_and_subject_is_not_recreated(): void
    {
        $this->seedAll();

        ContactMessage::query()->where('email', 'rudi@contoh.test')->firstOrFail()->forceFill(['status' => ContactMessageStatus::Archived->value])->save();

        $this->seed(ContactMessageSeeder::class);

        $this->assertSame(5, ContactMessage::query()->count());
        $this->assertSame(ContactMessageStatus::Archived, ContactMessage::query()->where('email', 'rudi@contoh.test')->firstOrFail()->status);
    }

    /** @return array<string, array{class-string, string}> */
    public static function seeders(): array
    {
        return [
            'RoomCategorySeeder' => [RoomCategorySeeder::class, 'RoomCategorySeeder DILARANG di APP_ENV=production. Seeder kamar contoh hanya untuk data demo.'],
            'FacilitySeeder' => [FacilitySeeder::class, 'FacilitySeeder DILARANG di APP_ENV=production. Seeder fasilitas contoh hanya untuk data demo.'],
            'ContactMessageSeeder' => [ContactMessageSeeder::class, 'ContactMessageSeeder DILARANG di APP_ENV=production. Pesan contoh memuat data pribadi fiktif dan hanya untuk data demo.'],
        ];
    }

    #[DataProvider('seeders')]
    public function test_each_seeder_throws_outside_local_and_testing_and_writes_nothing(string $seeder, string $message): void
    {
        $this->seed(DemoUserSeeder::class);

        $original = $this->app['env'];
        $this->app['env'] = 'production';
        $thrown = null;

        try {
            $this->app->make($seeder)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $this->app['env'] = $original;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame($message, $thrown->getMessage());
        $this->assertSame(0, DB::table('room_categories')->count() + DB::table('facilities')->count() + DB::table('contact_messages')->count() + DB::table('media')->count());
    }

    #[DataProvider('seedersNeedingTheOfficer')]
    public function test_seeders_that_attribute_rows_require_the_demo_registration_officer(string $seeder): void
    {
        $thrown = null;

        try {
            $this->app->make($seeder)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('Jalankan DemoUserSeeder lebih dulu.', $thrown->getMessage());
        $this->assertSame(0, RoomCategory::query()->count() + ContactMessage::query()->count() + Media::query()->count());
    }

    /** @return array<string, array{class-string}> */
    public static function seedersNeedingTheOfficer(): array
    {
        return [
            'RoomCategorySeeder' => [RoomCategorySeeder::class],
            'ContactMessageSeeder' => [ContactMessageSeeder::class],
        ];
    }

    public function test_the_facility_seeder_does_not_need_any_account(): void
    {
        $this->seed(FacilitySeeder::class);

        $this->assertSame(10, Facility::query()->count());
        $this->assertSame(14, Media::query()->count());
    }

    public function test_media_prune_dry_run_reports_no_orphans_after_seeding(): void
    {
        $this->seedAll();

        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 0')
            ->expectsOutputToContain('Berkas yatim: 0')
            ->assertExitCode(0);
    }

    public function test_the_database_seeder_runs_all_three_through_the_demo_seeder_but_never_calls_them_directly(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        foreach (['RoomCategorySeeder', 'FacilitySeeder', 'ContactMessageSeeder'] as $class) {
            $this->assertStringNotContainsString($class, $source);
        }

        $this->seed(DatabaseSeeder::class);

        $this->assertSame([4, 10, 5, 83], [
            RoomCategory::query()->count(),
            Facility::query()->count(),
            ContactMessage::query()->count(),
            Media::query()->count(),
        ]);
        $this->assertSame(3, DB::table('users')->count());
    }
}
