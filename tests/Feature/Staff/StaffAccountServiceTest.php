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

    // --- Pembantu ---

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
