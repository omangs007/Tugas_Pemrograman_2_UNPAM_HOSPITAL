<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoomCategory;
use App\Models\User;

/**
 * 04 §5 / 11 §8 (F4B-R15): daftar terbuka bagi `facilities.manage` ATAU `rooms.update-availability` (D-12);
 * struktur kategori (lihat, buat, ubah, hapus) memerlukan `facilities.manage`; ketersediaan memerlukan
 * `rooms.update-availability`. Kategori kamar tidak dirujuk tabel lain, jadi hapus tidak punya penolakan (CRD-13).
 */
final class RoomCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['facilities.manage', 'rooms.update-availability']);
    }

    public function view(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('facilities.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('facilities.manage');
    }

    public function update(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('facilities.manage');
    }

    public function delete(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('facilities.manage');
    }

    public function updateAvailability(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('rooms.update-availability');
    }
}
