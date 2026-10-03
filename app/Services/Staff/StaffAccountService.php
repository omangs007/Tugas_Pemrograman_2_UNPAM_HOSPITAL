<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Menegakkan aturan mutlak 00 G / 11 §6.15 yang tidak boleh bergantung pada Policy
 * karena Gate::before melewati Policy bagi Super Admin: peran super-admin terkunci,
 * minimal satu Super Admin aktif, dan staf tidak dapat menonaktifkan, mengubah peran,
 * atau mengatur ulang password akunnya sendiri.
 */
final class StaffAccountService
{
    private const SUPER_ADMIN = 'super-admin';

    private const TEMPORARY_PASSWORD_LENGTH = 12;

    // Tanpa karakter yang mudah tertukar (0/O, 1/l/I) agar password sementara mudah dibacakan.
    private const PASSWORD_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';

    private const PASSWORD_DIGITS = '23456789';

    private const MSG_INVALID_ROLE = 'Peran tidak valid.';

    private const MSG_SELF_ROLE = 'Anda tidak dapat mengubah peran akun Anda sendiri.';

    private const MSG_SELF_DEACTIVATE = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';

    private const MSG_SELF_RESET = 'Anda tidak dapat mengatur ulang password akun Anda sendiri. Gunakan menu Ganti Password.';

    private const MSG_LAST_SUPER_ADMIN = 'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $validated  name, email, phone (opsional), role
     * @return array{0: User, 1: string} [akun staf, password sementara (ditampilkan sekali, tidak disimpan/diaudit)]
     */
    public function create(array $validated, Actor $actor): array
    {
        $role = $this->validRole($validated['role'] ?? null);
        $temporaryPassword = $this->generateTemporaryPassword();

        $staff = DB::transaction(function () use ($validated, $role, $temporaryPassword, $actor): User {
            $phone = $validated['phone'] ?? null;

            $staff = new User;
            $staff->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $phone === '' ? null : $phone,
                'password' => $temporaryPassword,
                'account_type' => AccountType::Staff,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();

            $staff->assignRole($role);

            $this->audit->log(
                'staff.created',
                $staff,
                null,
                ['name' => $staff->name, 'email' => $staff->email, 'role' => $role],
                "Akun staf {$staff->name} dibuat dengan peran ".__('roles.'.$role).'.',
                $actor,
            );

            return $staff;
        });

        return [$staff, $temporaryPassword];
    }

    public function generateTemporaryPassword(): string
    {
        $letters = self::PASSWORD_LETTERS;
        $digits = self::PASSWORD_DIGITS;
        $alphabet = $letters.$digits;

        $characters = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        while (count($characters) < self::TEMPORARY_PASSWORD_LENGTH) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Fisher–Yates dengan random_int agar posisi huruf/angka wajib tidak dapat ditebak.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    public function changeStatus(User $staff, bool $active, Actor $actor): User
    {
        $this->assertStaff($staff);

        return DB::transaction(function () use ($staff, $active, $actor): User {
            // Seluruh keputusan diambil dari baca ulang terkunci, bukan dari model yang dioper pemanggil.
            $locked = $this->lockTarget($staff);

            if ($locked->is_active === $active) {
                return $staff->refresh();
            }

            if (! $active) {
                if ($this->isSelf($locked, $actor)) {
                    throw ValidationException::withMessages(['is_active' => self::MSG_SELF_DEACTIVATE]);
                }

                if ($this->lockedRoleNames($locked)->contains(self::SUPER_ADMIN)) {
                    $this->assertNotLastActiveSuperAdmin($locked, 'is_active');
                }
            }

            $locked->forceFill(['is_active' => $active])->save();

            if (! $active) {
                DB::table('sessions')->where('user_id', $locked->getKey())->delete();
            }

            $this->audit->log(
                $active ? 'staff.activated' : 'staff.deactivated',
                $locked,
                ['is_active' => ! $active],
                ['is_active' => $active],
                $active ? "Akun staf {$locked->name} diaktifkan kembali." : "Akun staf {$locked->name} dinonaktifkan.",
                $actor,
            );

            return $staff->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $validated  name, email, phone (opsional), role
     */
    public function update(User $staff, array $validated, Actor $actor): User
    {
        $this->assertStaff($staff);
        $newRole = $this->validRole($validated['role'] ?? null);

        return DB::transaction(function () use ($staff, $validated, $newRole, $actor): User {
            // Seluruh keputusan diambil dari baca ulang terkunci, bukan dari model yang dioper pemanggil.
            $locked = $this->lockTarget($staff);
            $oldRole = $this->lockedRoleNames($locked)->first();
            $roleChanged = $oldRole !== $newRole;

            if ($roleChanged) {
                if ($this->isSelf($locked, $actor)) {
                    throw ValidationException::withMessages(['role' => self::MSG_SELF_ROLE]);
                }

                if ($oldRole === self::SUPER_ADMIN) {
                    $this->assertNotLastActiveSuperAdmin($locked, 'role');
                }
            }

            $before = [
                'name' => $locked->name,
                'email' => $locked->email,
                'phone' => $locked->phone,
                'role' => $oldRole,
            ];

            $phone = array_key_exists('phone', $validated) ? $validated['phone'] : $locked->phone;

            $locked->fill([
                'name' => $validated['name'] ?? $locked->name,
                'email' => $validated['email'] ?? $locked->email,
                'phone' => $phone === '' ? null : $phone,
            ])->save();

            if ($roleChanged) {
                $locked->syncRoles([$newRole]);
            }

            $after = [
                'name' => $locked->name,
                'email' => $locked->email,
                'phone' => $locked->phone,
                'role' => $newRole,
            ];

            $old = [];
            $new = [];

            foreach ($after as $key => $value) {
                if ($before[$key] !== $value) {
                    $old[$key] = $before[$key];
                    $new[$key] = $value;
                }
            }

            if ($old !== []) {
                // 'staff.*' bukan awalan PII di AuditLogger::redact(), jadi telepon disamarkan di sini.
                if (array_key_exists('phone', $old)) {
                    $old['phone'] = AuditLogger::REDACTED;
                    $new['phone'] = AuditLogger::REDACTED;
                }

                $this->audit->log(
                    'staff.updated',
                    $locked,
                    $old,
                    $new,
                    "Data akun staf {$locked->name} diperbarui.",
                    $actor,
                );
            }

            return $staff->refresh()->load('roles');
        });
    }

    public function resetPassword(User $staff, Actor $actor): string
    {
        $this->assertStaff($staff);

        if ($this->isSelf($staff, $actor)) {
            throw ValidationException::withMessages(['password' => self::MSG_SELF_RESET]);
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        DB::transaction(function () use ($staff, $temporaryPassword, $actor): void {
            $staff->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ])->save();

            DB::table('sessions')->where('user_id', $staff->getKey())->delete();

            $this->audit->log(
                'staff.password_reset',
                $staff,
                null,
                ['must_change_password' => true],
                "Password akun staf {$staff->name} diatur ulang.",
                $actor,
            );
        });

        return $temporaryPassword;
    }

    // --- Pembantu privat ---

    private function assertStaff(User $staff): void
    {
        if ($staff->account_type !== AccountType::Staff) {
            throw new InvalidArgumentException('Hanya akun staf yang dapat dikelola oleh StaffAccountService.');
        }
    }

    private function isSelf(User $staff, Actor $actor): bool
    {
        return $actor->userId !== null && $actor->userId === $staff->getKey();
    }

    /** Baca ulang terkunci (FOR UPDATE) baris target; harus dipanggil di dalam DB::transaction. */
    private function lockTarget(User $staff): User
    {
        return User::query()->whereKey($staff->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Nama peran target saat ini lewat baca terkunci berbasis join (current read), bukan relasi termuat.
     *
     * @return Collection<int, string>
     */
    private function lockedRoleNames(User $staff): Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $staff->getMorphClass())
            ->where('model_has_roles.model_id', $staff->getKey())
            ->lockForUpdate()
            ->pluck('roles.name');
    }

    /**
     * Harus dipanggil di dalam DB::transaction. Super Admin aktif dihitung dengan satu baca terkunci
     * berbasis JOIN users-model_has_roles-roles: FOR UPDATE pada join mengunci dan membaca terkini
     * (current read) baris ketiga tabel, berbeda dengan subquery yang dibaca dari snapshot transaksi.
     * Dengan begitu dua permintaan serentak tidak dapat sama-sama menghabisi Super Admin aktif.
     */
    private function assertNotLastActiveSuperAdmin(User $staff, string $field): void
    {
        $activeIds = DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', (new User)->getMorphClass());
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', self::SUPER_ADMIN)
            ->where('roles.guard_name', 'web')
            ->where('users.is_active', true)
            ->lockForUpdate()
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id);

        if ($activeIds->count() <= 1 && $activeIds->contains((int) $staff->getKey())) {
            throw ValidationException::withMessages([$field => self::MSG_LAST_SUPER_ADMIN]);
        }
    }

    private function validRole(mixed $role): string
    {
        if (! is_string($role) || ! array_key_exists($role, RolePermissionSeeder::ROLES)) {
            throw ValidationException::withMessages(['role' => self::MSG_INVALID_ROLE]);
        }

        return $role;
    }
}
