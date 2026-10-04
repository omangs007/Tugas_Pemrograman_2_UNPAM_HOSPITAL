<?php

namespace Tests\Unit;

use Database\Seeders\ClinicSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\SpecialtySeeder;
use Tests\TestCase;

/**
 * Penjaga data demo 13 §4–6 pada tingkat konstanta (tanpa database dan tanpa gambar).
 */
class MasterDataSeedRowsTest extends TestCase
{
    public function test_specialty_rows_match_13_section_4(): void
    {
        $this->assertCount(13, SpecialtySeeder::ROWS);
        $this->assertSame(
            ['urologi', 'penyakit-dalam', 'anak', 'obstetri-dan-ginekologi', 'bedah-umum', 'jantung', 'saraf', 'mata', 'tht', 'kulit-dan-kelamin', 'ortopedi', 'dokter-umum', 'dokter-gigi'],
            array_column(SpecialtySeeder::ROWS, 'slug'),
        );
        $this->assertSame('Pelayanan kesehatan saluran kemih dan sistem reproduksi pria (contoh).', SpecialtySeeder::ROWS[0]['description']);

        foreach (SpecialtySeeder::ROWS as $row) {
            $this->assertStringEndsWith('(contoh).', $row['description']);
            $this->assertLessThanOrEqual(100, mb_strlen($row['name']));
        }
    }

    public function test_clinic_rows_match_13_section_5(): void
    {
        $rows = ClinicSeeder::ROWS;

        $this->assertCount(17, $rows);
        $this->assertCount(13, array_filter($rows, fn (array $row): bool => $row['type'] === 'poliklinik' && $row['bookable'] === true));
        $this->assertCount(4, array_filter($rows, fn (array $row): bool => $row['type'] === 'penunjang' && $row['bookable'] === false && $row['specialty'] === null));
        $this->assertSame(['URO', 'PD', 'ANK', 'OBG', 'BDH', 'JTG', 'SRF', 'MTA', 'THT', 'KK', 'ORT', 'UMM', 'GG', 'LAB', 'RAD', 'FAR', 'IGD'], array_column($rows, 'code'));
        $this->assertCount(17, array_unique(array_column($rows, 'slug')));

        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2,5}$/', $row['code']);
            $this->assertLessThanOrEqual(300, mb_strlen(ClinicSeeder::shortDescription($row)));
        }

        $this->assertCount(16, array_filter($rows, fn (array $row): bool => $row['cover']));
        $this->assertFalse(collect($rows)->firstWhere('code', 'GG')['cover']);
        $this->assertSame('Gedung A Lt. 1 (contoh)', collect($rows)->firstWhere('code', 'IGD')['location']);
        $this->assertStringContainsString('Jam layanan dan nomor darurat adalah contoh prototipe', collect($rows)->firstWhere('code', 'IGD')['note']);
    }

    public function test_every_clinic_specialty_exists_in_the_specialty_rows(): void
    {
        $names = array_column(SpecialtySeeder::ROWS, 'name');

        foreach (ClinicSeeder::ROWS as $row) {
            if ($row['specialty'] !== null) {
                $this->assertContains($row['specialty'], $names, $row['name']);
            }
        }
    }

    public function test_doctor_rows_match_13_section_6(): void
    {
        $rows = DoctorSeeder::ROWS;
        $clinicNames = array_column(ClinicSeeder::ROWS, 'name');
        $specialtyNames = array_column(SpecialtySeeder::ROWS, 'name');

        $this->assertCount(18, $rows);
        $this->assertCount(18, array_unique(array_column($rows, 'slug')));
        $this->assertCount(16, array_filter($rows, fn (array $row): bool => $row['status'] === 'active'));
        $this->assertSame(['active', 'inactive', 'archived'], array_values(array_unique(array_column($rows, 'status'))));
        $this->assertCount(16, array_filter($rows, fn (array $row): bool => $row['photo']));
        $this->assertSame([14, 15], array_keys(array_filter($rows, fn (array $row): bool => ! $row['photo'])));
        $this->assertSame(20, array_sum(array_map(fn (array $row): int => 1 + count($row['extra']), $rows)));
        $this->assertSame(['Penyakit Dalam'], collect($rows)->firstWhere('slug', 'maya-kartika-sari')['extra']);
        $this->assertSame(['Bedah Umum'], collect($rows)->firstWhere('slug', 'arif-budiman-santoso')['extra']);

        foreach ($rows as $row) {
            $this->assertContains($row['clinic'], $clinicNames, $row['name']);
            $this->assertContains($row['primary'], $specialtyNames, $row['name']);

            foreach ($row['extra'] as $extra) {
                $this->assertContains($extra, $specialtyNames, $row['name']);
            }
        }

        $this->assertSame('inactive', collect($rows)->firstWhere('slug', 'eko-susanto')['status']);
        $this->assertSame('archived', collect($rows)->firstWhere('slug', 'lilis-marlina')['status']);
        $this->assertNull(collect($rows)->firstWhere('slug', 'rani-oktaviani')['suffix']);
        $this->assertSame('drg.', collect($rows)->firstWhere('slug', 'ayu-larasati-hakim')['prefix']);
    }
}
