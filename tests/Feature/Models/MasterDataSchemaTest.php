<?php

namespace Tests\Feature\Models;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Skema 09-data-dictionary.md §3.1-3.4 (spesialisasi, poliklinik, dokter, pivot spesialisasi dokter).
 */
class MasterDataSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kolom: tabel, nama kolom, type_name, tipe lengkap, nullable, default (setelah normalisasi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    public static function expectedColumns(): array
    {
        $rows = [
            // 09 §3.1 specialties
            ['specialties', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['specialties', 'name', 'varchar', 'varchar(100)', false, null],
            ['specialties', 'slug', 'varchar', 'varchar(120)', false, null],
            ['specialties', 'description', 'text', 'text', true, null],
            ['specialties', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['specialties', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['specialties', 'created_at', 'timestamp', 'timestamp', true, null],
            ['specialties', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.2 clinics
            ['clinics', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['clinics', 'name', 'varchar', 'varchar(120)', false, null],
            ['clinics', 'slug', 'varchar', 'varchar(140)', false, null],
            ['clinics', 'code', 'varchar', 'varchar(5)', false, null],
            ['clinics', 'type', 'varchar', 'varchar(20)', false, null],
            ['clinics', 'is_bookable', 'tinyint', 'tinyint(1)', false, '1'],
            ['clinics', 'specialty_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['clinics', 'short_description', 'varchar', 'varchar(300)', false, null],
            ['clinics', 'description', 'text', 'text', true, null],
            ['clinics', 'location', 'varchar', 'varchar(150)', false, null],
            ['clinics', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['clinics', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['clinics', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['clinics', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['clinics', 'created_at', 'timestamp', 'timestamp', true, null],
            ['clinics', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.3 doctors
            ['doctors', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctors', 'clinic_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctors', 'name', 'varchar', 'varchar(150)', false, null],
            ['doctors', 'slug', 'varchar', 'varchar(170)', false, null],
            ['doctors', 'title_prefix', 'varchar', 'varchar(20)', true, null],
            ['doctors', 'title_suffix', 'varchar', 'varchar(50)', true, null],
            ['doctors', 'gender', 'char', 'char(1)', false, null],
            ['doctors', 'short_bio', 'varchar', 'varchar(500)', true, null],
            ['doctors', 'bio', 'text', 'text', true, null],
            ['doctors', 'status', 'varchar', 'varchar(10)', false, 'active'],
            ['doctors', 'archived_at', 'datetime', 'datetime', true, null],
            ['doctors', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['doctors', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['doctors', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['doctors', 'created_at', 'timestamp', 'timestamp', true, null],
            ['doctors', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.4 doctor_specialties
            ['doctor_specialties', 'doctor_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctor_specialties', 'specialty_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctor_specialties', 'is_primary', 'tinyint', 'tinyint(1)', false, '0'],
        ];

        $cases = [];

        foreach ($rows as $row) {
            $cases["{$row[0]}.{$row[1]}"] = $row;
        }

        return $cases;
    }

    /**
     * Index: tabel, nama index, kolom, unik, primer.
     *
     * @return array<string, array{0: string, 1: string, 2: list<string>, 3: bool, 4: bool}>
     */
    public static function expectedIndexes(): array
    {
        $rows = [
            ['specialties', 'primary', ['id'], true, true],
            ['specialties', 'specialties_slug_unique', ['slug'], true, false],
            ['specialties', 'specialties_is_active_sort_order_index', ['is_active', 'sort_order'], false, false],
            ['clinics', 'primary', ['id'], true, true],
            ['clinics', 'clinics_slug_unique', ['slug'], true, false],
            ['clinics', 'clinics_code_unique', ['code'], true, false],
            ['clinics', 'clinics_specialty_id_foreign', ['specialty_id'], false, false],
            ['clinics', 'clinics_is_active_type_sort_order_index', ['is_active', 'type', 'sort_order'], false, false],
            ['doctors', 'primary', ['id'], true, true],
            ['doctors', 'doctors_slug_unique', ['slug'], true, false],
            ['doctors', 'doctors_clinic_id_foreign', ['clinic_id'], false, false],
            ['doctors', 'doctors_status_sort_order_index', ['status', 'sort_order'], false, false],
            ['doctor_specialties', 'primary', ['doctor_id', 'specialty_id'], true, true],
            ['doctor_specialties', 'doctor_specialties_specialty_id_foreign', ['specialty_id'], false, false],
        ];

        $cases = [];

        foreach ($rows as $row) {
            $cases["{$row[0]}.{$row[1]}"] = $row;
        }

        return $cases;
    }

    /**
     * Foreign key: tabel, nama FK, kolom, tabel rujukan, aksi ON DELETE.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function expectedForeignKeys(): array
    {
        return [
            'clinics.specialty_id' => ['clinics', 'clinics_specialty_id_foreign', 'specialty_id', 'specialties', 'set null'],
            'doctors.clinic_id' => ['doctors', 'doctors_clinic_id_foreign', 'clinic_id', 'clinics', 'restrict'],
            'doctor_specialties.doctor_id' => ['doctor_specialties', 'doctor_specialties_doctor_id_foreign', 'doctor_id', 'doctors', 'cascade'],
            'doctor_specialties.specialty_id' => ['doctor_specialties', 'doctor_specialties_specialty_id_foreign', 'specialty_id', 'specialties', 'restrict'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function columnsOf(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = $column;
        }

        return $columns;
    }

    /** MariaDB 10.4 melaporkan default NULL sebagai string "NULL" dan default string dengan kutip. */
    private function normalizedDefault(?string $default): ?string
    {
        if ($default === null || strtoupper($default) === 'NULL') {
            return null;
        }

        return trim($default, "'");
    }

    /** @return array<string, array<string, mixed>> */
    private function indexesOf(string $table): array
    {
        $indexes = [];

        foreach (Schema::getIndexes($table) as $index) {
            $indexes[$index['name']] = $index;
        }

        return $indexes;
    }

    #[DataProvider('expectedColumns')]
    public function test_column_matches_the_data_dictionary(string $table, string $name, string $typeName, string $type, bool $nullable, ?string $default): void
    {
        $columns = $this->columnsOf($table);

        $this->assertArrayHasKey($name, $columns, "Kolom {$table}.{$name} belum ada.");
        $this->assertSame($typeName, $columns[$name]['type_name'], "type_name {$table}.{$name}");
        $this->assertSame($type, $columns[$name]['type'], "type {$table}.{$name}");
        $this->assertSame($nullable, $columns[$name]['nullable'], "nullable {$table}.{$name}");
        $this->assertSame($default, $this->normalizedDefault($columns[$name]['default']), "default {$table}.{$name}");
    }

    public function test_tables_have_exactly_the_documented_columns_in_order(): void
    {
        $this->assertSame(
            ['id', 'name', 'slug', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            Schema::getColumnListing('specialties'),
        );
        $this->assertSame(
            ['id', 'name', 'slug', 'code', 'type', 'is_bookable', 'specialty_id', 'short_description', 'description', 'location', 'is_active', 'sort_order', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('clinics'),
        );
        $this->assertSame(
            ['id', 'clinic_id', 'name', 'slug', 'title_prefix', 'title_suffix', 'gender', 'short_bio', 'bio', 'status', 'archived_at', 'sort_order', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('doctors'),
        );
        $this->assertSame(['doctor_id', 'specialty_id', 'is_primary'], Schema::getColumnListing('doctor_specialties'));
    }

    public function test_primary_keys_are_auto_increment_except_the_pivot(): void
    {
        foreach (['specialties', 'clinics', 'doctors'] as $table) {
            $this->assertTrue($this->columnsOf($table)['id']['auto_increment'], "{$table}.id auto increment");
        }
    }

    #[DataProvider('expectedIndexes')]
    public function test_index_matches_the_data_dictionary(string $table, string $name, array $columns, bool $unique, bool $primary): void
    {
        $indexes = $this->indexesOf($table);

        $this->assertArrayHasKey($name, $indexes, "Index {$table}.{$name} belum ada.");
        $this->assertSame($columns, $indexes[$name]['columns']);
        $this->assertSame($unique, $indexes[$name]['unique']);
        $this->assertSame($primary, $indexes[$name]['primary']);
    }

    #[DataProvider('expectedForeignKeys')]
    public function test_foreign_key_actions_match_the_data_dictionary(string $table, string $name, string $column, string $foreignTable, string $onDelete): void
    {
        $foreignKeys = collect(Schema::getForeignKeys($table))->keyBy('name');

        $this->assertTrue($foreignKeys->has($name), "FK {$name} belum ada.");
        $this->assertSame([$column], $foreignKeys[$name]['columns']);
        $this->assertSame($foreignTable, $foreignKeys[$name]['foreign_table']);
        $this->assertSame(['id'], $foreignKeys[$name]['foreign_columns']);
        $this->assertSame($onDelete, $foreignKeys[$name]['on_delete']);
    }

    public function test_migrations_are_registered_under_the_documented_names(): void
    {
        $names = DB::table('migrations')->pluck('migration')->all();

        foreach ([
            '2026_10_02_000200_create_specialties_table',
            '2026_10_02_000300_create_clinics_table',
            '2026_10_02_000400_create_doctors_table',
            '2026_10_02_000500_create_doctor_specialties_table',
        ] as $migration) {
            $this->assertContains($migration, $names);
        }
    }

    // --- Perilaku kendala ---

    public function test_specialty_slug_is_unique_and_case_insensitive(): void
    {
        $this->insertSpecialty(['name' => 'Anak', 'slug' => 'anak']);

        $this->expectException(QueryException::class);

        $this->insertSpecialty(['name' => 'Anak Dua', 'slug' => 'Anak']);
    }

    public function test_specialty_name_is_not_unique_at_database_level(): void
    {
        $this->insertSpecialty(['name' => 'Anak', 'slug' => 'anak']);
        $this->insertSpecialty(['name' => 'Anak', 'slug' => 'anak-2']);

        $this->assertSame(2, DB::table('specialties')->where('name', 'Anak')->count());
    }

    public function test_clinic_slug_and_code_are_unique(): void
    {
        $this->insertClinic(['slug' => 'poliklinik-anak', 'code' => 'ANK']);

        try {
            $this->insertClinic(['slug' => 'poliklinik-anak', 'code' => 'ANL']);
            $this->fail('Slug ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('clinics_slug_unique', $e->getMessage());
        }

        try {
            $this->insertClinic(['slug' => 'poliklinik-anak-2', 'code' => 'ANK']);
            $this->fail('Kode ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('clinics_code_unique', $e->getMessage());
        }
    }

    public function test_doctor_slug_is_unique(): void
    {
        $clinicId = $this->insertClinic();
        $this->insertDoctor($clinicId, ['slug' => 'andi-pratama']);

        $this->expectException(QueryException::class);

        $this->insertDoctor($clinicId, ['slug' => 'andi-pratama']);
    }

    public function test_deleting_a_clinic_that_has_doctors_is_rejected_by_the_database(): void
    {
        $clinicId = $this->insertClinic();
        $this->insertDoctor($clinicId);

        try {
            DB::table('clinics')->where('id', $clinicId)->delete();
            $this->fail('FK doctors.clinic_id RESTRICT seharusnya menolak penghapusan.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('doctors_clinic_id_foreign', $e->getMessage());
        }

        $this->assertSame(1, DB::table('clinics')->where('id', $clinicId)->count());
    }

    public function test_deleting_a_specialty_used_by_a_doctor_is_rejected_by_the_database(): void
    {
        $specialtyId = $this->insertSpecialty();
        $doctorId = $this->insertDoctor($this->insertClinic());
        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => true]);

        try {
            DB::table('specialties')->where('id', $specialtyId)->delete();
            $this->fail('FK doctor_specialties.specialty_id RESTRICT seharusnya menolak penghapusan.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('doctor_specialties_specialty_id_foreign', $e->getMessage());
        }
    }

    public function test_deleting_a_specialty_used_by_a_clinic_sets_the_clinic_specialty_to_null(): void
    {
        $specialtyId = $this->insertSpecialty();
        $clinicId = $this->insertClinic(['specialty_id' => $specialtyId]);

        DB::table('specialties')->where('id', $specialtyId)->delete();

        $this->assertNull(DB::table('clinics')->where('id', $clinicId)->value('specialty_id'));
    }

    public function test_deleting_a_doctor_cascades_to_the_pivot_rows(): void
    {
        $specialtyId = $this->insertSpecialty();
        $doctorId = $this->insertDoctor($this->insertClinic());
        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => true]);

        DB::table('doctors')->where('id', $doctorId)->delete();

        $this->assertSame(0, DB::table('doctor_specialties')->count());
        $this->assertSame(1, DB::table('specialties')->count());
    }

    public function test_pivot_primary_key_rejects_the_same_specialty_twice_for_a_doctor(): void
    {
        $specialtyId = $this->insertSpecialty();
        $doctorId = $this->insertDoctor($this->insertClinic());
        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => true]);

        $this->expectException(QueryException::class);

        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => false]);
    }

    public function test_column_defaults_are_applied_by_the_database(): void
    {
        $specialtyId = DB::table('specialties')->insertGetId(['name' => 'Umum', 'slug' => 'umum']);
        $clinicId = DB::table('clinics')->insertGetId([
            'name' => 'Poliklinik Umum', 'slug' => 'poliklinik-umum', 'code' => 'UMM', 'type' => 'poliklinik',
            'short_description' => 'Ringkasan.', 'location' => 'Gedung A Lt. 1',
        ]);
        $doctorId = DB::table('doctors')->insertGetId([
            'clinic_id' => $clinicId, 'name' => 'Rani Oktaviani', 'slug' => 'rani-oktaviani', 'gender' => 'P',
        ]);

        $specialty = DB::table('specialties')->find($specialtyId);
        $clinic = DB::table('clinics')->find($clinicId);
        $doctor = DB::table('doctors')->find($doctorId);

        $this->assertSame([1, 0], [(int) $specialty->is_active, (int) $specialty->sort_order]);
        $this->assertSame([1, 1, 0], [(int) $clinic->is_bookable, (int) $clinic->is_active, (int) $clinic->sort_order]);
        $this->assertSame('active', $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertSame(0, (int) $doctor->sort_order);
    }

    // --- Pembantu ---

    /** @param  array<string, mixed>  $overrides */
    private function insertSpecialty(array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('specialties')->insertGetId(array_merge([
            'name' => "Spesialisasi {$n}", 'slug' => "spesialisasi-{$n}",
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertClinic(array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('clinics')->insertGetId(array_merge([
            'name' => "Poliklinik {$n}", 'slug' => "poliklinik-{$n}", 'code' => 'K'.chr(64 + $n), 'type' => 'poliklinik',
            'short_description' => 'Ringkasan.', 'location' => 'Gedung A Lt. 1',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertDoctor(int $clinicId, array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('doctors')->insertGetId(array_merge([
            'clinic_id' => $clinicId, 'name' => "Dokter {$n}", 'slug' => "dokter-{$n}", 'gender' => 'L',
        ], $overrides));
    }
}
