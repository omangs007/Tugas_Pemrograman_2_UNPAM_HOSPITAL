<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScheduleException;
use App\Models\User;

/**
 * 04 §5 / 11 §8. Lihat: `schedules.view`; kelola: `schedules.manage`. `delete` berarti mencabut pengecualian
 * lewat ScheduleChangeService::removeException (00 C). Aturan `start_date` ditegakkan Form Request.
 */
final class ScheduleExceptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('schedules.view');
    }

    public function view(User $user, ScheduleException $exception): bool
    {
        return $user->can('schedules.view');
    }

    public function create(User $user): bool
    {
        return $user->can('schedules.manage');
    }

    public function update(User $user, ScheduleException $exception): bool
    {
        return $user->can('schedules.manage');
    }

    public function delete(User $user, ScheduleException $exception): bool
    {
        return $user->can('schedules.manage');
    }
}
