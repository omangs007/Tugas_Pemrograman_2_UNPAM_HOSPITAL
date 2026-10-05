<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PracticeSession;
use App\Models\User;

/**
 * 04 §5 / 11 §8. Lihat: `schedules.view`; kelola: `schedules.manage`; tindak lanjut sesi terdampak:
 * `sessions.review` (`review` dan `resolve` dipakai fase 7).
 *
 * Aturan yang juga mengikat Super Admin tidak diletakkan di sini (Gate::before melewati Policy), melainkan di
 * Form Request dan ScheduleChangeService: sesi lampau tidak diubah (F3-R27), kuota tidak di bawah booked_count
 * (BR-31), hapus hanya untuk sesi tambahan manual tanpa appointment (F3-R20).
 */
final class PracticeSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('schedules.view');
    }

    public function view(User $user, PracticeSession $session): bool
    {
        return $user->can('schedules.view');
    }

    public function create(User $user): bool
    {
        return $user->can('schedules.manage');
    }

    public function update(User $user, PracticeSession $session): bool
    {
        return $user->can('schedules.manage');
    }

    public function changeStatus(User $user, PracticeSession $session): bool
    {
        return $user->can('schedules.manage');
    }

    public function delete(User $user, PracticeSession $session): bool
    {
        return $user->can('schedules.manage');
    }

    public function review(User $user): bool
    {
        return $user->can('sessions.review');
    }

    public function resolve(User $user, PracticeSession $session): bool
    {
        return $user->can('sessions.review');
    }
}
