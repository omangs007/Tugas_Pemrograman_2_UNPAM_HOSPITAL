<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InsurancePartner;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan atas mitra asuransi memerlukan izin `content.manage`. Penolakan hapus karena
 * mitra masih dirujuk appointment dilakukan controller lewat InsurancePartner::deletionBlockedReason() (BR-180),
 * sehingga berlaku juga untuk Super Admin (F4A-R21).
 */
final class InsurancePartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, InsurancePartner $insurancePartner): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, InsurancePartner $insurancePartner): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, InsurancePartner $insurancePartner): bool
    {
        return $user->can('content.manage');
    }
}
