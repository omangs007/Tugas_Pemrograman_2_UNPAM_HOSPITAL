<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo fasilitas (13 §8.2, F4B-R36). Hanya `local`/`testing`; idempoten per slug. Deskripsi netral berakhiran
 * "(deskripsi contoh)" tanpa klaim sertifikasi atau peralatan merek tertentu.
 */
class FacilitySeeder extends Seeder
{
    /**
     * 13 §8.2 disalin persis. `sort_order` = nomor urut 1…10, `is_active` = true. `label` = teks besar pada gambar contoh;
     * `gallery` true hanya untuk #6 dan #7 (masing-masing dua foto galeri).
     *
     * @var list<array{name: string, slug: string, category: string, short: string, label: string, gallery: bool}>
     */
    public const FACILITIES = [
        ['name' => 'Instalasi Gawat Darurat', 'slug' => 'instalasi-gawat-darurat', 'category' => 'medis', 'short' => 'Ruang penanganan awal kondisi gawat darurat (contoh).', 'label' => 'IGD', 'gallery' => false],
        ['name' => 'Ruang Tindakan Minor', 'slug' => 'ruang-tindakan-minor', 'category' => 'medis', 'short' => 'Ruang untuk tindakan ringan rawat jalan (contoh).', 'label' => 'TINDAKAN', 'gallery' => false],
        ['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium', 'category' => 'penunjang', 'short' => 'Ruang pemeriksaan sampel untuk penunjang diagnosis (contoh).', 'label' => 'LAB', 'gallery' => false],
        ['name' => 'Radiologi', 'slug' => 'fasilitas-radiologi', 'category' => 'penunjang', 'short' => 'Ruang pemeriksaan pencitraan penunjang diagnosis (contoh).', 'label' => 'RADIOLOGI', 'gallery' => false],
        ['name' => 'Apotek', 'slug' => 'apotek', 'category' => 'penunjang', 'short' => 'Layanan penyiapan obat sesuai resep dokter (contoh).', 'label' => 'APOTEK', 'gallery' => false],
        ['name' => 'Ruang Pendaftaran dan Informasi', 'slug' => 'ruang-pendaftaran-dan-informasi', 'category' => 'umum', 'short' => 'Loket pendaftaran, check-in, dan bantuan informasi (contoh).', 'label' => 'PENDAFTARAN', 'gallery' => true],
        ['name' => 'Ruang Tunggu Pasien', 'slug' => 'ruang-tunggu-pasien', 'category' => 'umum', 'short' => 'Area tunggu dengan tempat duduk dan pendingin ruangan (contoh).', 'label' => 'RUANG TUNGGU', 'gallery' => true],
        ['name' => 'Musala', 'slug' => 'musala', 'category' => 'umum', 'short' => 'Tempat ibadah untuk pasien dan pendamping (contoh).', 'label' => 'MUSALA', 'gallery' => false],
        ['name' => 'Area Parkir', 'slug' => 'area-parkir', 'category' => 'umum', 'short' => 'Area parkir kendaraan roda dua dan roda empat (contoh).', 'label' => 'PARKIR', 'gallery' => false],
        ['name' => 'Akses Ramah Difabel', 'slug' => 'akses-ramah-difabel', 'category' => 'umum', 'short' => 'Jalur landai dan toilet yang dapat diakses kursi roda (contoh).', 'label' => 'AKSES', 'gallery' => false],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('FacilitySeeder DILARANG di APP_ENV='.app()->environment().'. Seeder fasilitas contoh hanya untuk data demo.');
        }

        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::FACILITIES as $index => $row) {
            if (Facility::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Fasilitas {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $facility = Facility::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'category' => $row['category'],
                'short_description' => $row['short'],
                'description' => self::description($row['name']),
                'is_active' => true,
                'sort_order' => $index + 1,
                'meta_title' => null,
                'meta_description' => null,
            ]);

            $this->attach($media, $generator, 'cover', $row['label'], $facility, 'Foto contoh '.$row['name']);

            if ($row['gallery']) {
                foreach ([1, 2] as $number) {
                    $this->attach($media, $generator, 'gallery', $row['label'].' '.$number, $facility, "Foto contoh {$row['name']} {$number}");
                }
            }
        }
    }

    /**
     * Satu paragraf HTML netral yang berakhiran "(deskripsi contoh)"; disanitasi trait SanitizesRichText saat disimpan.
     */
    public static function description(string $name): string
    {
        return '<p>'.e($name.' adalah fasilitas contoh di UNPAM HOSPITAL untuk keperluan prototipe. Keterangan pada halaman ini bersifat umum dan perlu ditinjau sebelum dipakai (deskripsi contoh)').'</p>';
    }

    /**
     * Membuat satu gambar contoh 1200x800 lewat GD lalu menyimpannya lewat MediaService; berkas sementara selalu dihapus.
     */
    private function attach(MediaService $media, PlaceholderImageGenerator $generator, string $collection, string $label, Facility $owner, string $alt): void
    {
        $file = $generator->make($collection, $label, 1200, 800);

        try {
            $media->store($file, $collection, $owner, ['alt_text' => $alt]);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
