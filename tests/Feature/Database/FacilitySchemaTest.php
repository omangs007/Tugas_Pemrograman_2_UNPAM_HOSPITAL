<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Skema fasilitas, kamar rawat inap, dan pesan kontak fase 4b: 09 §5.1, §5.2, §6.8, §10, §11. Seluruh insert
 * memakai DB::table() agar tes tidak bergantung pada model 4b (Task 2).
 */
class FacilitySchemaTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    // --- Data kamus ---

    /**
     * Kolom: tabel, nama kolom, type_name, tipe lengkap, nullable, default (setelah normalisasi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    public static function expectedColumns(): array
    {
        $rows = [
            // 09 §5.1 room_categories
            ['room_categories', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['room_categories', 'name', 'varchar', 'varchar(100)', false, null],
            ['room_categories', 'slug', 'varchar', 'varchar(120)', false, null],
            ['room_categories', 'short_description', 'varchar', 'varchar(300)', false, null],
            ['room_categories', 'description', 'text', 'text', true, null],
            ['room_categories', 'amenities', 'longtext', 'longtext', true, null],
            ['room_categories', 'bed_capacity', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['room_categories', 'beds_available', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['room_categories', 'sample_rate', 'decimal', 'decimal(12,2)', true, null],
            ['room_categories', 'rate_unit', 'varchar', 'varchar(30)', false, 'per malam'],
            ['room_categories', 'rate_note', 'varchar', 'varchar(255)', true, null],
            ['room_categories', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['room_categories', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['room_categories', 'availability_updated_at', 'datetime', 'datetime', true, null],
            ['room_categories', 'availability_updated_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['room_categories', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['room_categories', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['room_categories', 'created_at', 'timestamp', 'timestamp', true, null],
            ['room_categories', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §5.2 facilities
            ['facilities', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['facilities', 'name', 'varchar', 'varchar(150)', false, null],
            ['facilities', 'slug', 'varchar', 'varchar(170)', false, null],
            ['facilities', 'category', 'varchar', 'varchar(20)', false, null],
            ['facilities', 'short_description', 'varchar', 'varchar(300)', false, null],
            ['facilities', 'description', 'text', 'text', true, null],
            ['facilities', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['facilities', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['facilities', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['facilities', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['facilities', 'created_at', 'timestamp', 'timestamp', true, null],
            ['facilities', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.8 contact_messages
            ['contact_messages', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['contact_messages', 'name', 'varchar', 'varchar(100)', false, null],
            ['contact_messages', 'email', 'varchar', 'varchar(255)', false, null],
            ['contact_messages', 'phone', 'varchar', 'varchar(20)', true, null],
            ['contact_messages', 'subject', 'varchar', 'varchar(150)', false, null],
            ['contact_messages', 'message', 'text', 'text', false, null],
            ['contact_messages', 'status', 'varchar', 'varchar(10)', false, 'new'],
            ['contact_messages', 'consent_at', 'datetime', 'datetime', false, null],
            ['contact_messages', 'ip_hash', 'char', 'char(64)', false, null],
            ['contact_messages', 'handled_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['contact_messages', 'handled_at', 'datetime', 'datetime', true, null],
            ['contact_messages', 'created_at', 'timestamp', 'timestamp', true, null],
            ['contact_messages', 'updated_at', 'timestamp', 'timestamp', true, null],
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
            ['room_categories', 'primary', ['id'], true, true],
            ['room_categories', 'room_categories_slug_unique', ['slug'], true, false],
            ['room_categories', 'room_categories_availability_updated_by_foreign', ['availability_updated_by'], false, false],
            ['room_categories', 'room_categories_is_active_sort_order_index', ['is_active', 'sort_order'], false, false],
            ['facilities', 'primary', ['id'], true, true],
            ['facilities', 'facilities_slug_unique', ['slug'], true, false],
            ['facilities', 'facilities_is_active_category_sort_order_index', ['is_active', 'category', 'sort_order'], false, false],
            ['contact_messages', 'primary', ['id'], true, true],
            ['contact_messages', 'contact_messages_handled_by_foreign', ['handled_by'], false, false],
            ['contact_messages', 'contact_messages_status_created_at_index', ['status', 'created_at'], false, false],
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
            'room_categories.availability_updated_by' => ['room_categories', 'room_categories_availability_updated_by_foreign', 'availability_updated_by', 'users', 'set null'],
            'contact_messages.handled_by' => ['contact_messages', 'contact_messages_handled_by_foreign', 'handled_by', 'users', 'set null'],
        ];
    }

    /**
     * Pelanggaran CHECK: nama constraint dan penyisipan yang harus ditolak.
     *
     * @return array<string, array{0: string, 1: Closure}>
     */
    public static function checkViolations(): array
    {
        return [
            'chk_room_categories_beds: tersedia melebihi kapasitas' => ['chk_room_categories_beds', fn (self $t) => $t->insertRoom(['bed_capacity' => 10, 'beds_available' => 11])],
            'chk_room_categories_beds: kapasitas 0 tersedia 1' => ['chk_room_categories_beds', fn (self $t) => $t->insertRoom(['bed_capacity' => 0, 'beds_available' => 1])],
            'chk_room_categories_beds: tersedia ada, kapasitas bawaan 0' => ['chk_room_categories_beds', fn (self $t) => $t->insertRoom(['beds_available' => 3])],
            'amenities: bukan JSON' => ['amenities', fn (self $t) => $t->insertRoom(['amenities' => 'bukan json'])],
        ];
    }

    // --- Pembantu skema ---

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

    /** @return list<string> */
    private function namedChecks(): array
    {
        $rows = DB::select(
            'SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS constraint_name
             FROM information_schema.CHECK_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME LIKE ?
               AND TABLE_NAME IN (?, ?, ?)',
            ['chk\\_%', 'room_categories', 'facilities', 'contact_messages'],
        );

        $names = array_map(fn ($row): string => $row->table_name.'.'.$row->constraint_name, $rows);
        sort($names);

        return $names;
    }

    // --- Pembantu insert ---

    /** @param  array<string, mixed>  $overrides */
    public function insertRoom(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('room_categories')->insertGetId(array_merge([
            'name' => "Kamar {$n}", 'slug' => "kamar-{$n}", 'short_description' => 'Ringkasan kamar contoh.',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertFacility(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('facilities')->insertGetId(array_merge([
            'name' => "Fasilitas {$n}", 'slug' => "fasilitas-{$n}", 'category' => 'umum', 'short_description' => 'Ringkasan fasilitas contoh.',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertMessage(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('contact_messages')->insertGetId(array_merge([
            'name' => "Pengirim {$n}",
            'email' => "pengirim{$n}@contoh.test",
            'subject' => "Subjek contoh {$n}",
            'message' => 'Isi pesan contoh yang cukup panjang.',
            'consent_at' => '2026-10-05 10:00:00',
            'ip_hash' => hash('sha256', '127.0.0.1'.'kunci-uji'),
        ], $overrides));
    }

    // --- Kamus data ---

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
            ['id', 'name', 'slug', 'short_description', 'description', 'amenities', 'bed_capacity', 'beds_available', 'sample_rate', 'rate_unit', 'rate_note', 'is_active', 'sort_order', 'availability_updated_at', 'availability_updated_by', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('room_categories'),
        );
        $this->assertSame(
            ['id', 'name', 'slug', 'category', 'short_description', 'description', 'is_active', 'sort_order', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('facilities'),
        );
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'subject', 'message', 'status', 'consent_at', 'ip_hash', 'handled_by', 'handled_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('contact_messages'),
        );
    }

    public function test_primary_keys_are_auto_increment(): void
    {
        foreach (['room_categories', 'facilities', 'contact_messages'] as $table) {
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

    public function test_only_the_documented_foreign_keys_exist_on_the_phase_4b_tables(): void
    {
        $this->assertCount(1, Schema::getForeignKeys('room_categories'));
        $this->assertCount(1, Schema::getForeignKeys('contact_messages'));
        $this->assertSame([], Schema::getForeignKeys('facilities'), 'facilities tidak punya FK.');
    }

    public function test_migrations_are_registered_under_the_documented_names(): void
    {
        $names = DB::table('migrations')->pluck('migration')->all();

        foreach ([
            '2026_10_02_001200_create_room_categories_table',
            '2026_10_02_001300_create_facilities_table',
            '2026_10_02_002000_create_contact_messages_table',
        ] as $migration) {
            $this->assertContains($migration, $names);
            $this->assertFileExists(database_path("migrations/{$migration}.php"));
        }
    }

    public function test_every_down_method_drops_its_table(): void
    {
        foreach ([
            '001200_create_room_categories_table' => 'room_categories',
            '001300_create_facilities_table' => 'facilities',
            '002000_create_contact_messages_table' => 'contact_messages',
        ] as $suffix => $table) {
            $source = (string) file_get_contents(database_path("migrations/2026_10_02_{$suffix}.php"));

            $this->assertStringContainsString("Schema::dropIfExists('{$table}');", $source);
        }
    }

    // --- CHECK ---

    public function test_only_chk_room_categories_beds_exists_as_a_named_check_on_the_phase_4b_tables(): void
    {
        $this->assertSame(['room_categories.chk_room_categories_beds'], $this->namedChecks());
    }

    #[DataProvider('checkViolations')]
    public function test_check_constraint_rejects_the_value_with_errno_4025(string $constraint, Closure $insert): void
    {
        try {
            $insert($this);
            $this->fail("Constraint {$constraint} seharusnya menolak penyisipan.");
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0], 'SQLSTATE');
            $this->assertSame(4025, $e->errorInfo[1], 'errno');
            $this->assertStringContainsString("`{$constraint}`", $e->getMessage());
        }

        $this->assertSame(0, DB::table('room_categories')->count());
    }

    public function test_bed_check_accepts_the_boundary_values(): void
    {
        $this->insertRoom(['bed_capacity' => 12, 'beds_available' => 12]);
        $this->insertRoom(['bed_capacity' => 12, 'beds_available' => 0]);
        $this->insertRoom(['bed_capacity' => 0, 'beds_available' => 0]);
        $this->insertRoom(['bed_capacity' => 1, 'beds_available' => 1]);
        $this->insertRoom();

        $this->assertSame(5, DB::table('room_categories')->count());
    }

    public function test_bed_check_is_also_enforced_when_availability_is_raised_above_capacity_on_update(): void
    {
        $id = $this->insertRoom(['bed_capacity' => 10, 'beds_available' => 4]);

        try {
            DB::table('room_categories')->where('id', $id)->update(['beds_available' => 11]);
            $this->fail('Pembaruan yang melebihi kapasitas seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('`chk_room_categories_beds`', $e->getMessage());
        }

        DB::table('room_categories')->where('id', $id)->update(['beds_available' => 10]);

        $this->assertSame(10, (int) DB::table('room_categories')->where('id', $id)->value('beds_available'));
    }

    public function test_bed_check_is_also_enforced_when_capacity_is_lowered_below_availability_on_update(): void
    {
        $id = $this->insertRoom(['bed_capacity' => 10, 'beds_available' => 6]);

        try {
            DB::table('room_categories')->where('id', $id)->update(['bed_capacity' => 5]);
            $this->fail('Kapasitas di bawah ketersediaan seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('`chk_room_categories_beds`', $e->getMessage());
        }

        $this->assertSame(10, (int) DB::table('room_categories')->where('id', $id)->value('bed_capacity'));
    }

    public function test_a_negative_availability_is_rejected_by_the_unsigned_column(): void
    {
        $this->expectException(QueryException::class);

        $this->insertRoom(['bed_capacity' => 10, 'beds_available' => -1]);
    }

    public function test_amenities_accepts_null_and_a_valid_json_array(): void
    {
        $withJson = $this->insertRoom(['amenities' => json_encode(['AC', 'Televisi', 'Kamar mandi dalam'])]);
        $withNull = $this->insertRoom(['amenities' => null]);

        $this->assertSame(['AC', 'Televisi', 'Kamar mandi dalam'], json_decode((string) DB::table('room_categories')->where('id', $withJson)->value('amenities'), true));
        $this->assertNull(DB::table('room_categories')->where('id', $withNull)->value('amenities'));
    }

    // --- Perilaku kendala dan default ---

    public function test_room_category_defaults(): void
    {
        $row = DB::table('room_categories')->find($this->insertRoom());

        $this->assertSame(0, (int) $row->bed_capacity);
        $this->assertSame(0, (int) $row->beds_available);
        $this->assertSame('per malam', $row->rate_unit);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame(0, (int) $row->sort_order);
        $this->assertNull($row->description);
        $this->assertNull($row->amenities);
        $this->assertNull($row->sample_rate);
        $this->assertNull($row->rate_note);
        $this->assertNull($row->availability_updated_at);
        $this->assertNull($row->availability_updated_by);
        $this->assertNull($row->meta_title);
        $this->assertNull($row->meta_description);
    }

    public function test_sample_rate_keeps_two_decimals(): void
    {
        $id = $this->insertRoom(['sample_rate' => 1200000]);

        $this->assertSame('1200000.00', (string) DB::table('room_categories')->where('id', $id)->value('sample_rate'));
    }

    public function test_room_category_slug_is_unique_and_case_insensitive_but_name_is_not_unique(): void
    {
        $this->insertRoom(['name' => 'Kamar VIP', 'slug' => 'vip']);
        $this->insertRoom(['name' => 'Kamar VIP', 'slug' => 'vip-2']);

        $this->assertSame(2, DB::table('room_categories')->where('name', 'Kamar VIP')->count());

        try {
            $this->insertRoom(['slug' => 'VIP']);
            $this->fail('Slug kamar ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('room_categories_slug_unique', $e->getMessage());
        }
    }

    public function test_room_category_long_text_columns_hold_their_documented_lengths(): void
    {
        $id = $this->insertRoom([
            'name' => str_repeat('n', 100),
            'slug' => str_repeat('s', 120),
            'short_description' => str_repeat('d', 300),
            'description' => str_repeat('x', 30000),
            'rate_note' => str_repeat('r', 255),
        ]);

        $row = DB::table('room_categories')->find($id);

        $this->assertSame(100, strlen($row->name));
        $this->assertSame(120, strlen($row->slug));
        $this->assertSame(300, strlen($row->short_description));
        $this->assertSame(30000, strlen((string) $row->description));
    }

    public function test_deleting_a_user_sets_availability_updated_by_to_null(): void
    {
        $user = User::factory()->create();
        $roomId = $this->insertRoom(['availability_updated_by' => $user->id, 'availability_updated_at' => '2026-10-05 08:00:00']);

        DB::table('users')->where('id', $user->id)->delete();

        $row = DB::table('room_categories')->find($roomId);

        $this->assertNull($row->availability_updated_by);
        $this->assertSame('2026-10-05 08:00:00', $row->availability_updated_at, 'Waktu pembaruan tidak ikut terhapus.');
    }

    public function test_availability_updated_by_rejects_an_unknown_user(): void
    {
        $this->expectException(QueryException::class);

        $this->insertRoom(['availability_updated_by' => 999999]);
    }

    public function test_facility_defaults(): void
    {
        $row = DB::table('facilities')->find($this->insertFacility());

        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame(0, (int) $row->sort_order);
        $this->assertNull($row->description);
        $this->assertNull($row->meta_title);
        $this->assertNull($row->meta_description);
    }

    public function test_facility_slug_is_unique_and_case_insensitive_but_name_is_not_unique(): void
    {
        $this->insertFacility(['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium']);
        $this->insertFacility(['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium-2']);

        $this->assertSame(2, DB::table('facilities')->where('name', 'Laboratorium')->count());

        try {
            $this->insertFacility(['slug' => 'Fasilitas-Laboratorium']);
            $this->fail('Slug fasilitas ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('facilities_slug_unique', $e->getMessage());
        }
    }

    public function test_facility_long_text_columns_hold_their_documented_lengths(): void
    {
        $id = $this->insertFacility([
            'name' => str_repeat('n', 150),
            'slug' => str_repeat('s', 170),
            'short_description' => str_repeat('d', 300),
            'description' => str_repeat('x', 30000),
        ]);

        $row = DB::table('facilities')->find($id);

        $this->assertSame(150, strlen($row->name));
        $this->assertSame(170, strlen($row->slug));
        $this->assertSame(300, strlen($row->short_description));
    }

    public function test_contact_message_defaults(): void
    {
        $row = DB::table('contact_messages')->find($this->insertMessage());

        $this->assertSame('new', $row->status);
        $this->assertNull($row->phone);
        $this->assertNull($row->handled_by);
        $this->assertNull($row->handled_at);
        $this->assertSame(64, strlen($row->ip_hash));
        $this->assertSame('2026-10-05 10:00:00', $row->consent_at);
    }

    public function test_contact_message_ip_hash_holds_exactly_a_sha256_hex_digest(): void
    {
        $hash = hash('sha256', '203.0.113.7'.'kunci-uji');

        $id = $this->insertMessage(['ip_hash' => $hash]);

        $this->assertSame($hash, DB::table('contact_messages')->where('id', $id)->value('ip_hash'));

        $this->expectException(QueryException::class);

        $this->insertMessage(['ip_hash' => $hash.'0']);
    }

    public function test_contact_message_requires_consent_and_ip_hash(): void
    {
        foreach (['consent_at', 'ip_hash'] as $column) {
            try {
                $row = [
                    'name' => 'Pengirim', 'email' => 'pengirim@contoh.test', 'subject' => 'Subjek', 'message' => 'Isi pesan contoh.',
                    'consent_at' => '2026-10-05 10:00:00', 'ip_hash' => hash('sha256', 'x'),
                ];
                unset($row[$column]);

                DB::table('contact_messages')->insert($row);
                $this->fail("Kolom {$column} wajib diisi.");
            } catch (QueryException $e) {
                $this->assertSame(1364, $e->errorInfo[1], "{$column}: kolom NOT NULL tanpa default ditolak pada mode strict.");
            }
        }

        $this->assertSame(0, DB::table('contact_messages')->count());
    }

    public function test_contact_message_holds_a_two_thousand_character_message(): void
    {
        $id = $this->insertMessage(['message' => str_repeat('p', 2000), 'phone' => '081234567890', 'name' => str_repeat('n', 100), 'subject' => str_repeat('s', 150)]);

        $row = DB::table('contact_messages')->find($id);

        $this->assertSame(2000, strlen($row->message));
        $this->assertSame('081234567890', $row->phone);
    }

    public function test_deleting_a_staff_user_sets_handled_by_to_null_and_keeps_the_message(): void
    {
        $user = User::factory()->staff()->create();
        $messageId = $this->insertMessage(['status' => 'replied', 'handled_by' => $user->id, 'handled_at' => '2026-10-05 11:00:00']);

        DB::table('users')->where('id', $user->id)->delete();

        $row = DB::table('contact_messages')->find($messageId);

        $this->assertNull($row->handled_by);
        $this->assertSame('replied', $row->status);
        $this->assertSame('2026-10-05 11:00:00', $row->handled_at);
    }

    public function test_handled_by_rejects_an_unknown_user(): void
    {
        $this->expectException(QueryException::class);

        $this->insertMessage(['handled_by' => 999999]);
    }

    public function test_datetime_columns_keep_the_wall_clock_value_without_conversion(): void
    {
        $roomId = $this->insertRoom(['bed_capacity' => 6, 'beds_available' => 2, 'availability_updated_at' => '2026-10-05 23:30:00']);
        $messageId = $this->insertMessage(['consent_at' => '2026-10-05 00:00:00', 'handled_at' => '2026-10-31 23:59:59']);

        $this->assertSame('2026-10-05 23:30:00', DB::table('room_categories')->where('id', $roomId)->value('availability_updated_at'));
        $this->assertSame('2026-10-05 00:00:00', DB::table('contact_messages')->where('id', $messageId)->value('consent_at'));
        $this->assertSame('2026-10-31 23:59:59', DB::table('contact_messages')->where('id', $messageId)->value('handled_at'));
    }
}
