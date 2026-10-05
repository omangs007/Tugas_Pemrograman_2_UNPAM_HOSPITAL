<?php

namespace Tests\Feature\Database;

use App\Models\PatientProfile;
use App\Models\PracticeSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\Support\RawPdoConnection;
use Tests\TestCase;
use Throwable;

/**
 * KON-01 versi spike fase 3 (F3-R6): dua koneksi PDO mentah ke database uji membuktikan perilaku penguncian
 * baris dan constraint yang menjadi dasar `BookingService` (fase 6). Versi penuh dengan `BookingService`,
 * proses anak, dan beban N paralel ada di fase 6.
 *
 * Mekanisme (mengapa baris terlihat lintas koneksi):
 *  - Kelas ini memakai RefreshDatabase hanya untuk memastikan skema termigrasi, tetapi
 *    `$connectionsToTransact = []` mematikan transaksi pembungkus. Baris yang dibuat factory lewat koneksi
 *    aplikasi berjalan dalam autocommit, sehingga langsung TER-COMMIT dan terlihat oleh koneksi PDO lain.
 *  - Koneksi A dan B adalah sesi MariaDB terpisah (transaksi dan kunci sendiri). `innodb_lock_wait_timeout`
 *    diatur 1 detik pada keduanya. Kunci baris yang dipegang A membuat B menunggu sampai galat 1205, dan
 *    karena A tidak sedang menunggu apa pun, satu proses PHP cukup (tanpa proses anak atau thread).
 *  - Karena data ter-commit, `tearDown()` membersihkannya sesuai urutan FK (appointments, sesi, pasien,
 *    dokter, poliklinik) agar tes lain tidak terpengaruh. Transaksi PDO yang masih terbuka di-rollback lebih
 *    dulu supaya kunci tidak menahan penghapusan.
 */
class ConcurrencySpikeTest extends TestCase
{
    use RefreshDatabase;

    /** Tanpa transaksi pembungkus: data harus ter-commit agar terlihat oleh koneksi PDO lain. */
    protected $connectionsToTransact = [];

    private ?PDO $a = null;

    private ?PDO $b = null;

    /** Urutan hapus aman FK untuk baris yang dibuat tes ini (anak lebih dulu). */
    private const CLEANUP_TABLES = ['appointments', 'practice_sessions', 'patient_profiles', 'doctors', 'clinics', 'audit_logs'];

    /** @var array<string, int> id tertinggi tiap tabel sebelum tes dimulai */
    private array $baselines = [];

    private int $codeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(0, DB::transactionLevel(), 'Tes konkurensi tidak boleh berjalan di dalam transaksi.');

        foreach (self::CLEANUP_TABLES as $table) {
            $this->baselines[$table] = (int) DB::table($table)->max('id');
        }

        $this->a = RawPdoConnection::open(1);
        $this->b = RawPdoConnection::open(1);
    }

    protected function tearDown(): void
    {
        foreach ([$this->a, $this->b] as $pdo) {
            try {
                if ($pdo !== null && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                // Koneksi mungkin sudah mati; kunci dilepas server saat sesi ditutup.
            }
        }

        $this->a = null;
        $this->b = null;

        // Setiap tabel dibersihkan mandiri: kegagalan satu tidak boleh melewatkan yang lain. Baris yang dibuat
        // factory (klinik, dokter, audit) ter-commit walau pembuatan sesi gagal di tengah, jadi dasarnya id, bukan daftar.
        foreach (self::CLEANUP_TABLES as $table) {
            try {
                DB::table($table)->where('id', '>', $this->baselines[$table] ?? PHP_INT_MAX)->delete();
            } catch (Throwable) {
                // Dilanjutkan; tes kebersihan di awal tes berikutnya akan melaporkan sisa data.
            }
        }

        parent::tearDown();
    }

    // --- Pembantu data (koneksi aplikasi, autocommit) ---

    private function makeSession(int $quota = 5): PracticeSession
    {
        $session = PracticeSession::factory()->manual()->create([
            'quota' => $quota,
            'session_date' => today()->addDay()->toDateString(),
        ]);

        return $session;
    }

    private function makePatient(): PatientProfile
    {
        return PatientProfile::factory()->create();
    }

    // --- Pembantu SQL mentah ---

    /**
     * SELECT ... FOR UPDATE: pembacaan berkunci selalu membaca nilai terbaru yang ter-commit.
     *
     * @return array<string, mixed>
     */
    private function lockRow(PDO $pdo, string $table, int $id): array
    {
        $statement = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? FOR UPDATE");
        $statement->execute([$id]);

        return (array) $statement->fetch();
    }

    /**
     * Awal transaksi booking dengan urutan kunci baku F.6: patient_profiles lalu practice_sessions.
     *
     * @return array<string, mixed> baris sesi yang terkunci
     */
    private function beginAndLock(PDO $pdo, int $patientId, int $sessionId): array
    {
        $pdo->beginTransaction();
        $this->lockRow($pdo, 'patient_profiles', $patientId);

        return $this->lockRow($pdo, 'practice_sessions', $sessionId);
    }

    /**
     * Sisipkan appointment lalu naikkan pencacah sesi (tanpa commit). Mengembalikan nomor antrean.
     *
     * @param  array<string, mixed>  $session  baris sesi yang terkunci (hasil beginAndLock)
     */
    private function insertBooking(PDO $pdo, int $patientId, array $session, string $status = 'pending'): int
    {
        $queue = (int) $session['last_queue_number'] + 1;

        $this->insertAppointment($pdo, $patientId, $session, $queue, $status);

        $update = $pdo->prepare('UPDATE practice_sessions SET booked_count = booked_count + 1, last_queue_number = ? WHERE id = ?');
        $update->execute([$queue, $session['id']]);

        return $queue;
    }

    /** @param  array<string, mixed>  $session */
    private function insertAppointment(PDO $pdo, int $patientId, array $session, int $queue, string $status): void
    {
        $this->codeSequence++;

        $insert = $pdo->prepare(
            'INSERT INTO appointments (booking_code, patient_profile_id, practice_session_id, doctor_id, clinic_id, session_date, queue_number, status, channel, payment_type, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $now = now()->toDateTimeString();
        $insert->execute([
            sprintf('UPH-%04d-%04d', $this->codeSequence, random_int(0, 9999)),
            $patientId,
            $session['id'],
            $session['doctor_id'],
            $session['clinic_id'],
            $session['session_date'],
            $queue,
            $status,
            'online',
            'umum',
            $now,
            $now,
        ]);
    }

    /**
     * Menjalankan $callback dan mengukur waktunya.
     *
     * @return array{0: ?PDOException, 1: float} galat (bila ada) dan lama dalam detik
     */
    private function attempt(callable $callback): array
    {
        $start = hrtime(true);
        $error = null;

        try {
            $callback();
        } catch (PDOException $e) {
            $error = $e;
        }

        return [$error, (hrtime(true) - $start) / 1_000_000_000];
    }

    private function commitIfOpen(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    private function rollBackIfOpen(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    // --- Mekanisme ---

    public function test_rows_created_by_the_application_connection_are_committed_and_visible_to_other_connections(): void
    {
        $session = $this->makeSession();
        $patient = $this->makePatient();

        $this->assertSame(0, DB::transactionLevel());

        foreach ([$this->a, $this->b] as $pdo) {
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM practice_sessions WHERE id = {$session->id}")->fetchColumn());
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM patient_profiles WHERE id = {$patient->id}")->fetchColumn());
        }
    }

    public function test_the_two_raw_connections_are_distinct_server_sessions_with_a_one_second_lock_wait(): void
    {
        $idA = (int) $this->a->query('SELECT CONNECTION_ID()')->fetchColumn();
        $idB = (int) $this->b->query('SELECT CONNECTION_ID()')->fetchColumn();
        $idApp = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;

        $this->assertCount(3, array_unique([$idA, $idB, $idApp]));
        $this->assertSame(1, (int) $this->a->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn());
        $this->assertSame(1, (int) $this->b->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn());
    }

    // --- (a) SELECT ... FOR UPDATE pada sesi yang sama ---

    public function test_for_update_on_the_same_session_waits_until_the_lock_wait_timeout_then_succeeds_after_commit(): void
    {
        $session = $this->makeSession();

        $this->a->beginTransaction();
        $this->assertSame($session->id, (int) $this->lockRow($this->a, 'practice_sessions', $session->id)['id']);

        $this->b->beginTransaction();
        [$error, $seconds] = $this->attempt(fn () => $this->lockRow($this->b, 'practice_sessions', $session->id));

        $this->assertInstanceOf(PDOException::class, $error, 'Koneksi B seharusnya menunggu lalu gagal.');
        $this->assertSame('HY000', $error->errorInfo[0]);
        $this->assertSame(1205, $error->errorInfo[1], 'Lock wait timeout, bukan deadlock (1213).');
        $this->assertGreaterThanOrEqual(0.9, $seconds, 'B harus benar-benar menunggu sekitar satu detik.');
        $this->assertLessThan(5.0, $seconds);

        $this->commitIfOpen($this->a);

        [$retryError, $retrySeconds] = $this->attempt(fn () => $this->lockRow($this->b, 'practice_sessions', $session->id));

        $this->assertNull($retryError, 'Setelah A commit, B memperoleh kunci.');
        $this->assertLessThan(0.5, $retrySeconds);

        $this->commitIfOpen($this->b);
    }

    public function test_a_rollback_releases_the_lock_for_the_waiting_connection(): void
    {
        $session = $this->makeSession();

        $this->a->beginTransaction();
        $this->lockRow($this->a, 'practice_sessions', $session->id);
        $this->b->beginTransaction();
        [$error] = $this->attempt(fn () => $this->lockRow($this->b, 'practice_sessions', $session->id));
        $this->assertSame(1205, $error?->errorInfo[1]);

        $this->a->rollBack();
        [$retryError] = $this->attempt(fn () => $this->lockRow($this->b, 'practice_sessions', $session->id));

        $this->assertNull($retryError);
        $this->commitIfOpen($this->b);
    }

    // --- (b) Urutan kunci baku patient_profiles -> practice_sessions -> appointments ---

    public function test_the_patient_lock_serialises_bookings_of_the_same_patient_on_different_sessions_kon01d(): void
    {
        $patient = $this->makePatient();
        $first = $this->makeSession();
        $second = $this->makeSession();

        $firstRow = $this->beginAndLock($this->a, $patient->id, $first->id);

        $this->b->beginTransaction();
        [$error, $seconds] = $this->attempt(fn () => $this->lockRow($this->b, 'patient_profiles', $patient->id));

        $this->assertSame(1205, $error?->errorInfo[1], 'Kunci pertama (pasien) menahan B; bukan deadlock.');
        $this->assertGreaterThanOrEqual(0.9, $seconds);

        // A tidak terhalang oleh B: B belum memegang kunci apa pun yang dibutuhkan A.
        [$aError, $aSeconds] = $this->attempt(function () use ($patient, $firstRow): void {
            $this->insertBooking($this->a, $patient->id, $firstRow);
            $this->a->commit();
        });
        $this->assertNull($aError);
        $this->assertLessThan(0.5, $aSeconds);

        // B mengulang seluruh transaksi (seperti DB::transaction attempts) dan berhasil.
        $this->rollBackIfOpen($this->b);
        $secondRow = $this->beginAndLock($this->b, $patient->id, $second->id);
        $queue = $this->insertBooking($this->b, $patient->id, $secondRow);
        $this->b->commit();

        $this->assertSame(1, $queue);
        $this->assertSame(1, DB::table('appointments')->where('practice_session_id', $first->id)->count());
        $this->assertSame(1, DB::table('appointments')->where('practice_session_id', $second->id)->count());
        $this->assertSame(1, (int) DB::table('practice_sessions')->where('id', $first->id)->value('booked_count'));
        $this->assertSame(1, (int) DB::table('practice_sessions')->where('id', $second->id)->value('booked_count'));
    }

    public function test_the_standard_lock_order_serialises_two_patients_on_one_session_without_a_deadlock(): void
    {
        $session = $this->makeSession(5);
        $first = $this->makePatient();
        $second = $this->makePatient();

        // A memegang pasien 1 dan sesi.
        $locked = $this->beginAndLock($this->a, $first->id, $session->id);

        // B memperoleh pasien 2 (bebas), lalu menunggu sesi sambil MEMEGANG kunci pasien 2.
        $this->b->beginTransaction();
        [$ownLockError, $ownLockSeconds] = $this->attempt(fn () => $this->lockRow($this->b, 'patient_profiles', $second->id));
        $this->assertNull($ownLockError);
        $this->assertLessThan(0.5, $ownLockSeconds);

        [$error, $seconds] = $this->attempt(fn () => $this->lockRow($this->b, 'practice_sessions', $session->id));
        $this->assertSame(1205, $error?->errorInfo[1], 'B menunggu kunci sesi: lock wait timeout, bukan deadlock (1213).');
        $this->assertGreaterThanOrEqual(0.9, $seconds);

        // A tidak pernah meminta kunci pasien 2, sehingga tidak ada siklus tunggu: A selesai tanpa menunggu.
        [$aError, $aSeconds] = $this->attempt(function () use ($first, $locked): void {
            $this->insertBooking($this->a, $first->id, $locked);
            $this->a->commit();
        });
        $this->assertNull($aError);
        $this->assertLessThan(0.5, $aSeconds);

        // B mengulang transaksi dan memperoleh nomor antrean berikutnya.
        $this->rollBackIfOpen($this->b);
        $lockedAgain = $this->beginAndLock($this->b, $second->id, $session->id);
        $this->assertSame(1, (int) $lockedAgain['booked_count'], 'B membaca nilai terbaru setelah A commit.');
        $queue = $this->insertBooking($this->b, $second->id, $lockedAgain);
        $this->b->commit();

        $this->assertSame(2, $queue);
        $this->assertSame([1, 2], DB::table('appointments')->where('practice_session_id', $session->id)->orderBy('queue_number')->pluck('queue_number')->map(fn ($n): int => (int) $n)->all());

        $fresh = DB::table('practice_sessions')->find($session->id);
        $this->assertSame([2, 2], [(int) $fresh->booked_count, (int) $fresh->last_queue_number]);
    }

    // --- (c) UNIQUE (patient_profile_id, practice_session_id, active_flag) ---

    public function test_a_second_active_appointment_for_the_same_patient_and_session_is_rejected(): void
    {
        $session = $this->makeSession();
        $patient = $this->makePatient();
        $row = $this->committedSessionRow($session->id);

        $this->a->beginTransaction();
        $this->insertAppointment($this->a, $patient->id, $row, 1, 'pending');
        $this->a->commit();

        $this->b->beginTransaction();

        try {
            $this->insertAppointment($this->b, $patient->id, $row, 2, 'confirmed');
            $this->fail('Appointment aktif kedua seharusnya ditolak.');
        } catch (PDOException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('appointments_patient_session_active_unique', $e->getMessage());
        } finally {
            $this->rollBackIfOpen($this->b);
        }

        $this->assertSame(1, DB::table('appointments')->where('practice_session_id', $session->id)->count());
    }

    public function test_cancelled_appointments_for_the_same_patient_and_session_are_accepted(): void
    {
        $session = $this->makeSession();
        $patient = $this->makePatient();
        $row = $this->committedSessionRow($session->id);

        $this->a->beginTransaction();
        $this->insertAppointment($this->a, $patient->id, $row, 1, 'pending');
        $this->a->commit();

        $this->b->beginTransaction();
        $this->insertAppointment($this->b, $patient->id, $row, 2, 'cancelled');
        $this->insertAppointment($this->b, $patient->id, $row, 3, 'cancelled');
        $this->b->commit();

        $this->assertSame(3, DB::table('appointments')->where('practice_session_id', $session->id)->count());
        $this->assertSame(1, DB::table('appointments')->where('practice_session_id', $session->id)->whereNotNull('active_flag')->count());
    }

    public function test_a_duplicate_active_insert_waits_for_the_uncommitted_first_one_and_then_fails_with_1062(): void
    {
        $session = $this->makeSession();
        $patient = $this->makePatient();
        $row = $this->committedSessionRow($session->id);

        $this->a->beginTransaction();
        $this->insertAppointment($this->a, $patient->id, $row, 1, 'pending');

        $this->b->beginTransaction();
        [$error, $seconds] = $this->attempt(fn () => $this->insertAppointment($this->b, $patient->id, $row, 2, 'pending'));

        $this->assertSame(1205, $error?->errorInfo[1], 'Kunci UNIQUE milik sisipan A yang belum commit menahan B.');
        $this->assertGreaterThanOrEqual(0.9, $seconds);

        $this->a->commit();

        [$retryError] = $this->attempt(fn () => $this->insertAppointment($this->b, $patient->id, $row, 2, 'pending'));

        $this->assertSame(1062, $retryError?->errorInfo[1], 'Setelah A commit, B mendapat duplikat.');
        $this->assertStringContainsString('appointments_patient_session_active_unique', (string) $retryError?->getMessage());

        $this->rollBackIfOpen($this->b);
        $this->assertSame(1, DB::table('appointments')->where('practice_session_id', $session->id)->count());
    }

    // --- (d) CHECK chk_sessions_quota ---

    public function test_the_check_constraint_rejects_an_increment_beyond_the_quota_even_if_the_application_forgets_to_check(): void
    {
        $session = $this->makeSession(1);
        $first = $this->makePatient();
        $second = $this->makePatient();

        $locked = $this->beginAndLock($this->a, $first->id, $session->id);
        $this->insertBooking($this->a, $first->id, $locked);
        $this->a->commit();

        $lockedAgain = $this->beginAndLock($this->b, $second->id, $session->id);
        $this->assertSame([1, 1], [(int) $lockedAgain['booked_count'], (int) $lockedAgain['quota']], 'Sesi penuh: kursi terakhir sudah diambil A.');

        try {
            // Naif: tanpa memeriksa booked_count < quota.
            $this->b->prepare('UPDATE practice_sessions SET booked_count = booked_count + 1, last_queue_number = last_queue_number + 1 WHERE id = ?')
                ->execute([$session->id]);
            $this->fail('CHECK chk_sessions_quota seharusnya menolak.');
        } catch (PDOException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('`chk_sessions_quota`', $e->getMessage());
        } finally {
            $this->rollBackIfOpen($this->b);
        }

        $fresh = DB::table('practice_sessions')->find($session->id);
        $this->assertSame([1, 1], [(int) $fresh->booked_count, (int) $fresh->last_queue_number]);
        $this->assertSame(1, DB::table('appointments')->where('practice_session_id', $session->id)->count());
    }

    // --- Kebersihan ---

    public function test_the_database_is_clean_at_the_start_of_each_test(): void
    {
        foreach (self::CLEANUP_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Tabel {$table} harus kosong: tes sebelumnya wajib membersihkan di tearDown().");
        }
    }

    /**
     * Membaca baris sesi tanpa transaksi (nilai ter-commit) untuk melengkapi kolom appointment.
     *
     * @return array<string, mixed>
     */
    private function committedSessionRow(int $id): array
    {
        $row = DB::table('practice_sessions')->where('id', $id)->first();

        return [
            'id' => $row->id,
            'doctor_id' => $row->doctor_id,
            'clinic_id' => $row->clinic_id,
            'session_date' => $row->session_date,
            'last_queue_number' => $row->last_queue_number,
        ];
    }
}
