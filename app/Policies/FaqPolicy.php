<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan atas FAQ memerlukan izin `content.manage`.
 */
final class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, Faq $faq): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->can('content.manage');
    }
}
