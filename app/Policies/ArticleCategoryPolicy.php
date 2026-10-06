<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ArticleCategory;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan atas kategori artikel memerlukan izin `content.manage`. Kategori dapat
 * dihapus kapan saja: artikelnya menjadi tanpa kategori (FK `nullOnDelete`).
 */
final class ArticleCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, ArticleCategory $articleCategory): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, ArticleCategory $articleCategory): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, ArticleCategory $articleCategory): bool
    {
        return $user->can('content.manage');
    }
}
