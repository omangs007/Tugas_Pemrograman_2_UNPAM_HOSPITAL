<?php

namespace Tests\Feature\Staff;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordRules;
use App\Services\Staff\StaffAccountService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class StaffAccountServiceTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private StaffAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(StaffAccountService::class);
    }

    public function test_generated_temporary_password_has_12_letters_and_digits_and_passes_password_rules(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $password = $this->service->generateTemporaryPassword();

            $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{12}\z/', $password);
            $this->assertMatchesRegularExpression('/[A-Za-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);

            $validator = Validator::make(
                ['password' => $password, 'password_confirmation' => $password],
                ['password' => PasswordRules::default()],
            );

            $this->assertTrue($validator->passes(), "Password sementara {$password} harus lolos PasswordRules.");
        }

        $this->assertNotSame($this->service->generateTemporaryPassword(), $this->service->generateTemporaryPassword());
    }

    public function test_create_makes_active_staff_with_hashed_temporary_password_and_role(): void
    {
        $this->seedRoles();
        $admin = $this->createStaff('super-admin');

        [$staff, $temporaryPassword] = $this->service->create([
            'name' => 'Rina Wulandari',
            'email' => 'rina@unpamhospital.test',
            'phone' => '081234567890',
            'role' => 'content-admin',
        ], Actor::staff($admin));

        $fresh = $staff->fresh();

        $this->assertSame(AccountType::Staff, $fresh->account_type);
        $this->assertTrue($fresh->is_active);
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame('Rina Wulandari', $fresh->name);
        $this->assertSame('081234567890', $fresh->phone);
        $this->assertSame(12, strlen($temporaryPassword));
        $this->assertStringStartsWith('$2y$', $fresh->password);
        $this->assertNotSame($temporaryPassword, $fresh->password);
        $this->assertTrue(Hash::check($temporaryPassword, $fresh->password));
        $this->assertSame(['content-admin'], $fresh->roles()->pluck('name')->all());
    }

    public function test_create_without_phone_stores_null(): void
    {
        $this->seedRoles();

        [$staff] = $this->service->create([
            'name' => 'Tanpa Telepon',
            'email' => 'tanpa.telepon@unpamhospital.test',
            'role' => 'registration-officer',
        ], Actor::system());

        $this->assertNull($staff->fresh()->phone);
    }

    public function test_create_writes_audit_without_the_temporary_password(): void
    {
        $admin = $this->createStaff('super-admin');

        [$staff, $temporaryPassword] = $this->service->create([
            'name' => 'Petugas Baru',
            'email' => 'petugas.baru@unpamhospital.test',
            'phone' => '081200000000',
            'role' => 'registration-officer',
        ], Actor::staff($admin));

        $log = AuditLog::query()->where('action', 'staff.created')->sole();

        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame((new User)->getMorphClass(), $log->auditable_type);
        $this->assertSame($staff->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'name' => 'Petugas Baru',
            'email' => 'petugas.baru@unpamhospital.test',
            'role' => 'registration-officer',
        ], $log->new_values);
        $this->assertStringNotContainsString($temporaryPassword, DB::table('audit_logs')->get()->toJson());
    }

    public function test_create_rejects_unknown_role_without_creating_anything(): void
    {
        $this->seedRoles();

        $this->assertRejected(
            fn () => $this->service->create([
                'name' => 'Peran Salah',
                'email' => 'peran.salah@unpamhospital.test',
                'role' => 'dokter',
            ], Actor::system()),
            'role',
            'Peran tidak valid.',
        );

        $this->assertDatabaseMissing('users', ['email' => 'peran.salah@unpamhospital.test']);
        $this->assertSame(0, AuditLog::query()->where('action', 'staff.created')->count());
    }

    public function test_create_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $this->seedRoles();
        AuditLog::creating(fn () => throw new RuntimeException('Gagal menulis audit.'));

        $thrown = null;

        try {
            $this->service->create([
                'name' => 'Batal Dibuat',
                'email' => 'batal@unpamhospital.test',
                'role' => 'content-admin',
            ], Actor::system());
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Galat audit tidak boleh ditelan.');
        $this->assertSame('Gagal menulis audit.', $thrown->getMessage());
        $this->assertDatabaseMissing('users', ['email' => 'batal@unpamhospital.test']);
    }

    public function test_deactivating_sets_inactive_deletes_only_that_users_sessions_and_audits(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin');
        $other = $this->createStaff('registration-officer');
        $this->insertSession('sesi-target-1', $target->id);
        $this->insertSession('sesi-target-2', $target->id);
        $this->insertSession('sesi-lain', $other->id);

        $result = $this->service->changeStatus($target, false, Actor::staff($admin));

        $this->assertFalse($result->is_active);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());

        $log = $this->latestAudit('staff.deactivated');
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertEquals(['is_active' => true], $log->old_values);
        $this->assertEquals(['is_active' => false], $log->new_values);
    }

    public function test_activating_sets_active_and_audits(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin', ['is_active' => false]);

        $result = $this->service->changeStatus($target, true, Actor::staff($admin));

        $this->assertTrue($result->is_active);
        $this->assertTrue($target->fresh()->is_active);

        $log = $this->latestAudit('staff.activated');
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertEquals(['is_active' => false], $log->old_values);
        $this->assertEquals(['is_active' => true], $log->new_values);
    }

    public function test_changing_to_the_same_status_is_a_no_op_without_audit(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin');

        $this->service->changeStatus($target, true, Actor::staff($admin));

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_staff_cannot_deactivate_their_own_account_even_as_super_admin(): void
    {
        $admin = $this->createStaff('super-admin');
        $this->createStaff('super-admin');

        $this->assertRejected(
            fn () => $this->service->changeStatus($admin, false, Actor::staff($admin)),
            'is_active',
            'Anda tidak dapat menonaktifkan akun Anda sendiri.',
        );

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_with_two_super_admins_the_first_deactivation_succeeds_and_the_second_is_rejected(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('super-admin');

        $this->service->changeStatus($second, false, Actor::staff($first));
        $this->assertFalse($second->fresh()->is_active);

        $this->assertRejected(
            fn () => $this->service->changeStatus($first, false, Actor::staff($second)),
            'is_active',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->assertTrue($first->fresh()->is_active);
        $this->assertSame(1, AuditLog::query()->where('action', 'staff.deactivated')->count());
    }

    public function test_sole_super_admin_cannot_be_deactivated_by_another_staff(): void
    {
        $only = $this->createStaff('super-admin');
        $officer = $this->createStaff('registration-officer');

        $this->assertRejected(
            fn () => $this->service->changeStatus($only, false, Actor::staff($officer)),
            'is_active',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->assertTrue($only->fresh()->is_active);
    }

    public function test_last_super_admin_check_runs_inside_a_row_lock(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('super-admin');

        DB::enableQueryLog();
        $this->service->changeStatus($second, false, Actor::staff($first));
        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $sql): string => strtolower($sql));
        DB::disableQueryLog();

        $this->assertTrue(
            $queries->contains(fn (string $sql): bool => str_contains($sql, 'for update')),
            'Pemeriksaan Super Admin aktif terakhir harus memakai lockForUpdate().',
        );
    }

    public function test_change_status_rejects_patient_accounts(): void
    {
        $patient = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->changeStatus($patient, false, Actor::system());
    }

    // --- Pembantu ---

    private function latestAudit(string $action): AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);
    }

    private function assertRejected(callable $action, string $field, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertSame([$field => [$message]], $e->errors());

            return;
        }

        $this->fail('ValidationException tidak dilempar.');
    }
}
