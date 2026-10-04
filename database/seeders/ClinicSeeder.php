<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Specialty;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

class ClinicSeeder extends Seeder
{
    /**
     * 13 §5: kolom name/slug/code/type/bookable/specialty/location disalin persis dari tabel.
     * `focus`, `items`, dan `note` adalah bahan deskripsi (ditulis di plan ini): `short_description`
     * dan `description` dirangkai oleh `shortDescription()`/`description()`.
     * `cover` false hanya untuk GG (menguji fallback placeholder SVG).
     *
     * @var list<array{name: string, slug: string, code: string, type: string, bookable: bool, specialty: ?string, location: string, focus: string, items: list<string>, note: ?string, cover: bool}>
     */
    public const ROWS = [
        ['name' => 'Poliklinik Urologi', 'slug' => 'poliklinik-urologi', 'code' => 'URO', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Urologi', 'location' => 'Gedung A Lt. 1', 'focus' => 'keluhan saluran kemih dan sistem reproduksi pria', 'items' => ['Konsultasi keluhan saluran kemih', 'Pemeriksaan awal dan rujukan pemeriksaan penunjang', 'Edukasi kesehatan pasien'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Penyakit Dalam', 'slug' => 'poliklinik-penyakit-dalam', 'code' => 'PD', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Penyakit Dalam', 'location' => 'Gedung A Lt. 1', 'focus' => 'keluhan penyakit organ dalam pada orang dewasa', 'items' => ['Konsultasi penyakit dalam', 'Pemantauan penyakit kronis', 'Edukasi gaya hidup sehat'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Anak', 'slug' => 'poliklinik-anak', 'code' => 'ANK', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Anak', 'location' => 'Gedung A Lt. 1', 'focus' => 'kesehatan bayi, anak, dan remaja', 'items' => ['Konsultasi tumbuh kembang anak', 'Pemeriksaan keluhan anak', 'Edukasi orang tua'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Kandungan', 'slug' => 'poliklinik-kandungan', 'code' => 'OBG', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Obstetri dan Ginekologi', 'location' => 'Gedung A Lt. 2', 'focus' => 'kesehatan ibu hamil dan sistem reproduksi wanita', 'items' => ['Konsultasi kehamilan', 'Pemeriksaan kesehatan reproduksi wanita', 'Edukasi ibu dan keluarga'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Bedah Umum', 'slug' => 'poliklinik-bedah-umum', 'code' => 'BDH', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Bedah Umum', 'location' => 'Gedung A Lt. 2', 'focus' => 'keluhan yang memerlukan penilaian bedah', 'items' => ['Konsultasi sebelum tindakan bedah', 'Kontrol setelah tindakan', 'Perawatan luka sederhana'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Jantung', 'slug' => 'poliklinik-jantung', 'code' => 'JTG', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Jantung', 'location' => 'Gedung B Lt. 1', 'focus' => 'kesehatan jantung dan pembuluh darah', 'items' => ['Konsultasi keluhan jantung', 'Pemantauan tekanan darah', 'Edukasi pencegahan penyakit jantung'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Saraf', 'slug' => 'poliklinik-saraf', 'code' => 'SRF', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Saraf', 'location' => 'Gedung B Lt. 1', 'focus' => 'keluhan otak, saraf tulang belakang, dan saraf tepi', 'items' => ['Konsultasi keluhan saraf', 'Pemeriksaan awal gangguan saraf', 'Edukasi pasien dan keluarga'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Mata', 'slug' => 'poliklinik-mata', 'code' => 'MTA', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Mata', 'location' => 'Gedung B Lt. 2', 'focus' => 'kesehatan mata', 'items' => ['Konsultasi keluhan mata', 'Pemeriksaan ketajaman penglihatan', 'Edukasi kesehatan mata'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik THT', 'slug' => 'poliklinik-tht', 'code' => 'THT', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'THT', 'location' => 'Gedung B Lt. 2', 'focus' => 'kesehatan telinga, hidung, dan tenggorok', 'items' => ['Konsultasi keluhan THT', 'Pemeriksaan telinga, hidung, dan tenggorok', 'Edukasi perawatan harian'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Kulit dan Kelamin', 'slug' => 'poliklinik-kulit-dan-kelamin', 'code' => 'KK', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Kulit dan Kelamin', 'location' => 'Gedung B Lt. 2', 'focus' => 'kesehatan kulit, rambut, kuku, dan kelamin', 'items' => ['Konsultasi keluhan kulit', 'Pemeriksaan awal masalah kulit dan kelamin', 'Edukasi perawatan kulit'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Ortopedi', 'slug' => 'poliklinik-ortopedi', 'code' => 'ORT', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Ortopedi', 'location' => 'Gedung C Lt. 1', 'focus' => 'kesehatan tulang, sendi, dan otot', 'items' => ['Konsultasi keluhan tulang dan sendi', 'Pemeriksaan awal cedera', 'Edukasi pemulihan'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Umum', 'slug' => 'poliklinik-umum', 'code' => 'UMM', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Dokter Umum', 'location' => 'Gedung A Lt. 1', 'focus' => 'keluhan kesehatan umum', 'items' => ['Pemeriksaan awal keluhan umum', 'Konsultasi kesehatan', 'Rujukan ke poliklinik spesialis'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Gigi', 'slug' => 'poliklinik-gigi', 'code' => 'GG', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Dokter Gigi', 'location' => 'Gedung C Lt. 1', 'focus' => 'kesehatan gigi dan mulut', 'items' => ['Konsultasi keluhan gigi dan mulut', 'Pemeriksaan gigi', 'Edukasi kebersihan mulut'], 'note' => null, 'cover' => false],
        ['name' => 'Laboratorium', 'slug' => 'laboratorium', 'code' => 'LAB', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung C Lt. 1', 'focus' => 'pemeriksaan laboratorium untuk menunjang diagnosis dokter', 'items' => ['Pemeriksaan darah rutin', 'Pemeriksaan urine', 'Pengambilan sampel sesuai permintaan dokter'], 'note' => null, 'cover' => true],
        ['name' => 'Radiologi', 'slug' => 'radiologi', 'code' => 'RAD', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung C Lt. 1', 'focus' => 'pemeriksaan pencitraan untuk menunjang diagnosis dokter', 'items' => ['Rontgen', 'Pemeriksaan USG', 'Pembacaan hasil oleh dokter'], 'note' => null, 'cover' => true],
        ['name' => 'Farmasi', 'slug' => 'farmasi', 'code' => 'FAR', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung A Lt. 1', 'focus' => 'pelayanan obat sesuai resep dokter', 'items' => ['Penyiapan obat sesuai resep', 'Informasi cara penggunaan obat', 'Konsultasi singkat dengan apoteker'], 'note' => null, 'cover' => true],
        ['name' => 'IGD 24 Jam', 'slug' => 'igd-24-jam', 'code' => 'IGD', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung A Lt. 1 (contoh)', 'focus' => 'penanganan awal kondisi gawat darurat', 'items' => ['Penilaian awal kondisi pasien', 'Penanganan awal gawat darurat', 'Rujukan ke layanan lanjutan'], 'note' => 'Jam layanan dan nomor darurat adalah contoh prototipe.', 'cover' => true],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ClinicSeeder DILARANG di APP_ENV='.app()->environment().'. Seeder master hanya untuk data demo.');
        }

        $specialties = Specialty::query()->get()->keyBy('name');
        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::ROWS as $index => $row) {
            if (Clinic::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Layanan {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $specialty = $row['specialty'] === null ? null : $specialties->get($row['specialty']);

            if ($row['specialty'] !== null && $specialty === null) {
                throw new RuntimeException("Spesialisasi {$row['specialty']} belum ada. Jalankan SpecialtySeeder lebih dulu.");
            }

            $clinic = Clinic::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'code' => $row['code'],
                'type' => $row['type'],
                'is_bookable' => $row['bookable'],
                'specialty_id' => $specialty?->id,
                'short_description' => self::shortDescription($row),
                'description' => self::description($row),
                'location' => $row['location'],
                'is_active' => true,
                'sort_order' => $index + 1,
                'meta_title' => null,
                'meta_description' => null,
            ]);

            if ($row['cover']) {
                $file = $generator->make('cover', $row['code'], 1200, 800);

                try {
                    $media->store($file, 'cover', $clinic, ['alt_text' => 'Foto contoh '.$row['name']]);
                } finally {
                    @unlink($file->getPathname());
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function shortDescription(array $row): string
    {
        return $row['type'] === 'poliklinik'
            ? 'Konsultasi dan pemeriksaan '.$row['focus'].' (contoh).'
            : ucfirst($row['focus']).' (contoh).';
    }

    /**
     * HTML pendek `<p>` + `<ul>`; disanitasi oleh trait SanitizesRichText saat disimpan.
     *
     * @param  array<string, mixed>  $row
     */
    public static function description(array $row): string
    {
        $intro = $row['type'] === 'poliklinik'
            ? $row['name'].' melayani konsultasi dan pemeriksaan '.$row['focus'].' di UNPAM HOSPITAL.'
            : $row['name'].' menyediakan '.$row['focus'].' di UNPAM HOSPITAL.';

        $html = '<p>'.e($intro).' Keterangan pada halaman ini adalah data contoh untuk prototipe.</p><ul>';

        foreach ($row['items'] as $item) {
            $html .= '<li>'.e($item).'</li>';
        }

        $html .= '</ul>';

        if ($row['note'] !== null) {
            $html .= '<p>'.e($row['note']).'</p>';
        }

        return $html;
    }
}
