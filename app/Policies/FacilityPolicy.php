<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

/**
 * 04 §5 / 11 §8 (F4B-R15): seluruh kemampuan atas fasilitas memerlukan izin `facilities.manage`.
 * Fasilitas tidak dirujuk tabel lain sehingga tidak ada penolakan hapus (CRD-13).
 */
final class FacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('facilities.manage');
    }

    public function view(User $user, Facility $facility): bool
    {
        return $user->can('facilities.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('facilities.manage');
    }

    public function update(User $user, Facility $facility): bool
    {
        return $user->can('facilities.manage');
    }

    public function delete(User $user, Facility $facility): bool
    {
        return $user->can('facilities.manage');
    }
}
