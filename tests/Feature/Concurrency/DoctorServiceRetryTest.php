<?php

namespace Tests\Feature\Concurrency;

use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Doctors\DoctorService;
use App\Support\Actor;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Concurrency\ConcurrencyTestCase;

/**
 * F7A-R26 (carry F3/F6): `DoctorService::archive()` dan `changeStatus()` mengulang transaksi sampai tiga kali pada
 * deadlock dan, bila tetap gagal, menjawab `ValidationException` pesan sibuk pada kunci `status` (bukan 500) tanpa tulisan parsial.
 * Galat disuntikkan dengan injectFault (errno 1213); tidak ada proses anak. Titik gangguan: tulis dokter (galat langsung),
 * tulis sesi praktik (transaksi bersarang: DeadlockException), dan tulis templat jadwal (sesudah dokter dan sesi tertulis:
 * pembuktian rollback).
 */
#[Group('concurrency')]
class DoctorServiceRetryTest extends ConcurrencyTestCase
{
    private const DEADLOCK = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';

    private const BUSY = 'Sistem sedang sibuk, silakan coba lagi beberapa saat.';

    /**
     * Dokter aktif dengan satu sesi mendatang tanpa pendaftaran (arsip/nonaktif membatalkannya).
     *
     * @return array{0: Doctor, 1: PracticeSession}
     */
    private function scenario(): array
    {
        $session = $this->makeSession();

        return [Doctor::query()->findOrFail($session->doctor_id), $session];
    }

    private function service(): DoctorService
    {
        return $this->app->make(DoctorService::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function archiveFaultPoints(): array
    {
        return [
            'tulis dokter (galat langsung)' => ['update `doctors`'],
            'tulis sesi praktik (transaksi bersarang)' => ['update `practice_sessions`'],
            'tulis templat jadwal (sesudah dokter dan sesi tertulis)' => ['update `doctor_schedules`'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusFaultPoints(): array
    {
        return [
            'tulis dokter (galat langsung)' => ['update `doctors`'],
            'tulis sesi praktik (transaksi bersarang)' => ['update `practice_sessions`'],
        ];
    }

    /** Tidak ada tulisan parsial: dokter aktif, sesi terbuka, dan jumlah audit sama dengan sebelum percobaan. */
    private function assertNothingWritten(Doctor $doctor, PracticeSession $session, int $auditsBefore): void
    {
        $fresh = Doctor::query()->findOrFail($doctor->id);
        $this->assertSame(DoctorStatus::Active, $fresh->status);
        $this->assertNull($fresh->archived_at);

        $freshSession = PracticeSession::query()->findOrFail($session->id);
        $this->assertSame(SessionStatus::Open, $freshSession->status);
        $this->assertNull($freshSession->status_reason);

        $this->assertSame($auditsBefore, $this->rowsSince('audit_logs'), 'Tidak ada baris audit yang tertinggal.');
    }

    // ------------------------------------------------------------------ archive()

    #[DataProvider('archiveFaultPoints')]
    public function test_archive_retries_a_transient_deadlock_and_succeeds_on_the_third_attempt(string $fragment): void
    {
        [$doctor, $session] = $this->scenario();
        $staff = $this->makeStaff();
        $service = $this->service();
        $fault = $this->injectFault($fragment, 1213, '40001', self::DEADLOCK, 2);

        $service->archive($doctor, 'Uji retry', Actor::staff($staff));

        $this->assertSame(3, $fault->hits, 'Dua kegagalan lalu sukses pada percobaan ketiga.');
        $this->assertSame(2, $fault->thrown);

        $fresh = Doctor::query()->findOrFail($doctor->id);
        $this->assertSame(DoctorStatus::Archived, $fresh->status);
        $this->assertNotNull($fresh->archived_at);
        $this->assertSame(SessionStatus::Cancelled, PracticeSession::query()->findOrFail($session->id)->status);
        $this->assertSame(1, $this->rowsSince('audit_logs', 'doctor.archived'), 'Satu audit walau diulang.');
        $this->assertSame(1, $service->lastReport()?->cancelled, 'Laporan dampak hanya dari percobaan yang berhasil.');
    }

    #[DataProvider('archiveFaultPoints')]
    public function test_archive_with_a_persistent_deadlock_becomes_the_busy_message_without_partial_writes(string $fragment): void
    {
        [$doctor, $session] = $this->scenario();
        $staff = $this->makeStaff();
        $service = $this->service();
        $auditsBefore = $this->rowsSince('audit_logs');
        $fault = $this->injectFault($fragment, 1213, '40001', self::DEADLOCK);

        try {
            $service->archive($doctor, 'Uji retry', Actor::staff($staff));
            $this->fail('ValidationException pesan sibuk tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(['status' => [self::BUSY]], $e->errors());
        }

        $this->assertSame(3, $fault->hits, 'Tiga percobaan sebelum menyerah.');
        $this->assertNull($service->lastReport());
        $this->assertNothingWritten($doctor, $session, $auditsBefore);
    }

    public function test_archive_does_not_translate_a_non_concurrency_database_error(): void
    {
        [$doctor, $session] = $this->scenario();
        $staff = $this->makeStaff();
        $service = $this->service();
        $auditsBefore = $this->rowsSince('audit_logs');
        $fault = $this->injectFault('update `doctors`', 1406, '22001', "SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'status' at row 1");

        try {
            $service->archive($doctor, 'Uji retry', Actor::staff($staff));
            $this->fail('QueryException tidak dilempar.');
        } catch (QueryException $e) {
            $this->assertSame('22001', $e->errorInfo[0]);
        }

        $this->assertSame(1, $fault->hits, 'Galat bukan konkurensi tidak diulang.');
        $this->assertNull($service->lastReport());
        $this->assertNothingWritten($doctor, $session, $auditsBefore);
    }

    // ------------------------------------------------------------------ changeStatus()

    #[DataProvider('statusFaultPoints')]
    public function test_deactivating_retries_a_transient_deadlock_and_succeeds_on_the_third_attempt(string $fragment): void
    {
        [$doctor, $session] = $this->scenario();
        $staff = $this->makeStaff();
        $service = $this->service();
        $fault = $this->injectFault($fragment, 1213, '40001', self::DEADLOCK, 2);

        $service->changeStatus($doctor, DoctorStatus::Inactive, 'Cuti panjang', Actor::staff($staff));

        $this->assertSame(3, $fault->hits);
        $this->assertSame(2, $fault->thrown);
        $this->assertSame(DoctorStatus::Inactive, Doctor::query()->findOrFail($doctor->id)->status);
        $this->assertSame(SessionStatus::Cancelled, PracticeSession::query()->findOrFail($session->id)->status);
        $this->assertSame(1, $this->rowsSince('audit_logs', 'doctor.status_changed'));
        $this->assertSame(1, $service->lastReport()?->cancelled);
    }

    #[DataProvider('statusFaultPoints')]
    public function test_deactivating_with_a_persistent_deadlock_becomes_the_busy_message_without_partial_writes(string $fragment): void
    {
        [$doctor, $session] = $this->scenario();
        $staff = $this->makeStaff();
        $service = $this->service();
        $auditsBefore = $this->rowsSince('audit_logs');
        $fault = $this->injectFault($fragment, 1213, '40001', self::DEADLOCK);

        try {
            $service->changeStatus($doctor, DoctorStatus::Inactive, 'Cuti panjang', Actor::staff($staff));
            $this->fail('ValidationException pesan sibuk tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(['status' => [self::BUSY]], $e->errors());
        }

        $this->assertSame(3, $fault->hits);
        $this->assertNull($service->lastReport());
        $this->assertNothingWritten($doctor, $session, $auditsBefore);
    }

    public function test_reactivating_retries_a_transient_deadlock_too(): void
    {
        [$doctor] = $this->scenario();
        $staff = $this->makeStaff();
        Doctor::query()->whereKey($doctor->id)->update(['status' => DoctorStatus::Inactive->value]);
        $fault = $this->injectFault('update `doctors`', 1213, '40001', self::DEADLOCK, 2);

        $this->service()->changeStatus(Doctor::query()->findOrFail($doctor->id), DoctorStatus::Active, null, Actor::staff($staff));

        $this->assertSame(3, $fault->hits);
        $this->assertSame(DoctorStatus::Active, Doctor::query()->findOrFail($doctor->id)->status);
        $this->assertSame(1, $this->rowsSince('audit_logs', 'doctor.status_changed'));
    }

    public function test_the_validation_refusals_are_unchanged_by_the_retry_wrapper(): void
    {
        [$doctor] = $this->scenario();
        $staff = $this->makeStaff();
        $service = $this->service();
        $service->archive($doctor, 'Uji', Actor::staff($staff));

        try {
            $service->archive(Doctor::query()->findOrFail($doctor->id), 'Uji lagi', Actor::staff($staff));
            $this->fail('Arsip ganda harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['status' => ['Dokter sudah diarsipkan.']], $e->errors(), 'Penolakan validasi tidak diulang dan tidak menjadi pesan sibuk.');
        }

        $this->assertSame(1, $this->rowsSince('audit_logs', 'doctor.archived'));
    }
}
