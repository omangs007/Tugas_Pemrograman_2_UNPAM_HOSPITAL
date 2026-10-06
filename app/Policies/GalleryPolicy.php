<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan atas album galeri memerlukan izin `content.manage`.
 * Foto album dikelola lewat MediaPolicy (pemilik Gallery => `content.manage`).
 */
final class GalleryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, Gallery $gallery): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->can('content.manage');
    }
}
