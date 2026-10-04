<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan memerlukan izin `clinics.manage`. Penolakan hapus karena data masih
 * dirujuk dilakukan controller lewat Clinic::deletionBlockedReason() (F2-R18).
 */
final class ClinicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function view(User $user, Clinic $clinic): bool
    {
        return $user->can('clinics.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function update(User $user, Clinic $clinic): bool
    {
        return $user->can('clinics.manage');
    }

    public function delete(User $user, Clinic $clinic): bool
    {
        return $user->can('clinics.manage');
    }
}
