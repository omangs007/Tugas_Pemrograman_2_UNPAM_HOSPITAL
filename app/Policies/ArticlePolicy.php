<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan atas artikel memerlukan izin `content.manage`. Menerbitkan = `update`
 * (status `published`); `delete` = soft delete.
 */
final class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, Article $article): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, Article $article): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->can('content.manage');
    }
}
