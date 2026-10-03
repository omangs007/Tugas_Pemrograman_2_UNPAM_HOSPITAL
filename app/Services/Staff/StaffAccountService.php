<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Database\Seeders\RolePermissionSeeder;
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
            if ($staff->is_active === $active) {
                return $staff;
            }

            if (! $active) {
                if ($this->isSelf($staff, $actor)) {
                    throw ValidationException::withMessages(['is_active' => self::MSG_SELF_DEACTIVATE]);
                }

                if ($this->isSuperAdmin($staff)) {
                    $this->assertNotLastActiveSuperAdmin($staff, 'is_active');
                }
            }

            $staff->forceFill(['is_active' => $active])->save();

            if (! $active) {
                DB::table('sessions')->where('user_id', $staff->getKey())->delete();
            }

            $this->audit->log(
                $active ? 'staff.activated' : 'staff.deactivated',
                $staff,
                ['is_active' => ! $active],
                ['is_active' => $active],
                $active ? "Akun staf {$staff->name} diaktifkan kembali." : "Akun staf {$staff->name} dinonaktifkan.",
                $actor,
            );

            return $staff;
        });
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

    private function isSuperAdmin(User $staff): bool
    {
        $staff->loadMissing('roles');

        return $staff->roles->contains('name', self::SUPER_ADMIN);
    }

    /**
     * Harus dipanggil di dalam DB::transaction: baris Super Admin aktif dikunci
     * (lockForUpdate) sehingga dua permintaan serentak tidak dapat sama-sama menghabisinya.
     */
    private function assertNotLastActiveSuperAdmin(User $staff, string $field): void
    {
        $activeIds = User::role(self::SUPER_ADMIN)->where('is_active', true)->lockForUpdate()->pluck('id');

        if ($activeIds->count() <= 1 && $activeIds->contains($staff->getKey())) {
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
