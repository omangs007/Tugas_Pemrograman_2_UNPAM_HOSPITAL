<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Specialty;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan memerlukan izin `clinics.manage`. Penolakan hapus karena data masih
 * dirujuk dilakukan controller lewat Specialty::deletionBlockedReason() (F2-R18), bukan di Policy,
 * agar berlaku juga untuk Super Admin (Gate::before melewati Policy).
 */
final class SpecialtyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function view(User $user, Specialty $specialty): bool
    {
        return $user->can('clinics.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function update(User $user, Specialty $specialty): bool
    {
        return $user->can('clinics.manage');
    }

    public function delete(User $user, Specialty $specialty): bool
    {
        return $user->can('clinics.manage');
    }
}
