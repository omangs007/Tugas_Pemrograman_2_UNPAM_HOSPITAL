<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use RuntimeException;

class SpecialtySeeder extends Seeder
{
    /**
     * 13 §4: 13 spesialisasi, `is_active = true`, `sort_order` = nomor urut. Deskripsi satu kalimat netral
     * (Urologi = kutipan 13 §4; sisanya ditulis di plan ini).
     *
     * @var list<array{name: string, slug: string, description: string}>
     */
    public const ROWS = [
        ['name' => 'Urologi', 'slug' => 'urologi', 'description' => 'Pelayanan kesehatan saluran kemih dan sistem reproduksi pria (contoh).'],
        ['name' => 'Penyakit Dalam', 'slug' => 'penyakit-dalam', 'description' => 'Pelayanan pemeriksaan dan penanganan penyakit organ dalam pada orang dewasa (contoh).'],
        ['name' => 'Anak', 'slug' => 'anak', 'description' => 'Pelayanan kesehatan bayi, anak, dan remaja (contoh).'],
        ['name' => 'Obstetri dan Ginekologi', 'slug' => 'obstetri-dan-ginekologi', 'description' => 'Pelayanan kesehatan ibu hamil dan sistem reproduksi wanita (contoh).'],
        ['name' => 'Bedah Umum', 'slug' => 'bedah-umum', 'description' => 'Pelayanan tindakan bedah serta perawatan sebelum dan sesudah operasi (contoh).'],
        ['name' => 'Jantung', 'slug' => 'jantung', 'description' => 'Pelayanan kesehatan jantung dan pembuluh darah (contoh).'],
        ['name' => 'Saraf', 'slug' => 'saraf', 'description' => 'Pelayanan kesehatan otak, saraf tulang belakang, dan saraf tepi (contoh).'],
        ['name' => 'Mata', 'slug' => 'mata', 'description' => 'Pelayanan pemeriksaan dan perawatan kesehatan mata (contoh).'],
        ['name' => 'THT', 'slug' => 'tht', 'description' => 'Pelayanan kesehatan telinga, hidung, dan tenggorok (contoh).'],
        ['name' => 'Kulit dan Kelamin', 'slug' => 'kulit-dan-kelamin', 'description' => 'Pelayanan kesehatan kulit, rambut, kuku, dan kelamin (contoh).'],
        ['name' => 'Ortopedi', 'slug' => 'ortopedi', 'description' => 'Pelayanan kesehatan tulang, sendi, dan otot (contoh).'],
        ['name' => 'Dokter Umum', 'slug' => 'dokter-umum', 'description' => 'Pelayanan pemeriksaan awal dan konsultasi kesehatan umum (contoh).'],
        ['name' => 'Dokter Gigi', 'slug' => 'dokter-gigi', 'description' => 'Pelayanan kesehatan gigi dan mulut (contoh).'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SpecialtySeeder DILARANG di APP_ENV='.app()->environment().'. Seeder master hanya untuk data demo.');
        }

        foreach (self::ROWS as $index => $row) {
            if (Specialty::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Spesialisasi {$row['name']} sudah ada, dilewati.");

                continue;
            }

            Specialty::query()->create($row + ['is_active' => true, 'sort_order' => $index + 1]);
        }
    }
}
