<?php

namespace Tests\Support\Concurrency;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use stdClass;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\Support\DatabaseGuard;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\RawPdoConnection;
use Tests\TestCase;
use Throwable;

/**
 * Dasar tes konkurensi fase 6 (F6-R34). Pola spike fase 3: RefreshDatabase hanya untuk memastikan skema
 * termigrasi, `$connectionsToTransact = []` mematikan transaksi pembungkus sehingga semua baris ter-COMMIT
 * (terlihat oleh koneksi PDO dan proses anak) dan wajib dibersihkan sendiri. Pembersihan memakai baseline
 * `max(id)` per tabel, lalu memeriksa ulang bahwa tidak ada sisa (tes gagal bila ada).
 *
 * Aturan: tanpa createStaff() (menyemai peran yang ter-commit), tanpa mengubah pengaturan, tanpa travelTo()
 * (proses anak memakai jam asli).
 */
abstract class ConcurrencyTestCase extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    /** Tanpa transaksi pembungkus: data harus ter-commit agar terlihat oleh koneksi/proses lain. */
    protected $connectionsToTransact = [];

    /** Detik tetap sebelum penghalang serempak (waktu boot proses PHP di Windows). */
    protected const BARRIER_BASE = 2.5;

    /** Tambahan detik per proses anak. */
    protected const BARRIER_PER_PROCESS = 0.35;

    /** Urutan hapus aman FK: anak lebih dulu (`notifications` dihapus terpisah lewat akun uji). */
    private const CLEANUP_TABLES = [
        'appointment_status_histories',
        'appointments',
        'practice_sessions',
        'schedule_exceptions',
        'patient_profiles',
        'audit_logs',
        'doctors',
        'clinics',
        'users',
    ];

    /** @var array<string, int> id tertinggi tiap tabel sebelum tes dimulai */
    private array $baselines = [];

    /** @var list<PDO> */
    private array $connections = [];

    /** @var list<Process> */
    private array $processes = [];

    private int $codeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(0, DB::transactionLevel(), 'Tes konkurensi tidak boleh berjalan di dalam transaksi.');

        foreach (self::CLEANUP_TABLES as $table) {
            $this->baselines[$table] = (int) DB::table($table)->max('id');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            try {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            } catch (Throwable) {
                // Proses sudah selesai atau tidak dapat dihentikan; koneksinya ditutup server.
            }
        }

        $this->processes = [];

        foreach ($this->connections as $pdo) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                // Koneksi mungkin sudah mati; kunci dilepas server saat sesi ditutup.
            }
        }

        $this->connections = [];

        $leftovers = $this->purge();

        parent::tearDown();

        if ($leftovers !== []) {
            $this->fail('Data tes konkurensi tertinggal di database uji: '.implode(', ', $leftovers).'.');
        }
    }

    /**
     * Menghapus semua baris yang dibuat tes (dan proses anaknya), lalu mengembalikan daftar tabel yang masih
     * berisi baris di atas baseline. Setiap tabel dibersihkan mandiri agar satu kegagalan tidak melewatkan yang lain.
     *
     * @return list<string>
     */
    private function purge(): array
    {
        try {
            // Proses anak yang dihentikan paksa dapat menahan kunci sesaat; jangan menunggu 50 detik.
            DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
        } catch (Throwable) {
        }

        $usersBaseline = $this->baselines['users'] ?? PHP_INT_MAX;

        try {
            DB::table('notifications')->where('notifiable_id', '>', $usersBaseline)->delete();
        } catch (Throwable) {
        }

        foreach (self::CLEANUP_TABLES as $table) {
            try {
                DB::table($table)->where('id', '>', $this->baselines[$table] ?? PHP_INT_MAX)->delete();
            } catch (Throwable) {
            }
        }

        $leftovers = [];

        try {
            if (DB::table('notifications')->where('notifiable_id', '>', $usersBaseline)->exists()) {
                $leftovers[] = 'notifications';
            }
        } catch (Throwable) {
            $leftovers[] = 'notifications (tidak terbaca)';
        }

        foreach (self::CLEANUP_TABLES as $table) {
            try {
                if (DB::table($table)->where('id', '>', $this->baselines[$table] ?? PHP_INT_MAX)->exists()) {
                    $leftovers[] = $table;
                }
            } catch (Throwable) {
                $leftovers[] = $table.' (tidak terbaca)';
            }
        }

        return $leftovers;
    }

    protected function baseline(string $table): int
    {
        return $this->baselines[$table] ?? 0;
    }

    /** Jumlah baris tabel yang dibuat tes ini (id di atas baseline), opsional per nilai `action`. */
    protected function rowsSince(string $table, ?string $action = null): int
    {
        $query = DB::table($table)->where('id', '>', $this->baseline($table));

        if ($action !== null) {
            $query->where('action', $action);
        }

        return $query->count();
    }

    /** Jumlah notifikasi untuk akun yang dibuat tes ini. */
    protected function notificationsSince(): int
    {
        return DB::table('notifications')->where('notifiable_id', '>', $this->baseline('users'))->count();
    }

    // --- data (koneksi aplikasi, autocommit) ---------------------------------------------------

    /**
     * Sesi manual (tanpa templat) besok 08.00-12.00 pada dokter dan poliklinik baru, kecuali ditimpa.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSession(int $quota = 5, array $overrides = []): PracticeSession
    {
        return PracticeSession::factory()->manual()->create($overrides + [
            'quota' => $quota,
            'session_date' => today()->addDay()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
    }

    /** Pasien berakun (kanal online): `Actor::patient()` membutuhkan `User`. */
    protected function makePatient(): PatientProfile
    {
        return PatientProfile::factory()->withAccount()->create();
    }

    /** @return list<PatientProfile> */
    protected function makePatients(int $count): array
    {
        $patients = [];

        for ($i = 0; $i < $count; $i++) {
            $patients[] = $this->makePatient();
        }

        return $patients;
    }

    /** Akun staf tanpa peran (service tidak memeriksa izin). */
    protected function makeStaff(): User
    {
        return User::factory()->staff()->create();
    }

    // --- koneksi PDO mentah --------------------------------------------------------------------

    protected function openConnection(int $lockWaitTimeoutSeconds = 1): PDO
    {
        $pdo = RawPdoConnection::open($lockWaitTimeoutSeconds);
        $this->connections[] = $pdo;

        return $pdo;
    }

    /**
     * SELECT ... FOR UPDATE: pembacaan berkunci selalu membaca nilai terbaru yang ter-commit.
     *
     * @return array<string, mixed>
     */
    protected function lockRow(PDO $pdo, string $table, int $id): array
    {
        $statement = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? FOR UPDATE");
        $statement->execute([$id]);

        return (array) $statement->fetch();
    }

    /**
     * Awal transaksi booking dengan urutan kunci baku: patient_profiles lalu practice_sessions.
     *
     * @return array<string, mixed> baris sesi yang terkunci
     */
    protected function beginAndLock(PDO $pdo, int $patientId, int $sessionId): array
    {
        $pdo->beginTransaction();
        $this->lockRow($pdo, 'patient_profiles', $patientId);

        return $this->lockRow($pdo, 'practice_sessions', $sessionId);
    }

    /**
     * Sisipkan appointment `pending` dan naikkan pencacah sesi (tanpa commit). Mengembalikan nomor antrean.
     *
     * @param  array<string, mixed>  $session  baris sesi yang terkunci (hasil beginAndLock)
     */
    protected function rawBook(PDO $pdo, int $patientId, array $session): int
    {
        $queue = (int) $session['last_queue_number'] + 1;
        $this->codeSequence++;
        $now = now()->toDateTimeString();

        $pdo->prepare(
            'INSERT INTO appointments (booking_code, patient_profile_id, practice_session_id, doctor_id, clinic_id, session_date, queue_number, status, channel, payment_type, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            sprintf('UPH-%04d-%04d', $this->codeSequence, random_int(0, 9999)),
            $patientId,
            $session['id'],
            $session['doctor_id'],
            $session['clinic_id'],
            $session['session_date'],
            $queue,
            'pending',
            'online',
            'umum',
            $now,
            $now,
        ]);

        $pdo->prepare('UPDATE practice_sessions SET booked_count = booked_count + 1, last_queue_number = ? WHERE id = ?')
            ->execute([$queue, $session['id']]);

        return $queue;
    }

    // --- proses anak ---------------------------------------------------------------------------

    /** Waktu (epoch) penghalang serempak untuk $processes proses anak. */
    protected function barrier(int $processes = 1): float
    {
        return microtime(true) + static::BARRIER_BASE + static::BARRIER_PER_PROCESS * $processes;
    }

    protected function waitUntil(float $epoch): void
    {
        while (microtime(true) < $epoch) {
            usleep(2000);
        }
    }

    /**
     * Menjalankan tests/Support/Concurrency/book-session.php sebagai proses anak.
     *
     * @param  string  $operation  `book` atau `transition`
     * @param  array<string, scalar>  $arguments  opsi skrip (tanpa `--`), mis. ['patient' => 1, 'session' => 2]
     */
    protected function spawn(string $operation, array $arguments, float $startAt, int $lockTimeoutSeconds = 20): Process
    {
        $command = [
            PHP_BINARY,
            base_path('tests/Support/Concurrency/book-session.php'),
            '--op='.$operation,
            '--start-at='.sprintf('%.6F', $startAt),
            '--lock-timeout='.$lockTimeoutSeconds,
        ];

        foreach ($arguments as $name => $value) {
            $command[] = "--{$name}={$value}";
        }

        $process = new Process($command, base_path(), $this->childEnvironment(), null, 90);
        $process->start();
        $this->processes[] = $process;

        return $process;
    }

    /**
     * Menunggu proses selesai dan mengurai baris JSON terakhir keluarannya. Proses yang gagal diurai menjadi
     * `['ok' => false, 'code' => 'PROC', ...]`; yang melewati batas waktu menjadi `code => 'TIMEOUT'`.
     *
     * @param  list<Process>  $processes
     * @return list<array<string, mixed>>
     */
    protected function gather(array $processes): array
    {
        $results = [];

        foreach ($processes as $process) {
            try {
                $process->wait();
            } catch (ProcessTimedOutException) {
                $process->stop(0);
                $results[] = ['ok' => false, 'code' => 'TIMEOUT'];

                continue;
            }

            $lines = array_values(array_filter(array_map('trim', explode("\n", $process->getOutput())), fn (string $line): bool => $line !== ''));
            $decoded = $lines === [] ? null : json_decode($lines[count($lines) - 1], true);

            $results[] = is_array($decoded)
                ? $decoded
                : [
                    'ok' => false,
                    'code' => 'PROC',
                    'exit' => $process->getExitCode(),
                    'detail' => trim($process->getErrorOutput().' | '.$process->getOutput()),
                ];
        }

        return $results;
    }

    /** @return array<string, string> */
    private function childEnvironment(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('database.connections.mariadb');

        $database = (string) $config['database'];
        DatabaseGuard::assertIsTestDatabase($database);

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => (string) $config['host'],
            'DB_PORT' => (string) $config['port'],
            'DB_DATABASE' => $database,
            'DB_USERNAME' => (string) $config['username'],
            'DB_PASSWORD' => (string) $config['password'],
            'DB_URL' => '',
            'DB_TIMEZONE' => (string) ($config['timezone'] ?? '+07:00'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'LOG_CHANNEL' => 'null',
            'BCRYPT_ROUNDS' => '4',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ];
    }

    // --- galat driver tersimulasi --------------------------------------------------------------

    /**
     * Memasang penutup `beforeExecuting` pada koneksi aplikasi: setiap kueri yang mengandung `$sqlFragment`
     * (huruf kecil) melempar galat driver palsu sebanyak `$times` kali pertama. `hits` menghitung semua kueri
     * yang cocok (termasuk yang lolos), `thrown` yang dilempar. Penutup tidak dapat dicabut: satu per tes.
     */
    protected function injectFault(string $sqlFragment, int $errno, string $sqlState, string $message, int $times = PHP_INT_MAX): stdClass
    {
        $state = new stdClass;
        $state->hits = 0;
        $state->thrown = 0;

        DB::connection()->beforeExecuting(function (string $query, array $bindings) use ($sqlFragment, $errno, $sqlState, $message, $times, $state): void {
            if (! str_contains(strtolower($query), $sqlFragment)) {
                return;
            }

            $state->hits++;

            if ($state->thrown >= $times) {
                return;
            }

            $state->thrown++;

            $driver = new PDOException($message);
            $driver->errorInfo = [$sqlState, $errno, $message];

            // Laravel membungkus pelanggaran UNIQUE (1062) sebagai subkelas; BookingService menangkap subkelas itu.
            $class = $errno === 1062 ? UniqueConstraintViolationException::class : QueryException::class;

            throw new $class('mariadb', $query, $bindings, $driver);
        });

        return $state;
    }

    // --- asersi bersama ------------------------------------------------------------------------

    /**
     * Invarian sesi (I1, I2, I4): `booked_count` = appointment non-batal; `booked_count <= quota`; nomor antrean
     * unik dan tidak pernah dipakai ulang (`last_queue_number` = jumlah appointment yang pernah dibuat, antrean 1..n).
     */
    protected function assertSessionIsConsistent(PracticeSession $session): void
    {
        $fresh = PracticeSession::query()->findOrFail($session->getKey());
        $appointments = Appointment::query()->where('practice_session_id', $session->getKey())->get(['id', 'status', 'queue_number']);
        $consuming = $appointments->filter(fn (Appointment $appointment): bool => $appointment->status !== AppointmentStatus::Cancelled)->count();
        $queues = $appointments->pluck('queue_number')->map(fn ($queue): int => (int) $queue)->all();

        $this->assertSame($consuming, (int) $fresh->booked_count, 'I1: booked_count harus sama dengan jumlah appointment non-batal.');
        $this->assertLessThanOrEqual((int) $fresh->quota, (int) $fresh->booked_count, 'I2: booked_count tidak boleh melebihi kuota.');
        $this->assertSame(count($queues), count(array_unique($queues)), 'Nomor antrean harus unik.');
        $this->assertSame($appointments->count(), (int) $fresh->last_queue_number, 'last_queue_number harus sama dengan jumlah appointment yang pernah dibuat (antrean tidak dipakai ulang).');

        if ($queues !== []) {
            sort($queues);
            $this->assertSame(range(1, count($queues)), $queues, 'Nomor antrean harus 1..n tanpa lubang.');
        }
    }

    /**
     * Tidak ada proses anak yang gagal secara infrastruktur (proses mati, waktu habis, galat DB tak terduga,
     * pengecualian tak terduga) dan tidak ada `SYSTEM_BUSY` (jalur normal tanpa kunci yang ditahan).
     *
     * @param  list<array<string, mixed>>  $results
     */
    protected function assertNoInfrastructureErrors(array $results): void
    {
        foreach ($results as $result) {
            $code = (string) ($result['code'] ?? '');
            $unexpected = ($result['ok'] ?? false) !== true
                && (str_starts_with($code, 'DB_') || str_starts_with($code, 'EXC_') || in_array($code, ['PROC', 'TIMEOUT', 'SYSTEM_BUSY'], true));

            $this->assertFalse($unexpected, 'Hasil proses anak tidak wajar: '.json_encode($result));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    protected function successes(array $results): array
    {
        return array_values(array_filter($results, fn (array $result): bool => ($result['ok'] ?? false) === true));
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<string> kode galat proses yang gagal, terurut
     */
    protected function failureCodes(array $results): array
    {
        $codes = array_map(
            fn (array $result): string => (string) $result['code'],
            array_values(array_filter($results, fn (array $result): bool => ($result['ok'] ?? false) !== true)),
        );

        sort($codes);

        return $codes;
    }
}
