<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan atas banner memerlukan izin `content.manage`. Banner tidak punya aturan
 * rujukan: hapus selalu permanen (media ikut terhapus).
 */
final class BannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, Banner $banner): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, Banner $banner): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $user->can('content.manage');
    }
}
