<?php

namespace Tests\Feature\Audit;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private function logger(): AuditLogger
    {
        return app(AuditLogger::class);
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

    /**
     * Membuat baris audit langsung (tanpa AuditLogger) untuk tes skema/model.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function makeLog(string $action = 'uji.aksi', ?int $userId = null, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::query()->create([
            'actor_type' => ActorType::System,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => null,
            'description' => 'Uji.',
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    // ---------------------------------------------------------------- skema (09 §7.3)

    public function test_schema_audit_logs_table_matches_the_data_dictionary(): void
    {
        // nama => [type_name, tipe lengkap (null = tidak diperiksa), unsigned, nullable]
        $expected = [
            'id' => ['bigint', null, true, false],
            'actor_type' => ['varchar', 'varchar(10)', false, false],
            'user_id' => ['bigint', null, true, true],
            'action' => ['varchar', 'varchar(100)', false, false],
            'auditable_type' => ['varchar', 'varchar(255)', false, false],
            'auditable_id' => ['bigint', null, true, true],
            'description' => ['varchar', 'varchar(255)', false, false],
            'old_values' => ['longtext', null, false, true],
            'new_values' => ['longtext', null, false, true],
            'ip_address' => ['varchar', 'varchar(45)', false, true],
            'user_agent' => ['varchar', 'varchar(255)', false, true],
            'created_at' => ['timestamp', 'timestamp', false, false],
        ];

        $columns = $this->columnsOf('audit_logs');

        $this->assertSame(array_keys($expected), array_keys($columns));

        foreach ($expected as $name => [$typeName, $type, $unsigned, $nullable]) {
            $this->assertSame($typeName, $columns[$name]['type_name'], "type_name audit_logs.{$name}");

            if ($type !== null) {
                $this->assertSame($type, $columns[$name]['type'], "type audit_logs.{$name}");
            }

            $this->assertSame($unsigned, str_contains($columns[$name]['type'], 'unsigned'), "unsigned audit_logs.{$name}");
            $this->assertSame($nullable, $columns[$name]['nullable'], "nullable audit_logs.{$name}");
        }

        $this->assertTrue($columns['id']['auto_increment']);
        $this->assertStringStartsWith('current_timestamp', strtolower((string) $columns['created_at']['default']));
    }

    public function test_schema_audit_logs_indexes_use_the_dictionary_names(): void
    {
        $indexes = [];

        foreach (Schema::getIndexes('audit_logs') as $index) {
            $indexes[$index['name']] = $index;
        }

        $this->assertSame(['id'], $indexes['primary']['columns']);
        $this->assertTrue($indexes['primary']['primary']);

        $expected = [
            'audit_logs_auditable_type_auditable_id_index' => ['auditable_type', 'auditable_id'],
            'audit_logs_user_id_created_at_index' => ['user_id', 'created_at'],
            'audit_logs_action_created_at_index' => ['action', 'created_at'],
            'audit_logs_created_at_index' => ['created_at'],
        ];

        foreach ($expected as $name => $columns) {
            $this->assertArrayHasKey($name, $indexes, "Index {$name} belum ada.");
            $this->assertSame($columns, $indexes[$name]['columns'], $name);
            $this->assertFalse($indexes[$name]['unique'], $name);
        }
    }

    public function test_schema_user_foreign_key_nulls_the_user_on_delete(): void
    {
        $foreignKeys = Schema::getForeignKeys('audit_logs');

        $this->assertCount(1, $foreignKeys);
        $this->assertSame('audit_logs_user_id_foreign', $foreignKeys[0]['name']);
        $this->assertSame(['user_id'], $foreignKeys[0]['columns']);
        $this->assertSame('users', $foreignKeys[0]['foreign_table']);
        $this->assertSame(['id'], $foreignKeys[0]['foreign_columns']);
        $this->assertSame('set null', $foreignKeys[0]['on_delete']);
    }

    public function test_schema_deleting_the_user_keeps_the_audit_row_with_a_null_user(): void
    {
        $staff = User::factory()->staff()->create();
        $log = $this->makeLog('uji.fk', $staff->id);

        $this->assertSame($staff->id, AuditLog::query()->findOrFail($log->id)->user_id);

        DB::table('users')->where('id', $staff->id)->delete();

        $this->assertNull(AuditLog::query()->findOrFail($log->id)->user_id);
    }

    public function test_schema_json_columns_reject_invalid_json(): void
    {
        $this->expectException(QueryException::class);

        DB::table('audit_logs')->insert([
            'actor_type' => 'system',
            'action' => 'uji.json',
            'auditable_type' => User::class,
            'description' => 'Uji JSON tidak valid.',
            'old_values' => 'ini bukan json',
        ]);
    }

    public function test_schema_created_at_defaults_to_the_current_time(): void
    {
        DB::table('audit_logs')->insert([
            'actor_type' => 'system',
            'action' => 'uji.default',
            'auditable_type' => User::class,
            'description' => 'Uji default created_at.',
        ]);

        $createdAt = DB::table('audit_logs')->where('action', 'uji.default')->value('created_at');

        $this->assertNotNull($createdAt);
        $this->assertLessThan(120, abs(now()->diffInSeconds(CarbonImmutable::parse($createdAt), false)));
    }

    // ---------------------------------------------------------------- model AuditLog

    public function test_model_fillable_casts_and_timestamps_follow_the_blueprint(): void
    {
        $this->assertNull(AuditLog::UPDATED_AT);

        $model = new AuditLog;

        $this->assertNull($model->getUpdatedAtColumn());
        $this->assertSame('created_at', $model->getCreatedAtColumn());
        $this->assertSame([
            'actor_type',
            'user_id',
            'action',
            'auditable_type',
            'auditable_id',
            'description',
            'old_values',
            'new_values',
            'ip_address',
            'user_agent',
        ], $model->getFillable());
    }

    public function test_model_casts_and_relations_resolve_without_lazy_loading(): void
    {
        $staff = User::factory()->staff()->create();
        $log = AuditLog::query()->create([
            'actor_type' => ActorType::Staff,
            'user_id' => $staff->id,
            'action' => 'staff.created',
            'auditable_type' => $staff->getMorphClass(),
            'auditable_id' => $staff->getKey(),
            'description' => 'Akun staf dibuat.',
            'old_values' => null,
            'new_values' => ['name' => 'Budi'],
        ]);

        $loaded = AuditLog::query()->with(['user', 'auditable'])->findOrFail($log->id);

        $this->assertSame(ActorType::Staff, $loaded->actor_type);
        $this->assertNull($loaded->old_values);
        $this->assertSame(['name' => 'Budi'], $loaded->new_values);
        $this->assertInstanceOf(CarbonInterface::class, $loaded->created_at);
        $this->assertTrue($loaded->user->is($staff));
        $this->assertTrue($loaded->auditable->is($staff));
    }

    public function test_model_cannot_be_updated(): void
    {
        $log = $this->makeLog('uji.immutable');

        try {
            $log->update(['description' => 'Diubah.']);
            $this->fail('Pembaruan audit log seharusnya ditolak.');
        } catch (LogicException $e) {
            $this->assertSame('Audit log tidak dapat diubah atau dihapus.', $e->getMessage());
        }

        $this->assertSame('Uji.', AuditLog::query()->findOrFail($log->id)->description);
    }

    public function test_model_cannot_be_deleted(): void
    {
        $log = $this->makeLog('uji.immutable');

        try {
            $log->delete();
            $this->fail('Penghapusan audit log seharusnya ditolak.');
        } catch (LogicException $e) {
            $this->assertSame('Audit log tidak dapat diubah atau dihapus.', $e->getMessage());
        }

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_model_scope_for_action_matches_the_prefix_literally(): void
    {
        foreach (['staff.created', 'staff.updated', 'auth.staff_login', 'auth.staff_login_failed', 'auth.staffXlogin', 'settings.updated'] as $action) {
            $this->makeLog($action);
        }

        $actions = static fn (string $prefix): array => AuditLog::query()->forAction($prefix)->orderBy('id')->pluck('action')->all();

        $this->assertSame(['staff.created', 'staff.updated'], $actions('staff.'));
        $this->assertSame(['auth.staff_login', 'auth.staff_login_failed'], $actions('auth.staff_login'));
        $this->assertSame(['auth.staff_login_failed'], $actions('auth.staff_login_failed'));
        $this->assertSame(['settings.updated'], $actions('settings.'));
        $this->assertSame([], $actions('staff%'));
    }

    public function test_model_scope_between_filters_by_created_at_inclusively(): void
    {
        $first = $this->makeLog('uji.satu');
        $second = $this->makeLog('uji.dua');
        $third = $this->makeLog('uji.tiga');

        foreach ([[$first, '2026-09-01 10:00:00'], [$second, '2026-09-15 12:00:00'], [$third, '2026-09-30 23:59:59']] as [$log, $at]) {
            AuditLog::query()->whereKey($log->id)->update(['created_at' => $at]);
        }

        $ids = static fn ($from, $to): array => AuditLog::query()->between($from, $to)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$second->id, $third->id], $ids('2026-09-10', '2026-09-30'));
        $this->assertSame([$first->id], $ids(null, '2026-09-01'));
        $this->assertSame([$second->id, $third->id], $ids('2026-09-15 12:00:00', null));
        $this->assertSame(
            [$second->id],
            $ids(CarbonImmutable::parse('2026-09-15 12:00:00'), CarbonImmutable::parse('2026-09-15 12:00:00')),
        );
        $this->assertSame([$first->id, $second->id, $third->id], $ids(null, null));
    }

    // ---------------------------------------------------------------- log()

    public function test_log_constants_match_the_contract(): void
    {
        $this->assertSame('[disamarkan]', AuditLogger::REDACTED);
        $this->assertSame('[konten diubah]', AuditLogger::CONTENT_CHANGED);
        $this->assertSame('…[dipotong]', AuditLogger::TRUNCATED_SUFFIX);
    }

    public function test_log_records_the_authenticated_staff_as_actor(): void
    {
        $staff = User::factory()->staff()->create();
        $subject = User::factory()->staff()->create();

        $this->actingAs($staff);

        $log = $this->logger()->log('staff.deactivated', $subject, ['is_active' => true], ['is_active' => false], 'Akun staf dinonaktifkan.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::Staff, $fresh->actor_type);
        $this->assertSame($staff->id, $fresh->user_id);
        $this->assertSame('staff.deactivated', $fresh->action);
        $this->assertSame(User::class, $fresh->auditable_type);
        $this->assertSame($subject->id, $fresh->auditable_id);
        $this->assertSame('Akun staf dinonaktifkan.', $fresh->description);
        $this->assertSame(['is_active' => true], $fresh->old_values);
        $this->assertSame(['is_active' => false], $fresh->new_values);
        $this->assertNotNull($fresh->created_at);
    }

    public function test_log_records_the_authenticated_patient_as_actor(): void
    {
        $patient = User::factory()->create();

        $this->actingAs($patient);

        $log = $this->logger()->log('auth.password_changed', $patient, null, null, 'Pasien mengganti password.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::Patient, $fresh->actor_type);
        $this->assertSame($patient->id, $fresh->user_id);
    }

    public function test_log_without_a_logged_in_user_records_the_system_actor(): void
    {
        $log = $this->logger()->log('media.pruned', new User, null, ['rows' => 2], 'Pembersihan media.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::System, $fresh->actor_type);
        $this->assertNull($fresh->user_id);
    }

    public function test_log_prefers_the_explicit_actor_over_the_session_user(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff);

        $log = $this->logger()->log('staff.created', new User, null, ['name' => 'Baru'], 'Akun staf dibuat.', Actor::system());

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::System, $fresh->actor_type);
        $this->assertNull($fresh->user_id);
    }

    public function test_log_uses_the_model_morph_class_and_key(): void
    {
        $user = User::factory()->staff()->create();

        $log = $this->logger()->log('staff.updated', $user, null, ['name' => 'X'], 'Uji.', Actor::system());

        $this->assertSame($user->getMorphClass(), $log->auditable_type);
        $this->assertSame($user->getKey(), $log->auditable_id);
    }

    public function test_log_with_an_empty_model_stores_the_type_and_a_null_id(): void
    {
        $log = $this->logger()->log('report.exported', new User, null, ['row_count' => 3], 'Ekspor CSV.', Actor::system());

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(User::class, $fresh->auditable_type);
        $this->assertNull($fresh->auditable_id);
    }

    public function test_log_stores_null_for_missing_or_empty_value_arrays(): void
    {
        $first = $this->logger()->log('uji.kosong', new User, null, null, 'Uji.', Actor::system());
        $second = $this->logger()->log('uji.kosong', new User, [], [], 'Uji.', Actor::system());

        foreach ([$first, $second] as $log) {
            $this->assertNull(DB::table('audit_logs')->where('id', $log->id)->value('old_values'));
            $this->assertNull(DB::table('audit_logs')->where('id', $log->id)->value('new_values'));
        }
    }

    public function test_log_redacts_before_storing_old_and_new_values(): void
    {
        $user = User::factory()->staff()->create();

        $log = $this->logger()->log(
            'patient.updated',
            $user,
            ['phone' => '081200000001', 'full_name' => 'Budi Lama'],
            ['phone' => '081200000002', 'full_name' => 'Budi Baru'],
            'Profil pasien diubah.',
            Actor::system(),
        );

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(['phone' => '[disamarkan]', 'full_name' => 'Budi Lama'], $fresh->old_values);
        $this->assertSame(['phone' => '[disamarkan]', 'full_name' => 'Budi Baru'], $fresh->new_values);
    }

    public function test_log_truncates_the_description_to_255_characters(): void
    {
        $log = $this->logger()->log('uji.panjang', new User, null, null, str_repeat('é', 300), Actor::system());

        $stored = DB::table('audit_logs')->where('id', $log->id)->value('description');

        $this->assertSame(255, mb_strlen($stored));
        $this->assertSame(str_repeat('é', 255), $stored);
    }

    /** @return array<string, array{0: string}> */
    public static function invalidActions(): array
    {
        return [
            'kosong' => [''],
            'lebih dari 100 karakter' => [str_repeat('a', 101)],
        ];
    }

    #[DataProvider('invalidActions')]
    public function test_log_rejects_invalid_action_names(string $action): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->logger()->log($action, new User, null, null, 'Uji.', Actor::system());
    }

    public function test_log_does_not_swallow_write_errors(): void
    {
        $staff = User::factory()->staff()->create();
        $actor = Actor::staff($staff);

        DB::table('users')->where('id', $staff->id)->delete();

        $before = AuditLog::query()->count();

        try {
            $this->logger()->log('uji.gagal', new User, null, ['a' => 1], 'Pelaku sudah tidak ada.', $actor);
            $this->fail('Galat tulis (pelanggaran FK) seharusnya dilempar, bukan ditelan.');
        } catch (QueryException) {
            $this->assertSame($before, AuditLog::query()->count());
        }
    }

    // ---------------------------------------------------------------- konteks HTTP vs CLI (F1-R8)

    public function test_context_is_null_outside_routed_http_requests(): void
    {
        $log = $this->logger()->log('uji.cli', new User, null, ['a' => 1], 'Uji konteks CLI.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertNull($fresh->ip_address);
        $this->assertNull($fresh->user_agent);
    }

    public function test_context_records_ip_and_user_agent_inside_a_routed_request(): void
    {
        Route::get('/_uji/audit-http', function () {
            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji konteks HTTP.');

            return response()->json(['id' => $log->id]);
        });

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeader('User-Agent', 'UjiAgen/1.0 (Windows)')
            ->getJson('/_uji/audit-http');

        $response->assertOk();

        $fresh = AuditLog::query()->findOrFail($response->json('id'));

        $this->assertSame('203.0.113.9', $fresh->ip_address);
        $this->assertSame('UjiAgen/1.0 (Windows)', $fresh->user_agent);
        $this->assertSame(ActorType::System, $fresh->actor_type);
    }

    public function test_context_takes_the_actor_from_the_session_inside_a_routed_request(): void
    {
        Route::get('/_uji/audit-http-staf', function () {
            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji pelaku dari sesi.');

            return response()->json(['id' => $log->id]);
        });

        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff)->getJson('/_uji/audit-http-staf');

        $fresh = AuditLog::query()->findOrFail($response->json('id'));

        $this->assertSame(ActorType::Staff, $fresh->actor_type);
        $this->assertSame($staff->id, $fresh->user_id);
        $this->assertNotNull($fresh->ip_address);
    }

    public function test_context_strips_control_characters_and_limits_the_user_agent(): void
    {
        Route::get('/_uji/audit-ua', function () {
            request()->headers->set('User-Agent', "Uji\x07Agen\x1B[31m".str_repeat('a', 300));

            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji user agent.');

            return response()->json(['id' => $log->id]);
        });

        $response = $this->getJson('/_uji/audit-ua');

        $stored = AuditLog::query()->findOrFail($response->json('id'))->user_agent;
        $prefix = 'UjiAgen[31m';

        $this->assertSame(255, strlen($stored));
        $this->assertSame($prefix.str_repeat('a', 255 - strlen($prefix)), $stored);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $stored);
    }

    public function test_context_stores_a_null_user_agent_when_the_header_is_missing(): void
    {
        Route::get('/_uji/audit-tanpa-ua', function () {
            request()->headers->remove('User-Agent');

            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji tanpa user agent.');

            return response()->json(['id' => $log->id]);
        });

        $response = $this->getJson('/_uji/audit-tanpa-ua');

        $fresh = AuditLog::query()->findOrFail($response->json('id'));

        $this->assertNull($fresh->user_agent);
        $this->assertNotNull($fresh->ip_address);
    }

    // ---------------------------------------------------------------- redact()

    public function test_redact_always_masks_secret_keys_case_insensitively_and_recursively(): void
    {
        $result = $this->logger()->redact([
            'Password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'CURRENT_PASSWORD' => 'Lama123',
            'remember_token' => 'abc',
            'token' => 'xyz',
            'bpjs_number' => '0009900000011',
            'payment_reference' => 'PAY-1',
            'name' => 'Budi',
            'nested' => ['TOKEN' => 'abc', 'deeper' => ['Bpjs_Number' => '0009900000011', 'ok' => 1]],
            'list' => [['password' => 'x', 'label' => 'a']],
        ], 'staff.updated');

        $this->assertSame([
            'Password' => '[disamarkan]',
            'password_confirmation' => '[disamarkan]',
            'CURRENT_PASSWORD' => '[disamarkan]',
            'remember_token' => '[disamarkan]',
            'token' => '[disamarkan]',
            'bpjs_number' => '[disamarkan]',
            'payment_reference' => '[disamarkan]',
            'name' => 'Budi',
            'nested' => ['TOKEN' => '[disamarkan]', 'deeper' => ['Bpjs_Number' => '[disamarkan]', 'ok' => 1]],
            'list' => [['password' => '[disamarkan]', 'label' => 'a']],
        ], $result);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function piiActions(): array
    {
        return [
            'patient.updated' => ['patient.updated', true],
            'patient.status_changed' => ['patient.status_changed', true],
            'appointment.created' => ['appointment.created', true],
            'contact_message.status_changed' => ['contact_message.status_changed', true],
            'staff.updated' => ['staff.updated', false],
            'auth.staff_login_failed' => ['auth.staff_login_failed', false],
            'settings.updated' => ['settings.updated', false],
            'patients.exported (awalan mirip)' => ['patients.exported', false],
            'appointments.exported (awalan mirip)' => ['appointments.exported', false],
        ];
    }

    #[DataProvider('piiActions')]
    public function test_redact_masks_pii_only_for_patient_appointment_and_contact_message_actions(string $action, bool $masked): void
    {
        $values = [
            'address' => 'Jl. Melati No. 1',
            'phone' => '081200000001',
            'email' => 'budi@unpamhospital.test',
            'birth_date' => '1990-01-31',
            'complaint' => 'Sakit kepala',
            'message' => 'Mohon info jadwal',
            'full_name' => 'Budi Santoso',
        ];

        $result = $this->logger()->redact($values, $action);

        foreach (['address', 'phone', 'email', 'birth_date', 'complaint', 'message'] as $key) {
            $this->assertSame($masked ? '[disamarkan]' : $values[$key], $result[$key], "{$action} / {$key}");
        }

        $this->assertSame('Budi Santoso', $result['full_name']);
    }

    public function test_redact_records_long_text_fields_as_changed_content_only(): void
    {
        $result = $this->logger()->redact([
            'body' => '<p>Isi halaman yang panjang</p>',
            'BIO' => 'Riwayat dokter',
            'short_bio' => 'Ringkas',
            'description' => 'Deskripsi poliklinik',
            'title' => 'Judul tetap tampil',
        ], 'page.updated');

        $this->assertSame([
            'body' => '[konten diubah]',
            'BIO' => '[konten diubah]',
            'short_bio' => '[konten diubah]',
            'description' => '[konten diubah]',
            'title' => 'Judul tetap tampil',
        ], $result);
    }

    public function test_redact_masks_hidden_and_encrypted_attributes_of_the_model(): void
    {
        $model = new class extends Model
        {
            protected $hidden = ['Secret_Note'];

            protected $casts = ['api_key' => 'encrypted'];
        };

        $values = ['secret_note' => 'rahasia', 'api_key' => 'kunci-api', 'ok' => 'ya'];

        $this->assertSame(
            ['secret_note' => '[disamarkan]', 'api_key' => '[disamarkan]', 'ok' => 'ya'],
            $this->logger()->redact($values, 'uji.aksi', $model),
        );

        $this->assertSame($values, $this->logger()->redact($values, 'uji.aksi'));
    }

    public function test_redact_masks_the_hidden_attributes_of_user(): void
    {
        $result = $this->logger()->redact(['password' => 'hash', 'remember_token' => 'abc', 'name' => 'Budi'], 'staff.created', new User);

        $this->assertSame(['password' => '[disamarkan]', 'remember_token' => '[disamarkan]', 'name' => 'Budi'], $result);
    }

    public function test_redact_truncates_strings_longer_than_500_characters(): void
    {
        $result = $this->logger()->redact([
            'panjang' => str_repeat('a', 501),
            'pas' => str_repeat('b', 500),
            'multibyte' => str_repeat('é', 600),
            'nested' => ['catatan' => str_repeat('c', 700)],
        ], 'uji.aksi');

        $this->assertSame(str_repeat('a', 500).'…[dipotong]', $result['panjang']);
        $this->assertSame(str_repeat('b', 500), $result['pas']);
        $this->assertSame(str_repeat('é', 500).'…[dipotong]', $result['multibyte']);
        $this->assertSame(str_repeat('c', 500).'…[dipotong]', $result['nested']['catatan']);
    }

    public function test_redact_normalizes_enums_and_dates_and_keeps_scalars(): void
    {
        $result = $this->logger()->redact([
            'status' => AppointmentStatus::Confirmed,
            'at' => CarbonImmutable::parse('2026-10-03 08:15:30'),
            'native' => new DateTimeImmutable('2026-10-03 09:00:00'),
            'ids' => [1, 2],
            'active' => false,
            'count' => 0,
            'ratio' => 1.5,
            'nothing' => null,
            'list' => [PaymentType::Bpjs, ['x' => SessionStatus::Open]],
        ], 'session.updated');

        $this->assertSame([
            'status' => 'confirmed',
            'at' => '2026-10-03 08:15:30',
            'native' => '2026-10-03 09:00:00',
            'ids' => [1, 2],
            'active' => false,
            'count' => 0,
            'ratio' => 1.5,
            'nothing' => null,
            'list' => ['bpjs', ['x' => 'open']],
        ], $result);
    }

    public function test_redact_of_an_empty_array_is_empty(): void
    {
        $this->assertSame([], $this->logger()->redact([], 'uji.aksi'));
    }

    // ---------------------------------------------------------------- diff()

    public function test_diff_after_an_update_returns_previous_and_new_values_without_updated_at(): void
    {
        $user = User::factory()->create(['name' => 'Nama Lama']);

        $user->name = 'Nama Baru';
        $user->save();

        $this->assertSame(
            ['old' => ['name' => 'Nama Lama'], 'new' => ['name' => 'Nama Baru']],
            $this->logger()->diff($user),
        );
    }

    public function test_diff_before_saving_uses_the_dirty_attributes(): void
    {
        $user = User::query()->findOrFail(User::factory()->create(['name' => 'Nama Lama'])->id);

        $user->name = 'Nama Baru';
        $user->phone = '081200000001';

        $this->assertSame(
            ['old' => ['name' => 'Nama Lama', 'phone' => null], 'new' => ['name' => 'Nama Baru', 'phone' => '081200000001']],
            $this->logger()->diff($user),
        );
    }

    public function test_diff_is_empty_when_nothing_changed(): void
    {
        $user = User::query()->findOrFail(User::factory()->create()->id);

        $this->assertSame(['old' => [], 'new' => []], $this->logger()->diff($user));

        $user->save();

        $this->assertSame(['old' => [], 'new' => []], $this->logger()->diff($user));
    }

    // ---------------------------------------------------------------- SEK-11

    public function test_sek11_sensitive_values_never_reach_the_audit_logs_table(): void
    {
        $staff = User::factory()->staff()->create(['password' => 'Rahasia123']);
        $logger = $this->logger();

        $logger->log(
            'staff.password_reset',
            $staff,
            ['password' => 'Rahasia123', 'remember_token' => 'TokenRahasia777'],
            [
                'password' => 'Rahasia456',
                'password_confirmation' => 'Rahasia456',
                'current_password' => 'Rahasia123',
                'nested' => ['token' => 'Rahasia123', 'items' => [['bpjs_number' => '0009900000011']]],
            ],
            'Super Admin mengatur ulang password staf.',
            Actor::staff($staff),
        );

        $logger->log(
            'patient.updated',
            $staff,
            null,
            [
                'bpjs_number' => '0009900000011',
                'payment_reference' => 'PAY-0009900000011',
                'address' => 'Jl. Rahasia123 No. 1',
                'phone' => '081200000099',
                'email' => 'pasien.rahasia@unpamhospital.test',
                'complaint' => 'Keluhan Rahasia123',
            ],
            'Profil pasien diubah.',
            Actor::system(),
        );

        $secrets = ['Rahasia123', 'Rahasia456', 'TokenRahasia777', '0009900000011', 'PAY-0009900000011', '081200000099', 'pasien.rahasia@'];
        $everything = json_encode(DB::table('audit_logs')->get()->all(), JSON_UNESCAPED_UNICODE);

        foreach ($secrets as $secret) {
            $count = AuditLog::query()
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->count();

            $this->assertSame(0, $count, "Nilai sensitif '{$secret}' ditemukan di audit_logs.");
            $this->assertStringNotContainsString($secret, $everything, "Nilai sensitif '{$secret}' bocor ke baris audit_logs.");
        }

        $first = AuditLog::query()->where('action', 'staff.password_reset')->firstOrFail();
        $second = AuditLog::query()->where('action', 'patient.updated')->firstOrFail();

        $this->assertSame('[disamarkan]', $first->old_values['password']);
        $this->assertSame('[disamarkan]', $first->old_values['remember_token']);
        $this->assertSame('[disamarkan]', $first->new_values['password']);
        $this->assertSame('[disamarkan]', $first->new_values['password_confirmation']);
        $this->assertSame('[disamarkan]', $first->new_values['current_password']);
        $this->assertSame('[disamarkan]', $first->new_values['nested']['token']);
        $this->assertSame('[disamarkan]', $first->new_values['nested']['items'][0]['bpjs_number']);

        foreach (['bpjs_number', 'payment_reference', 'address', 'phone', 'email', 'complaint'] as $key) {
            $this->assertSame('[disamarkan]', $second->new_values[$key], $key);
        }
    }
}
