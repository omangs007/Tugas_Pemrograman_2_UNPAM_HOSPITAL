<?php

namespace Tests\Feature\Concurrency;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientAccountLinker;
use App\Support\Actor;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\Concurrency\ConcurrencyTestCase;

/**
 * F7A-R12 / catatan teknis 6: `link()` mengevaluasi ulang di bawah kunci. Bukti deterministik bahwa perubahan yang
 * ter-COMMIT koneksi lain setelah pembacaan pertama dan sebelum kunci profil (read view REPEATABLE READ sudah terbuka)
 * tetap terlihat: hasilnya `ACCOUNT_HAS_HISTORY`, bukan galat unik/FK (500). Tanpa RefreshDatabase per transaksi:
 * masalah snapshot tidak dapat direproduksi dalam satu koneksi.
 */
class PatientAccountLinkerSnapshotTest extends ConcurrencyTestCase
{
    private function linker(): PatientAccountLinker
    {
        return $this->app->make(PatientAccountLinker::class);
    }

    /** Menjalankan `$competing` satu kali, tepat sebelum kueri `select ... for update` pada `patient_profiles`. */
    private function beforeProfileLock(Closure $competing): void
    {
        $fired = false;

        DB::connection()->beforeExecuting(function (string $query) use (&$fired, $competing): void {
            $sql = strtolower($query);

            if ($fired || ! str_contains($sql, 'for update') || ! str_contains($sql, 'patient_profiles')) {
                return;
            }

            $fired = true;
            $competing();
        });
    }

    private function assertHasHistory(PatientProfile $walkIn, User $account, Actor $actor): void
    {
        try {
            $this->linker()->link($walkIn, $account, $actor);
            $this->fail('Penghubungan harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['account_email' => [__('patients.link.ACCOUNT_HAS_HISTORY')]], $e->errors());
        }

        $this->assertNull(PatientProfile::query()->findOrFail($walkIn->id)->user_id);
        $this->assertSame(0, $this->rowsSince('audit_logs', 'patient.account_linked'));
    }

    public function test_the_empty_account_profile_taken_by_another_officer_is_reported_as_history(): void
    {
        $actor = Actor::staff($this->makeStaff());
        $w1 = PatientProfile::factory()->create();
        $w2 = PatientProfile::factory()->create();
        $empty = $this->makePatient();
        $account = User::query()->findOrFail($empty->user_id);
        $pdo = $this->openConnection();

        // Petugas lain menghubungkan W2 ke akun yang sama lebih dulu: profil kosong dihapus, W2 dipasang.
        $this->beforeProfileLock(function () use ($pdo, $empty, $w2, $account): void {
            $pdo->prepare('DELETE FROM patient_profiles WHERE id = ?')->execute([$empty->id]);
            $pdo->prepare('UPDATE patient_profiles SET user_id = ? WHERE id = ?')->execute([$account->id, $w2->id]);
        });

        $this->assertHasHistory($w1, $account, $actor);
        $this->assertSame($account->id, (int) PatientProfile::query()->findOrFail($w2->id)->user_id);
    }

    public function test_an_account_without_a_profile_linked_by_another_officer_is_reported_as_history(): void
    {
        $actor = Actor::staff($this->makeStaff());
        $w1 = PatientProfile::factory()->create();
        $w2 = PatientProfile::factory()->create();
        $account = User::factory()->patient()->create();
        $pdo = $this->openConnection();

        $this->beforeProfileLock(function () use ($pdo, $w2, $account): void {
            $pdo->prepare('UPDATE patient_profiles SET user_id = ? WHERE id = ?')->execute([$account->id, $w2->id]);
        });

        $this->assertHasHistory($w1, $account, $actor);
        $this->assertSame($account->id, (int) PatientProfile::query()->findOrFail($w2->id)->user_id);
    }

    public function test_a_booking_committed_after_the_first_read_is_seen_and_the_profile_is_kept(): void
    {
        $actor = Actor::staff($this->makeStaff());
        $w1 = PatientProfile::factory()->create();
        $empty = $this->makePatient();
        $account = User::query()->findOrFail($empty->user_id);
        $session = $this->makeSession();
        $pdo = $this->openConnection();

        $this->beforeProfileLock(function () use ($pdo, $empty, $session): void {
            $locked = $this->beginAndLock($pdo, $empty->id, $session->id);
            $this->rawBook($pdo, $empty->id, $locked);
            $pdo->commit();
        });

        $this->assertHasHistory($w1, $account, $actor);
        $this->assertNotNull(PatientProfile::query()->find($empty->id), 'Profil berriwayat tidak dihapus.');
        $this->assertSame(1, Appointment::query()->where('patient_profile_id', $empty->id)->count());
    }
}
