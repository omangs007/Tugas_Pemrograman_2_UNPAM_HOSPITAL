<?php

namespace Database\Seeders;

use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo kategori kamar rawat inap (13 §8.1, F4B-R36). Hanya `local`/`testing`; idempoten per slug.
 * `availability_updated_at` = sekarang dikurangi 2 jam dan `availability_updated_by` = Petugas Pendaftaran Demo.
 * Nama kategori adalah kebutuhan prototipe, bukan klasifikasi BPJS yang sudah diverifikasi (BR-116).
 */
class RoomCategorySeeder extends Seeder
{
    /** Akun yang tercatat sebagai pembaruh ketersediaan (DemoUserSeeder::ACCOUNTS, Petugas Pendaftaran Demo). */
    public const UPDATER_EMAIL = 'pendaftaran@unpamhospital.test';

    /**
     * 13 §8.1 disalin persis. `sort_order` = nomor urut 1…4, `is_active` = true. `label` = teks besar pada gambar contoh.
     * Kelas 2 sengaja `beds_available = 0` agar badge "Penuh" teruji; CHECK `chk_room_categories_beds` terpenuhi.
     *
     * @var list<array{name: string, slug: string, label: string, short: string, amenities: list<string>, capacity: int, available: int, rate: int, note: string}>
     */
    public const ROOMS = [
        [
            'name' => 'Kamar BPJS Kelas 1',
            'slug' => 'bpjs-kelas-1',
            'label' => 'KELAS 1',
            'short' => 'Kamar rawat inap untuk dua pasien per kamar dengan kamar mandi dalam (contoh).',
            'amenities' => ['2 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Televisi', 'Lemari pasien', 'Kursi pendamping'],
            'capacity' => 12,
            'available' => 5,
            'rate' => 400000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.',
        ],
        [
            'name' => 'Kamar BPJS Kelas 2',
            'slug' => 'bpjs-kelas-2',
            'label' => 'KELAS 2',
            'short' => 'Kamar rawat inap untuk tiga sampai empat pasien per kamar dengan kamar mandi dalam (contoh).',
            'amenities' => ['3–4 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Lemari pasien', 'Kursi pendamping'],
            'capacity' => 16,
            'available' => 0,
            'rate' => 250000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.',
        ],
        [
            'name' => 'Kamar BPJS Kelas 3',
            'slug' => 'bpjs-kelas-3',
            'label' => 'KELAS 3',
            'short' => 'Kamar rawat inap untuk empat sampai enam pasien per kamar dengan kamar mandi bersama (contoh).',
            'amenities' => ['4–6 tempat tidur per kamar', 'Kamar mandi bersama', 'Kipas/ventilasi', 'Lemari pasien'],
            'capacity' => 24,
            'available' => 9,
            'rate' => 150000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.',
        ],
        [
            'name' => 'Kamar VIP/Pribadi',
            'slug' => 'vip',
            'label' => 'VIP',
            'short' => 'Kamar rawat inap pribadi untuk satu pasien dengan sofa pendamping (contoh).',
            'amenities' => ['1 tempat tidur', 'Kamar mandi dalam', 'AC', 'Televisi', 'Sofa pendamping', 'Lemari es kecil', 'Meja kerja'],
            'capacity' => 6,
            'available' => 2,
            'rate' => 1200000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi.',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('RoomCategorySeeder DILARANG di APP_ENV='.app()->environment().'. Seeder kamar contoh hanya untuk data demo.');
        }

        $updater = User::query()->where('email', self::UPDATER_EMAIL)->first();

        if ($updater === null) {
            throw new RuntimeException('Akun Petugas Pendaftaran Demo belum ada. Jalankan DemoUserSeeder lebih dulu.');
        }

        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::ROOMS as $index => $row) {
            if (RoomCategory::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Kategori kamar {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $room = RoomCategory::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'short_description' => $row['short'],
                'description' => self::description($row['name']),
                'amenities' => $row['amenities'],
                'bed_capacity' => $row['capacity'],
                'beds_available' => $row['available'],
                'sample_rate' => $row['rate'],
                'rate_unit' => 'per malam',
                'rate_note' => $row['note'],
                'is_active' => true,
                'sort_order' => $index + 1,
                'availability_updated_at' => now()->subHours(2),
                'availability_updated_by' => $updater->id,
                'meta_title' => null,
                'meta_description' => null,
            ]);

            $this->attach($media, $generator, 'cover', $row['label'], $room, 'Foto contoh '.$row['name']);

            foreach ([1, 2] as $number) {
                $this->attach($media, $generator, 'gallery', $row['label'].' '.$number, $room, "Foto contoh {$row['name']} {$number}");
            }
        }
    }

    /**
     * Satu paragraf HTML netral yang berakhiran "(deskripsi contoh)"; disanitasi trait SanitizesRichText saat disimpan.
     */
    public static function description(string $name): string
    {
        return '<p>'.e($name.' adalah kategori kamar rawat inap contoh untuk prototipe UNPAM HOSPITAL. Nama kategori, fasilitas, dan tarif dapat berubah dan perlu ditinjau sebelum dipakai (deskripsi contoh)').'</p>';
    }

    /**
     * Membuat satu gambar contoh 1200x800 lewat GD lalu menyimpannya lewat MediaService; berkas sementara selalu dihapus.
     */
    private function attach(MediaService $media, PlaceholderImageGenerator $generator, string $collection, string $label, RoomCategory $owner, string $alt): void
    {
        $file = $generator->make($collection, $label, 1200, 800);

        try {
            $media->store($file, $collection, $owner, ['alt_text' => $alt]);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
