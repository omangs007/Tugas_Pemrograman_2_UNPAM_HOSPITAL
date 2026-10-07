<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * 04 §5 / 11 §8 (F4B-R15): daftar, detail, dan ubah status memerlukan `messages.manage`. Pesan berisi data pribadi
 * pengunjung sehingga tidak pernah dihapus, hanya diarsipkan (00 C): `delete` selalu `false`.
 */
final class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('messages.manage');
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('messages.manage');
    }

    public function updateStatus(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('messages.manage');
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return false;
    }
}
