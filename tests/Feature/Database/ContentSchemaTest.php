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
 * Skema konten fase 4a: 09 §6.1-§6.6, §10, §11.3. Seluruh insert memakai DB::table() agar tes
 * tidak bergantung pada model konten (Task 2).
 */
class ContentSchemaTest extends TestCase
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
            // 09 §6.1 pages
            ['pages', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['pages', 'key', 'varchar', 'varchar(50)', false, null],
            ['pages', 'title', 'varchar', 'varchar(150)', false, null],
            ['pages', 'subtitle', 'varchar', 'varchar(255)', true, null],
            ['pages', 'body', 'mediumtext', 'mediumtext', true, null],
            ['pages', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['pages', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['pages', 'is_system', 'tinyint', 'tinyint(1)', false, '1'],
            ['pages', 'updated_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['pages', 'created_at', 'timestamp', 'timestamp', true, null],
            ['pages', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.2 article_categories
            ['article_categories', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['article_categories', 'name', 'varchar', 'varchar(100)', false, null],
            ['article_categories', 'slug', 'varchar', 'varchar(120)', false, null],
            ['article_categories', 'description', 'varchar', 'varchar(255)', true, null],
            ['article_categories', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['article_categories', 'created_at', 'timestamp', 'timestamp', true, null],
            ['article_categories', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.3 articles
            ['articles', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['articles', 'article_category_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['articles', 'author_user_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['articles', 'title', 'varchar', 'varchar(200)', false, null],
            ['articles', 'slug', 'varchar', 'varchar(220)', false, null],
            ['articles', 'excerpt', 'varchar', 'varchar(300)', false, null],
            ['articles', 'body', 'longtext', 'longtext', false, null],
            ['articles', 'status', 'varchar', 'varchar(10)', false, 'draft'],
            ['articles', 'published_at', 'datetime', 'datetime', true, null],
            ['articles', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['articles', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['articles', 'created_at', 'timestamp', 'timestamp', true, null],
            ['articles', 'updated_at', 'timestamp', 'timestamp', true, null],
            ['articles', 'deleted_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.4 banners
            ['banners', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['banners', 'title', 'varchar', 'varchar(150)', false, null],
            ['banners', 'subtitle', 'varchar', 'varchar(255)', true, null],
            ['banners', 'button_label', 'varchar', 'varchar(50)', true, null],
            ['banners', 'button_url', 'varchar', 'varchar(255)', true, null],
            ['banners', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['banners', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['banners', 'starts_at', 'datetime', 'datetime', true, null],
            ['banners', 'ends_at', 'datetime', 'datetime', true, null],
            ['banners', 'created_at', 'timestamp', 'timestamp', true, null],
            ['banners', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.5 galleries
            ['galleries', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['galleries', 'title', 'varchar', 'varchar(150)', false, null],
            ['galleries', 'slug', 'varchar', 'varchar(170)', false, null],
            ['galleries', 'description', 'varchar', 'varchar(500)', true, null],
            ['galleries', 'is_published', 'tinyint', 'tinyint(1)', false, '0'],
            ['galleries', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['galleries', 'created_at', 'timestamp', 'timestamp', true, null],
            ['galleries', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.6 faqs
            ['faqs', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['faqs', 'category', 'varchar', 'varchar(50)', false, null],
            ['faqs', 'question', 'varchar', 'varchar(255)', false, null],
            ['faqs', 'answer', 'text', 'text', false, null],
            ['faqs', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['faqs', 'is_published', 'tinyint', 'tinyint(1)', false, '0'],
            ['faqs', 'created_at', 'timestamp', 'timestamp', true, null],
            ['faqs', 'updated_at', 'timestamp', 'timestamp', true, null],
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
            ['pages', 'primary', ['id'], true, true],
            ['pages', 'pages_key_unique', ['key'], true, false],
            ['pages', 'pages_updated_by_foreign', ['updated_by'], false, false],
            ['article_categories', 'primary', ['id'], true, true],
            ['article_categories', 'article_categories_slug_unique', ['slug'], true, false],
            ['articles', 'primary', ['id'], true, true],
            ['articles', 'articles_slug_unique', ['slug'], true, false],
            ['articles', 'articles_article_category_id_foreign', ['article_category_id'], false, false],
            ['articles', 'articles_author_user_id_foreign', ['author_user_id'], false, false],
            ['articles', 'articles_status_published_at_index', ['status', 'published_at'], false, false],
            ['banners', 'primary', ['id'], true, true],
            ['banners', 'banners_is_active_sort_order_index', ['is_active', 'sort_order'], false, false],
            ['galleries', 'primary', ['id'], true, true],
            ['galleries', 'galleries_slug_unique', ['slug'], true, false],
            ['galleries', 'galleries_is_published_sort_order_index', ['is_published', 'sort_order'], false, false],
            ['faqs', 'primary', ['id'], true, true],
            ['faqs', 'faqs_is_published_category_sort_order_index', ['is_published', 'category', 'sort_order'], false, false],
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
            'pages.updated_by' => ['pages', 'pages_updated_by_foreign', 'updated_by', 'users', 'set null'],
            'articles.article_category_id' => ['articles', 'articles_article_category_id_foreign', 'article_category_id', 'article_categories', 'set null'],
            'articles.author_user_id' => ['articles', 'articles_author_user_id_foreign', 'author_user_id', 'users', 'set null'],
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
            'chk_banners_period: selesai sebelum mulai' => ['chk_banners_period', fn (self $t) => $t->insertBanner(['starts_at' => '2026-10-10 10:00:00', 'ends_at' => '2026-10-10 09:59:59'])],
            'chk_banners_period: selesai sama dengan mulai' => ['chk_banners_period', fn (self $t) => $t->insertBanner(['starts_at' => '2026-10-10 10:00:00', 'ends_at' => '2026-10-10 10:00:00'])],
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
               AND TABLE_NAME IN (?, ?, ?, ?, ?, ?)',
            ['chk\\_%', 'pages', 'article_categories', 'articles', 'banners', 'galleries', 'faqs'],
        );

        $names = array_map(fn ($row): string => $row->table_name.'.'.$row->constraint_name, $rows);
        sort($names);

        return $names;
    }

    // --- Pembantu insert ---

    /** @param  array<string, mixed>  $overrides */
    public function insertPage(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('pages')->insertGetId(array_merge([
            'key' => "halaman-{$n}", 'title' => "Halaman {$n}",
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertCategory(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('article_categories')->insertGetId(array_merge([
            'name' => "Kategori {$n}", 'slug' => "kategori-{$n}",
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertArticle(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('articles')->insertGetId(array_merge([
            'title' => "Artikel {$n}", 'slug' => "artikel-{$n}", 'excerpt' => 'Ringkasan artikel.', 'body' => '<p>Isi artikel.</p>',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertBanner(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('banners')->insertGetId(array_merge([
            'title' => "Banner {$n}",
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertGallery(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('galleries')->insertGetId(array_merge([
            'title' => "Album {$n}", 'slug' => "album-{$n}",
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertFaq(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('faqs')->insertGetId(array_merge([
            'category' => 'umum', 'question' => "Pertanyaan contoh {$n}?", 'answer' => 'Jawaban contoh.',
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
            ['id', 'key', 'title', 'subtitle', 'body', 'meta_title', 'meta_description', 'is_system', 'updated_by', 'created_at', 'updated_at'],
            Schema::getColumnListing('pages'),
        );
        $this->assertSame(
            ['id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            Schema::getColumnListing('article_categories'),
        );
        $this->assertSame(
            ['id', 'article_category_id', 'author_user_id', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'meta_title', 'meta_description', 'created_at', 'updated_at', 'deleted_at'],
            Schema::getColumnListing('articles'),
        );
        $this->assertSame(
            ['id', 'title', 'subtitle', 'button_label', 'button_url', 'sort_order', 'is_active', 'starts_at', 'ends_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('banners'),
        );
        $this->assertSame(
            ['id', 'title', 'slug', 'description', 'is_published', 'sort_order', 'created_at', 'updated_at'],
            Schema::getColumnListing('galleries'),
        );
        $this->assertSame(
            ['id', 'category', 'question', 'answer', 'sort_order', 'is_published', 'created_at', 'updated_at'],
            Schema::getColumnListing('faqs'),
        );
    }

    public function test_primary_keys_are_auto_increment(): void
    {
        foreach (['pages', 'article_categories', 'articles', 'banners', 'galleries', 'faqs'] as $table) {
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

    public function test_only_the_documented_foreign_keys_exist_on_the_content_tables(): void
    {
        $this->assertCount(1, Schema::getForeignKeys('pages'));
        $this->assertCount(2, Schema::getForeignKeys('articles'));

        foreach (['article_categories', 'banners', 'galleries', 'faqs'] as $table) {
            $this->assertSame([], Schema::getForeignKeys($table), "{$table} tidak punya FK");
        }
    }

    public function test_migrations_are_registered_under_the_documented_names(): void
    {
        $names = DB::table('migrations')->pluck('migration')->all();

        foreach ([
            '2026_10_02_001400_create_pages_table',
            '2026_10_02_001500_create_article_categories_table',
            '2026_10_02_001600_create_articles_table',
            '2026_10_02_001700_create_banners_table',
            '2026_10_02_001800_create_galleries_table',
            '2026_10_02_001900_create_faqs_table',
        ] as $migration) {
            $this->assertContains($migration, $names);
            $this->assertFileExists(database_path("migrations/{$migration}.php"));
        }
    }

    public function test_every_down_method_drops_its_table(): void
    {
        foreach ([
            '001400_create_pages_table' => 'pages',
            '001500_create_article_categories_table' => 'article_categories',
            '001600_create_articles_table' => 'articles',
            '001700_create_banners_table' => 'banners',
            '001800_create_galleries_table' => 'galleries',
            '001900_create_faqs_table' => 'faqs',
        ] as $suffix => $table) {
            $source = (string) file_get_contents(database_path("migrations/2026_10_02_{$suffix}.php"));

            $this->assertStringContainsString("Schema::dropIfExists('{$table}');", $source);
        }
    }

    // --- CHECK ---

    public function test_only_chk_banners_period_exists_as_a_named_check_on_the_content_tables(): void
    {
        $this->assertSame(['banners.chk_banners_period'], $this->namedChecks());
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

        $this->assertSame(0, DB::table('banners')->count());
    }

    public function test_banner_period_accepts_open_ended_and_strictly_ordered_windows(): void
    {
        $this->insertBanner();
        $this->insertBanner(['starts_at' => '2026-10-10 10:00:00']);
        $this->insertBanner(['ends_at' => '2026-10-10 10:00:00']);
        $this->insertBanner(['starts_at' => '2026-10-10 10:00:00', 'ends_at' => '2026-10-10 10:00:01']);
        $this->insertBanner(['starts_at' => null, 'ends_at' => null]);

        $this->assertSame(5, DB::table('banners')->count());
    }

    public function test_banner_period_is_also_enforced_on_update(): void
    {
        $id = $this->insertBanner(['starts_at' => '2026-10-10 10:00:00', 'ends_at' => '2026-10-11 10:00:00']);

        try {
            DB::table('banners')->where('id', $id)->update(['ends_at' => '2026-10-09 10:00:00']);
            $this->fail('Pembaruan yang melanggar periode seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('`chk_banners_period`', $e->getMessage());
        }
    }

    // --- Perilaku kendala dan default ---

    public function test_page_key_is_unique_and_case_insensitive(): void
    {
        $this->insertPage(['key' => 'about']);

        try {
            $this->insertPage(['key' => 'About']);
            $this->fail('Key halaman ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('pages_key_unique', $e->getMessage());
        }
    }

    public function test_page_defaults_and_medium_text_body_column(): void
    {
        $id = $this->insertPage(['body' => str_repeat('a', 70000)]);

        $row = DB::table('pages')->find($id);

        $this->assertSame(1, (int) $row->is_system);
        $this->assertNull($row->subtitle);
        $this->assertNull($row->meta_title);
        $this->assertNull($row->meta_description);
        $this->assertNull($row->updated_by);
        $this->assertSame(70000, strlen((string) $row->body), 'MEDIUMTEXT menampung lebih dari 65.535 byte.');
    }

    public function test_deleting_a_user_sets_pages_updated_by_to_null(): void
    {
        $user = User::factory()->create();
        $pageId = $this->insertPage(['updated_by' => $user->id]);

        DB::table('users')->where('id', $user->id)->delete();

        $this->assertNull(DB::table('pages')->where('id', $pageId)->value('updated_by'));
        $this->assertSame(1, DB::table('pages')->where('id', $pageId)->count());
    }

    public function test_article_category_slug_is_unique_but_name_is_not_at_database_level(): void
    {
        $this->insertCategory(['name' => 'Gizi', 'slug' => 'gizi']);
        $this->insertCategory(['name' => 'Gizi', 'slug' => 'gizi-2']);

        $this->assertSame(2, DB::table('article_categories')->where('name', 'Gizi')->count());

        $this->expectException(QueryException::class);

        $this->insertCategory(['name' => 'Gizi Tiga', 'slug' => 'GIZI']);
    }

    public function test_article_defaults(): void
    {
        $id = $this->insertArticle();

        $row = DB::table('articles')->find($id);

        $this->assertSame('draft', $row->status);
        $this->assertNull($row->published_at);
        $this->assertNull($row->article_category_id);
        $this->assertNull($row->author_user_id);
        $this->assertNull($row->deleted_at);
    }

    public function test_article_body_holds_more_than_the_text_column_limit(): void
    {
        $id = $this->insertArticle(['body' => str_repeat('b', 70000)]);

        $this->assertSame(70000, strlen((string) DB::table('articles')->where('id', $id)->value('body')));
    }

    public function test_article_slug_is_unique_even_when_the_other_row_is_soft_deleted(): void
    {
        $this->insertArticle(['slug' => 'judul-yang-sama', 'deleted_at' => '2026-10-05 10:00:00']);

        try {
            $this->insertArticle(['slug' => 'judul-yang-sama']);
            $this->fail('Slug artikel terhapus tidak boleh dipakai ulang.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('articles_slug_unique', $e->getMessage());
        }
    }

    public function test_article_slug_is_case_insensitive_unique(): void
    {
        $this->insertArticle(['slug' => 'pola-tidur']);

        $this->expectException(QueryException::class);

        $this->insertArticle(['slug' => 'Pola-Tidur']);
    }

    public function test_deleting_a_category_sets_the_article_category_to_null(): void
    {
        $categoryId = $this->insertCategory();
        $articleId = $this->insertArticle(['article_category_id' => $categoryId]);

        DB::table('article_categories')->where('id', $categoryId)->delete();

        $this->assertNull(DB::table('articles')->where('id', $articleId)->value('article_category_id'));
        $this->assertSame(1, DB::table('articles')->where('id', $articleId)->count());
    }

    public function test_deleting_the_author_sets_the_article_author_to_null(): void
    {
        $user = User::factory()->create();
        $articleId = $this->insertArticle(['author_user_id' => $user->id]);

        DB::table('users')->where('id', $user->id)->delete();

        $this->assertNull(DB::table('articles')->where('id', $articleId)->value('author_user_id'));
    }

    public function test_article_category_foreign_key_rejects_an_unknown_category(): void
    {
        $this->expectException(QueryException::class);

        $this->insertArticle(['article_category_id' => 999999]);
    }

    public function test_banner_defaults(): void
    {
        $row = DB::table('banners')->find($this->insertBanner());

        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame(0, (int) $row->sort_order);
        $this->assertNull($row->subtitle);
        $this->assertNull($row->button_label);
        $this->assertNull($row->button_url);
        $this->assertNull($row->starts_at);
        $this->assertNull($row->ends_at);
    }

    public function test_gallery_slug_is_unique_and_defaults_are_unpublished(): void
    {
        $row = DB::table('galleries')->find($this->insertGallery(['slug' => 'lobi']));

        $this->assertSame(0, (int) $row->is_published);
        $this->assertSame(0, (int) $row->sort_order);
        $this->assertNull($row->description);

        $this->expectException(QueryException::class);

        $this->insertGallery(['slug' => 'lobi']);
    }

    public function test_faq_defaults(): void
    {
        $row = DB::table('faqs')->find($this->insertFaq());

        $this->assertSame(0, (int) $row->is_published);
        $this->assertSame(0, (int) $row->sort_order);
        $this->assertSame('umum', $row->category);
    }

    public function test_datetime_columns_keep_the_wall_clock_value_without_conversion(): void
    {
        $id = $this->insertArticle(['status' => 'published', 'published_at' => '2026-10-05 23:30:00']);
        $bannerId = $this->insertBanner(['starts_at' => '2026-10-05 00:00:00', 'ends_at' => '2026-10-31 23:59:59']);

        $this->assertSame('2026-10-05 23:30:00', DB::table('articles')->where('id', $id)->value('published_at'));
        $this->assertSame('2026-10-05 00:00:00', DB::table('banners')->where('id', $bannerId)->value('starts_at'));
        $this->assertSame('2026-10-31 23:59:59', DB::table('banners')->where('id', $bannerId)->value('ends_at'));
    }
}
