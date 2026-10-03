<?php

namespace Tests\Feature\Models;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 09-data-dictionary.md §2.1.
     * Kolom: nama kolom, type_name, tipe lengkap, nullable, default (setelah normalisasi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>
     */
    public static function expectedUserColumns(): array
    {
        return [
            'name' => ['name', 'varchar', 'varchar(255)', false, null],
            'email' => ['email', 'varchar', 'varchar(255)', false, null],
            'phone' => ['phone', 'varchar', 'varchar(20)', true, null],
            'email_verified_at' => ['email_verified_at', 'timestamp', 'timestamp', true, null],
            'password' => ['password', 'varchar', 'varchar(255)', false, null],
            'account_type' => ['account_type', 'varchar', 'varchar(10)', false, null],
            'is_active' => ['is_active', 'tinyint', 'tinyint(1)', false, '1'],
            'must_change_password' => ['must_change_password', 'tinyint', 'tinyint(1)', false, '0'],
            'last_login_at' => ['last_login_at', 'datetime', 'datetime', true, null],
            'remember_token' => ['remember_token', 'varchar', 'varchar(100)', true, null],
            'created_at' => ['created_at', 'timestamp', 'timestamp', true, null],
            'updated_at' => ['updated_at', 'timestamp', 'timestamp', true, null],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function guardedAccountFields(): array
    {
        return [
            'account_type' => ['account_type', 'staff'],
            'is_active' => ['is_active', false],
            'must_change_password' => ['must_change_password', true],
            'last_login_at' => ['last_login_at', '2026-10-03 08:00:00'],
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

    #[DataProvider('expectedUserColumns')]
    public function test_schema_users_column_matches_the_data_dictionary(string $name, string $typeName, string $type, bool $nullable, ?string $default): void
    {
        $columns = $this->columnsOf('users');

        $this->assertArrayHasKey($name, $columns, "Kolom users.{$name} belum ada.");
        $this->assertSame($typeName, $columns[$name]['type_name'], "type_name users.{$name}");
        $this->assertSame($type, $columns[$name]['type'], "type users.{$name}");
        $this->assertSame($nullable, $columns[$name]['nullable'], "nullable users.{$name}");
        $this->assertSame($default, $this->normalizedDefault($columns[$name]['default']), "default users.{$name}");
    }

    public function test_schema_users_primary_key_and_new_columns_exist(): void
    {
        $columns = $this->columnsOf('users');

        $this->assertSame('bigint', $columns['id']['type_name']);
        $this->assertStringContainsString('unsigned', $columns['id']['type']);
        $this->assertTrue($columns['id']['auto_increment']);

        $this->assertTrue(Schema::hasColumns('users', ['phone', 'account_type', 'is_active', 'must_change_password', 'last_login_at']));
    }

    public function test_schema_users_email_stays_unique(): void
    {
        $indexes = [];

        foreach (Schema::getIndexes('users') as $index) {
            $indexes[$index['name']] = $index;
        }

        $this->assertArrayHasKey('users_email_unique', $indexes);
        $this->assertTrue($indexes['users_email_unique']['unique']);
        $this->assertSame(['email'], $indexes['users_email_unique']['columns']);
    }

    public function test_schema_notifications_table_is_the_laravel_default(): void
    {
        $columns = $this->columnsOf('notifications');

        $this->assertSame(
            ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'],
            array_keys($columns),
        );

        $this->assertSame('char(36)', $columns['id']['type']);
        $this->assertSame('varchar(255)', $columns['type']['type']);
        $this->assertSame('varchar(255)', $columns['notifiable_type']['type']);
        $this->assertSame('bigint', $columns['notifiable_id']['type_name']);
        $this->assertStringContainsString('unsigned', $columns['notifiable_id']['type']);
        $this->assertSame('text', $columns['data']['type_name']);
        $this->assertFalse($columns['data']['nullable']);
        $this->assertTrue($columns['read_at']['nullable']);

        $indexes = [];

        foreach (Schema::getIndexes('notifications') as $index) {
            $indexes[$index['name']] = $index;
        }

        $this->assertTrue($indexes['primary']['primary']);
        $this->assertSame(['id'], $indexes['primary']['columns']);
        $this->assertSame(
            ['notifiable_type', 'notifiable_id'],
            $indexes['notifications_notifiable_type_notifiable_id_index']['columns'] ?? null,
        );
    }

    public function test_password_is_stored_as_a_bcrypt_hash_and_never_serialized(): void
    {
        $user = User::factory()->create(['password' => 'Rahasia123']);

        $stored = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame('Rahasia123', $stored);
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check('Rahasia123', $stored));
        $this->assertFalse(Hash::check('Rahasia124', $stored));

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertStringNotContainsString('Rahasia123', $user->toJson());
        $this->assertStringNotContainsString($stored, $user->toJson());
    }

    #[DataProvider('guardedAccountFields')]
    public function test_account_fields_are_not_mass_assignable(string $field, mixed $value): void
    {
        $this->expectException(MassAssignmentException::class);

        User::create([
            'name' => 'Uji Massal',
            'email' => 'massal@unpamhospital.test',
            'password' => 'Rahasia123',
            $field => $value,
        ]);
    }

    public function test_fillable_and_hidden_follow_the_blueprint(): void
    {
        $user = new User;

        $this->assertSame(['name', 'email', 'phone', 'password'], $user->getFillable());
        $this->assertSame(['password', 'remember_token'], $user->getHidden());
    }

    public function test_casts_follow_the_blueprint(): void
    {
        $user = User::factory()->staff()->mustChangePassword()->create();
        $user->forceFill(['last_login_at' => '2026-10-03 08:15:00'])->save();

        $fresh = User::query()->findOrFail($user->id);

        $this->assertSame(AccountType::Staff, $fresh->account_type);
        $this->assertSame(true, $fresh->is_active);
        $this->assertSame(true, $fresh->must_change_password);
        $this->assertInstanceOf(CarbonInterface::class, $fresh->email_verified_at);
        $this->assertInstanceOf(CarbonInterface::class, $fresh->last_login_at);
        $this->assertSame('2026-10-03 08:15:00', $fresh->last_login_at->format('Y-m-d H:i:s'));
    }

    public function test_factory_defaults_to_an_active_patient(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AccountType::Patient, $user->account_type);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->phone);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_factory_states(): void
    {
        $this->assertSame(AccountType::Staff, User::factory()->staff()->create()->account_type);
        $this->assertSame(AccountType::Patient, User::factory()->staff()->patient()->create()->account_type);
        $this->assertFalse(User::factory()->inactive()->create()->is_active);
        $this->assertTrue(User::factory()->mustChangePassword()->create()->must_change_password);
        $this->assertNull(User::factory()->unverified()->create()->email_verified_at);

        $combined = User::factory()->staff()->inactive()->mustChangePassword()->create();

        $this->assertSame(AccountType::Staff, $combined->account_type);
        $this->assertFalse($combined->is_active);
        $this->assertTrue($combined->must_change_password);
    }

    public function test_scopes_filter_by_account_type_and_activity(): void
    {
        User::factory()->staff()->count(2)->create();
        User::factory()->count(3)->create();
        User::factory()->staff()->inactive()->create();

        $this->assertSame(3, User::query()->staff()->count());
        $this->assertSame(3, User::query()->patients()->count());
        $this->assertSame(5, User::query()->active()->count());
        $this->assertSame(2, User::query()->staff()->active()->count());
    }

    public function test_is_staff_and_is_patient_helpers(): void
    {
        $staff = User::factory()->staff()->create();
        $patient = User::factory()->patient()->create();

        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isPatient());
        $this->assertTrue($patient->isPatient());
        $this->assertFalse($patient->isStaff());
    }

    public function test_actor_type_from_user_follows_account_type(): void
    {
        $this->assertSame(ActorType::Staff, ActorType::fromUser(User::factory()->staff()->create()));
        $this->assertSame(ActorType::Patient, ActorType::fromUser(User::factory()->create()));
        $this->assertSame(ActorType::Patient, ActorType::fromUser(User::factory()->patient()->inactive()->create()));
    }

    public function test_strict_mode_is_enabled_outside_production(): void
    {
        $this->assertFalse($this->app->isProduction());
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertTrue(Model::preventsSilentlyDiscardingAttributes());
        $this->assertTrue(Model::preventsAccessingMissingAttributes());
    }

    public function test_lazy_loading_a_relation_on_a_collection_is_rejected(): void
    {
        User::factory()->count(2)->create();

        $this->expectException(LazyLoadingViolationException::class);

        User::query()->get()->each(fn (User $user) => $user->notifications);
    }

    public function test_reading_a_column_that_was_not_selected_is_rejected(): void
    {
        $user = User::factory()->create();

        $partial = User::query()->select('id')->findOrFail($user->id);

        $this->expectException(MissingAttributeException::class);

        $partial->email;
    }
}
