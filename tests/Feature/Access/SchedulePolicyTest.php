<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 04 §5 / 11 §8: Policy DoctorSchedule, ScheduleException, dan PracticeSession per peran.
 * Super Admin lolos lewat Gate::before; aturan mutlak (sesi lampau, kuota, hapus bersyarat) ada di service dan
 * Form Request, bukan di Policy (diuji di tes service dan tes Form Request).
 */
class SchedulePolicyTest extends TestCase
{
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, bool, bool, bool}>
     */
    public static function roles(): array
    {
        // [peran, boleh lihat (schedules.view), boleh kelola (schedules.manage), boleh tindak lanjut (sessions.review)]
        return [
            'super admin' => ['super-admin', true, true, true],
            'admin konten' => ['content-admin', true, true, false],
            'petugas pendaftaran' => ['registration-officer', true, false, true],
        ];
    }

    #[DataProvider('roles')]
    public function test_doctor_schedule_policy_follows_view_and_manage_permissions(string $role, bool $view, bool $manage): void
    {
        $user = $this->createStaff($role);
        $schedule = $this->createSchedule();

        $this->assertSame($view, $user->can('viewAny', $schedule::class));
        $this->assertSame($view, $user->can('view', $schedule));
        $this->assertSame($manage, $user->can('create', $schedule::class));
        $this->assertSame($manage, $user->can('update', $schedule));
        $this->assertSame($manage, $user->can('delete', $schedule));
    }

    #[DataProvider('roles')]
    public function test_schedule_exception_policy_follows_view_and_manage_permissions(string $role, bool $view, bool $manage): void
    {
        $user = $this->createStaff($role);
        $exception = $this->createException();

        $this->assertSame($view, $user->can('viewAny', $exception::class));
        $this->assertSame($view, $user->can('view', $exception));
        $this->assertSame($manage, $user->can('create', $exception::class));
        $this->assertSame($manage, $user->can('update', $exception));
        $this->assertSame($manage, $user->can('delete', $exception));
    }

    #[DataProvider('roles')]
    public function test_practice_session_policy_separates_manage_from_review(string $role, bool $view, bool $manage, bool $review): void
    {
        $user = $this->createStaff($role);
        $session = $this->createSession();

        $this->assertSame($view, $user->can('viewAny', $session::class));
        $this->assertSame($view, $user->can('view', $session));
        $this->assertSame($manage, $user->can('create', $session::class));
        $this->assertSame($manage, $user->can('update', $session));
        $this->assertSame($manage, $user->can('changeStatus', $session));
        $this->assertSame($manage, $user->can('delete', $session));
        $this->assertSame($review, $user->can('review', $session::class));
        $this->assertSame($review, $user->can('resolve', $session));
    }

    public function test_patient_account_has_no_schedule_ability(): void
    {
        $this->seedRoles();
        $patient = User::factory()->patient()->create();
        $schedule = $this->createSchedule();
        $exception = $this->createException();
        $session = $this->createSession();

        foreach (['viewAny' => $schedule::class, 'create' => $schedule::class] as $ability => $target) {
            $this->assertFalse($patient->can($ability, $target), "Pasien tidak boleh {$ability} templat.");
        }

        $this->assertFalse($patient->can('update', $schedule));
        $this->assertFalse($patient->can('delete', $exception));
        $this->assertFalse($patient->can('view', $session));
        $this->assertFalse($patient->can('review', $session::class));
    }
}
