<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DoctorSchedule;
use App\Models\User;

/**
 * 04 §5 / 11 §8. Lihat: `schedules.view`; kelola: `schedules.manage`. Mengaktifkan atau menonaktifkan templat
 * adalah `update`. Penolakan hapus karena ada sesi ber-appointment (BR-12) dan penolakan ganti poliklinik
 * (BR-10) dilakukan service dan Form Request, bukan di sini, agar berlaku juga untuk Super Admin
 * (Gate::before melewati Policy; F3-R30 dan Global Constraints).
 */
final class DoctorSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('schedules.view');
    }

    public function view(User $user, DoctorSchedule $schedule): bool
    {
        return $user->can('schedules.view');
    }

    public function create(User $user): bool
    {
        return $user->can('schedules.manage');
    }

    public function update(User $user, DoctorSchedule $schedule): bool
    {
        return $user->can('schedules.manage');
    }

    public function delete(User $user, DoctorSchedule $schedule): bool
    {
        return $user->can('schedules.manage');
    }
}
