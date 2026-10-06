<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * 04 §5 / 11 §8 (BR-121): halaman statis adalah halaman sistem. Melihat dan mengubah memerlukan
 * `content.manage`; menambah dan menghapus tidak pernah diizinkan (tidak ada route-nya).
 */
final class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, Page $page): bool
    {
        return false;
    }
}
