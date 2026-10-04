<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

class DoctorSeeder extends Seeder
{
    /**
     * 13 §6, 18 dokter fiktif (disalin persis). `slug` = Str::slug(name). `extra` = spesialisasi tambahan
     * (2 baris sekunder: #8 dan #13 ⇒ 20 baris `doctor_specialties`). `photo` false untuk #15 dan #16.
     * #17 `inactive`; #18 `archived` dengan `archived_at` = hari ini − 30 hari.
     *
     * @var list<array{name: string, slug: string, prefix: string, suffix: ?string, gender: string, primary: string, extra: list<string>, clinic: string, status: string, photo: bool}>
     */
    public const ROWS = [
        ['name' => 'Raka Pradipta Wibowo', 'slug' => 'raka-pradipta-wibowo', 'prefix' => 'dr.', 'suffix' => 'Sp.U', 'gender' => 'L', 'primary' => 'Urologi', 'extra' => [], 'clinic' => 'Poliklinik Urologi', 'status' => 'active', 'photo' => true],
        ['name' => 'Sekar Ayuningtyas', 'slug' => 'sekar-ayuningtyas', 'prefix' => 'dr.', 'suffix' => 'Sp.PD', 'gender' => 'P', 'primary' => 'Penyakit Dalam', 'extra' => [], 'clinic' => 'Poliklinik Penyakit Dalam', 'status' => 'active', 'photo' => true],
        ['name' => 'Bagas Setiawan Nugroho', 'slug' => 'bagas-setiawan-nugroho', 'prefix' => 'dr.', 'suffix' => 'Sp.PD', 'gender' => 'L', 'primary' => 'Penyakit Dalam', 'extra' => [], 'clinic' => 'Poliklinik Penyakit Dalam', 'status' => 'active', 'photo' => true],
        ['name' => 'Laras Wulandari', 'slug' => 'laras-wulandari', 'prefix' => 'dr.', 'suffix' => 'Sp.A', 'gender' => 'P', 'primary' => 'Anak', 'extra' => [], 'clinic' => 'Poliklinik Anak', 'status' => 'active', 'photo' => true],
        ['name' => 'Dimas Aryaputra', 'slug' => 'dimas-aryaputra', 'prefix' => 'dr.', 'suffix' => 'Sp.A', 'gender' => 'L', 'primary' => 'Anak', 'extra' => [], 'clinic' => 'Poliklinik Anak', 'status' => 'active', 'photo' => true],
        ['name' => 'Nadira Kusumawardani', 'slug' => 'nadira-kusumawardani', 'prefix' => 'dr.', 'suffix' => 'Sp.OG', 'gender' => 'P', 'primary' => 'Obstetri dan Ginekologi', 'extra' => [], 'clinic' => 'Poliklinik Kandungan', 'status' => 'active', 'photo' => true],
        ['name' => 'Hendra Mahardika', 'slug' => 'hendra-mahardika', 'prefix' => 'dr.', 'suffix' => 'Sp.B', 'gender' => 'L', 'primary' => 'Bedah Umum', 'extra' => [], 'clinic' => 'Poliklinik Bedah Umum', 'status' => 'active', 'photo' => true],
        ['name' => 'Maya Kartika Sari', 'slug' => 'maya-kartika-sari', 'prefix' => 'dr.', 'suffix' => 'Sp.JP', 'gender' => 'P', 'primary' => 'Jantung', 'extra' => ['Penyakit Dalam'], 'clinic' => 'Poliklinik Jantung', 'status' => 'active', 'photo' => true],
        ['name' => 'Fajar Ramadhan Lubis', 'slug' => 'fajar-ramadhan-lubis', 'prefix' => 'dr.', 'suffix' => 'Sp.N', 'gender' => 'L', 'primary' => 'Saraf', 'extra' => [], 'clinic' => 'Poliklinik Saraf', 'status' => 'active', 'photo' => true],
        ['name' => 'Intan Permatasari', 'slug' => 'intan-permatasari', 'prefix' => 'dr.', 'suffix' => 'Sp.M', 'gender' => 'P', 'primary' => 'Mata', 'extra' => [], 'clinic' => 'Poliklinik Mata', 'status' => 'active', 'photo' => true],
        ['name' => 'Yoga Prasetya', 'slug' => 'yoga-prasetya', 'prefix' => 'dr.', 'suffix' => 'Sp.THT-KL', 'gender' => 'L', 'primary' => 'THT', 'extra' => [], 'clinic' => 'Poliklinik THT', 'status' => 'active', 'photo' => true],
        ['name' => 'Citra Anindya Putri', 'slug' => 'citra-anindya-putri', 'prefix' => 'dr.', 'suffix' => 'Sp.DV', 'gender' => 'P', 'primary' => 'Kulit dan Kelamin', 'extra' => [], 'clinic' => 'Poliklinik Kulit dan Kelamin', 'status' => 'active', 'photo' => true],
        ['name' => 'Arif Budiman Santoso', 'slug' => 'arif-budiman-santoso', 'prefix' => 'dr.', 'suffix' => 'Sp.OT', 'gender' => 'L', 'primary' => 'Ortopedi', 'extra' => ['Bedah Umum'], 'clinic' => 'Poliklinik Ortopedi', 'status' => 'active', 'photo' => true],
        ['name' => 'Rani Oktaviani', 'slug' => 'rani-oktaviani', 'prefix' => 'dr.', 'suffix' => null, 'gender' => 'P', 'primary' => 'Dokter Umum', 'extra' => [], 'clinic' => 'Poliklinik Umum', 'status' => 'active', 'photo' => true],
        ['name' => 'Teguh Wicaksono', 'slug' => 'teguh-wicaksono', 'prefix' => 'dr.', 'suffix' => null, 'gender' => 'L', 'primary' => 'Dokter Umum', 'extra' => [], 'clinic' => 'Poliklinik Umum', 'status' => 'active', 'photo' => false],
        ['name' => 'Ayu Larasati Hakim', 'slug' => 'ayu-larasati-hakim', 'prefix' => 'drg.', 'suffix' => null, 'gender' => 'P', 'primary' => 'Dokter Gigi', 'extra' => [], 'clinic' => 'Poliklinik Gigi', 'status' => 'active', 'photo' => false],
        ['name' => 'Eko Susanto', 'slug' => 'eko-susanto', 'prefix' => 'dr.', 'suffix' => 'Sp.B', 'gender' => 'L', 'primary' => 'Bedah Umum', 'extra' => [], 'clinic' => 'Poliklinik Bedah Umum', 'status' => 'inactive', 'photo' => true],
        ['name' => 'Lilis Marlina', 'slug' => 'lilis-marlina', 'prefix' => 'dr.', 'suffix' => 'Sp.M', 'gender' => 'P', 'primary' => 'Mata', 'extra' => [], 'clinic' => 'Poliklinik Mata', 'status' => 'archived', 'photo' => true],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DoctorSeeder DILARANG di APP_ENV='.app()->environment().'. Seeder master hanya untuk data demo.');
        }

        $clinics = Clinic::query()->get()->keyBy('name');
        $specialties = Specialty::query()->get()->keyBy('name');
        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::ROWS as $index => $row) {
            if (Doctor::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Dokter {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $clinic = $clinics->get($row['clinic']) ?? throw new RuntimeException("Poliklinik {$row['clinic']} belum ada. Jalankan ClinicSeeder lebih dulu.");
            $primary = $specialties->get($row['primary']) ?? throw new RuntimeException("Spesialisasi {$row['primary']} belum ada. Jalankan SpecialtySeeder lebih dulu.");

            // Semua kunci diisi (mode ketat): display_name membaca title_prefix/name/title_suffix sebelum disimpan.
            $doctor = new Doctor([
                'clinic_id' => $clinic->id,
                'name' => $row['name'],
                'slug' => $row['slug'],
                'title_prefix' => $row['prefix'],
                'title_suffix' => $row['suffix'],
                'gender' => $row['gender'],
                'short_bio' => null,
                'bio' => null,
                'status' => $row['status'],
                'sort_order' => $index + 1,
                'meta_title' => null,
                'meta_description' => null,
            ]);
            $displayName = $doctor->display_name;
            $doctor->short_bio = "{$displayName} melayani konsultasi {$row['primary']} di UNPAM HOSPITAL. (Profil contoh).";
            $doctor->bio = "{$displayName} adalah dokter contoh yang melayani konsultasi {$row['primary']} di UNPAM HOSPITAL. Profil ini dibuat untuk keperluan prototipe dan tidak menggambarkan orang sungguhan.\n\nInformasi pendidikan, pengalaman, dan jadwal praktik pada profil ini belum diisi. Data akan dilengkapi pada tahap pengembangan berikutnya.";
            $doctor->save();

            if ($row['status'] === 'archived') {
                $doctor->forceFill(['archived_at' => now()->subDays(30)])->save();
            }

            $pivot = [$primary->id => ['is_primary' => true]];

            foreach ($row['extra'] as $extraName) {
                $extra = $specialties->get($extraName) ?? throw new RuntimeException("Spesialisasi {$extraName} belum ada.");
                $pivot[$extra->id] = ['is_primary' => false];
            }

            $doctor->specialties()->attach($pivot);

            if ($row['photo']) {
                $file = $generator->make('photo', self::initials($row['name']), 600, 600);

                try {
                    $media->store($file, 'photo', $doctor, ['alt_text' => 'Foto contoh '.$displayName]);
                } finally {
                    @unlink($file->getPathname());
                }
            }
        }
    }

    /** Dua huruf pertama dari dua kata pertama nama, mis. "Raka Pradipta Wibowo" ⇒ "RP". */
    private static function initials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', $name)));

        return strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }
}
