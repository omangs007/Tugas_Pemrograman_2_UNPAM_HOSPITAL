<?php

namespace Tests\Feature\Staff;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordRules;
use App\Services\Audit\AuditLogger;
use App\Services\Staff\StaffAccountService;
use App\Support\Actor;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_update_changes_profile_and_role_and_audits_with_redacted_phone(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin', [
            'name' => 'Nama Lama',
            'email' => 'lama@unpamhospital.test',
            'phone' => '081111111111',
        ]);

        $updated = $this->service->update($target, [
            'name' => 'Nama Baru',
            'email' => 'baru@unpamhospital.test',
            'phone' => '082222222222',
            'role' => 'registration-officer',
        ], Actor::staff($admin));

        $fresh = $target->fresh();
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame('baru@unpamhospital.test', $fresh->email);
        $this->assertSame('082222222222', $fresh->phone);
        $this->assertSame(['registration-officer'], $fresh->roles()->pluck('name')->all());
        $this->assertSame(['registration-officer'], $updated->roles->pluck('name')->all());

        $log = $this->latestAudit('staff.updated');
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertEquals([
            'name' => 'Nama Lama',
            'email' => 'lama@unpamhospital.test',
            'phone' => AuditLogger::REDACTED,
            'role' => 'content-admin',
        ], $log->old_values);
        $this->assertEquals([
            'name' => 'Nama Baru',
            'email' => 'baru@unpamhospital.test',
            'phone' => AuditLogger::REDACTED,
            'role' => 'registration-officer',
        ], $log->new_values);

        $audits = DB::table('audit_logs')->get()->toJson();
        $this->assertStringNotContainsString('081111111111', $audits);
        $this->assertStringNotContainsString('082222222222', $audits);
    }

    public function test_update_without_any_change_writes_no_audit(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin');

        $this->service->update($target, [
            'name' => $target->name,
            'email' => $target->email,
            'phone' => $target->phone,
            'role' => 'content-admin',
        ], Actor::staff($admin));

        $this->assertSame(0, AuditLog::query()->where('action', 'staff.updated')->count());
    }

    public function test_staff_can_edit_their_own_profile_when_the_role_stays_the_same(): void
    {
        $admin = $this->createStaff('super-admin', ['name' => 'Admin Lama']);

        $this->service->update($admin, [
            'name' => 'Admin Baru',
            'email' => $admin->email,
            'phone' => null,
            'role' => 'super-admin',
        ], Actor::staff($admin));

        $this->assertSame('Admin Baru', $admin->fresh()->name);
        $this->assertSame(['super-admin'], $admin->roles()->pluck('name')->all());
    }

    public function test_staff_cannot_change_their_own_role_even_as_super_admin(): void
    {
        $admin = $this->createStaff('super-admin');
        $this->createStaff('super-admin');

        $this->assertRejected(
            fn () => $this->service->update($admin, [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => null,
                'role' => 'content-admin',
            ], Actor::staff($admin)),
            'role',
            'Anda tidak dapat mengubah peran akun Anda sendiri.',
        );

        $this->assertSame(['super-admin'], $admin->roles()->pluck('name')->all());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_staff_cannot_promote_themselves_to_super_admin(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->assertRejected(
            fn () => $this->service->update($staff, [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => null,
                'role' => 'super-admin',
            ], Actor::staff($staff)),
            'role',
            'Anda tidak dapat mengubah peran akun Anda sendiri.',
        );

        $this->assertSame(['content-admin'], $staff->roles()->pluck('name')->all());
    }

    public function test_with_two_super_admins_demoting_the_first_succeeds_and_the_second_is_rejected(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('super-admin');

        $this->service->update($second, [
            'name' => $second->name,
            'email' => $second->email,
            'phone' => null,
            'role' => 'content-admin',
        ], Actor::staff($first));
        $this->assertSame(['content-admin'], $second->roles()->pluck('name')->all());

        $this->assertRejected(
            fn () => $this->service->update($first, [
                'name' => $first->name,
                'email' => $first->email,
                'phone' => null,
                'role' => 'content-admin',
            ], Actor::staff($second)),
            'role',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->assertSame(['super-admin'], $first->roles()->pluck('name')->all());
    }

    public function test_inactive_super_admin_does_not_count_as_an_active_one(): void
    {
        $active = $this->createStaff('super-admin');
        $inactive = $this->createStaff('super-admin', ['is_active' => false]);

        $this->assertRejected(
            fn () => $this->service->update($active, [
                'name' => $active->name,
                'email' => $active->email,
                'phone' => null,
                'role' => 'content-admin',
            ], Actor::staff($inactive)),
            'role',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->service->update($inactive, [
            'name' => $inactive->name,
            'email' => $inactive->email,
            'phone' => null,
            'role' => 'content-admin',
        ], Actor::staff($active));

        $this->assertSame(['content-admin'], $inactive->roles()->pluck('name')->all());
    }

    public function test_reset_password_sets_new_temporary_password_forces_change_and_ends_sessions(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('registration-officer', ['must_change_password' => false]);
        $other = $this->createStaff('content-admin');
        $oldHash = $target->password;
        $this->insertSession('reset-target-1', $target->id);
        $this->insertSession('reset-target-2', $target->id);
        $this->insertSession('reset-lain', $other->id);

        $temporaryPassword = $this->service->resetPassword($target, Actor::staff($admin));

        $fresh = $target->fresh();
        $this->assertSame(12, strlen($temporaryPassword));
        $this->assertNotSame($oldHash, $fresh->password);
        $this->assertTrue(Hash::check($temporaryPassword, $fresh->password));
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());

        $log = $this->latestAudit('staff.password_reset');
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals(['must_change_password' => true], $log->new_values);
        $this->assertStringNotContainsString($temporaryPassword, DB::table('audit_logs')->get()->toJson());
    }

    public function test_staff_cannot_reset_their_own_password(): void
    {
        $admin = $this->createStaff('super-admin');
        $oldHash = $admin->password;

        $this->assertRejected(
            fn () => $this->service->resetPassword($admin, Actor::staff($admin)),
            'password',
            'Anda tidak dapat mengatur ulang password akun Anda sendiri. Gunakan menu Ganti Password.',
        );

        $this->assertSame($oldHash, $admin->fresh()->password);
        $this->assertSame(0, AuditLog::query()->count());
    }

    /** @return array<string, array{Closure}> */
    public static function patientOperations(): array
    {
        return [
            'update' => [fn (StaffAccountService $service, User $user) => $service->update($user, [
                'name' => 'Nama Pasien',
                'email' => 'pasien.ubah@unpamhospital.test',
                'role' => 'content-admin',
            ], Actor::system())],
            'resetPassword' => [fn (StaffAccountService $service, User $user) => $service->resetPassword($user, Actor::system())],
        ];
    }

    #[DataProvider('patientOperations')]
    public function test_update_and_reset_password_reject_patient_accounts(Closure $operation): void
    {
        $this->seedRoles();
        $patient = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $operation($this->service, $patient);
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
