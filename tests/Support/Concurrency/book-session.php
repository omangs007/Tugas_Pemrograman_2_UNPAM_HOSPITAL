<?php

/**
 * Pembantu tes konkurensi fase 6 dan 7a (F6-R34, F7A-R27). Dijalankan SEBAGAI PROSES PHP TERPISAH oleh
 * tests/Support/Concurrency/ConcurrencyTestCase::spawn(); bukan kelas tes dan tidak dimuat aplikasi.
 *
 *   php book-session.php --op=book --patient=ID --session=ID [--as=patient|staff] [--actor-user=USER_ID]
 *   php book-session.php --op=transition --appointment=ID --to=STATUS --as=patient|staff|system
 *                        [--actor-user=USER_ID] [--reason=TEKS] [--expected=STATUS]
 *   php book-session.php --op=mark-no-show --session=ID [--as=staff|system] [--actor-user=USER_ID] [--reason=TEKS]
 *   php book-session.php --op=link --profile=ID --account=USER_ID --actor-user=USER_ID
 *   opsi bersama: [--start-at=EPOCH.FRAK] [--lock-timeout=DETIK]
 *
 * Keluaran: SATU baris JSON pada baris terakhir stdout, mis.
 *   {"ok":true,"queue":2,"booking_code":"UPH-...","status":"pending","begin":1.7e9,"end":1.7e9}
 *   {"ok":true,"marked":3,"begin":...,"end":...}                      (mark-no-show)
 *   {"ok":true,"profile":12,"user":7,"begin":...,"end":...}           (link)
 *   {"ok":false,"code":"QUOTA_FULL","message":"...","begin":...,"end":...}
 * `code` = kode BookingException/InvalidTransitionException; `VALIDATION` untuk ValidationException (message =
 * pesan pertama); `DB_<errno>` untuk QueryException tak terduga; `EXC_<kelas>` untuk pengecualian lain. Kode keluar 0
 * untuk semua hasil domain; 3 bila lingkungan bukan database uji (pagar keamanan: tidak pernah menulis ke database
 * selain `*_test`).
 */

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Booking\AppointmentStatusService;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Services\Patients\PatientAccountLinker;
use App\Support\Actor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\DatabaseGuard;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! $app->environment('testing')) {
        throw new RuntimeException('Skrip ini hanya boleh berjalan dengan APP_ENV=testing.');
    }

    DatabaseGuard::assertIsTestDatabase((string) DB::connection()->getDatabaseName());
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);

    exit(3);
}

$options = getopt('', [
    'op:',
    'patient:',
    'session:',
    'appointment:',
    'profile:',
    'account:',
    'to:',
    'as::',
    'actor-user::',
    'reason::',
    'expected::',
    'start-at::',
    'lock-timeout::',
]);

$emit = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
};

$begin = null;

try {
    if (isset($options['lock-timeout'])) {
        DB::statement('SET SESSION innodb_lock_wait_timeout = '.max(1, (int) $options['lock-timeout']));
    }

    // Koneksi dibuka dan service dimuat SEBELUM penghalang agar waktu setelahnya hanya milik operasi.
    DB::connection()->getPdo();

    $operation = (string) ($options['op'] ?? 'book');
    $as = (string) ($options['as'] ?? 'patient');

    if ($operation === 'book') {
        $service = $app->make(BookingService::class);
        $profile = PatientProfile::query()->with('user')->findOrFail((int) $options['patient']);
        $sessionId = (int) $options['session'];
        $actor = $as === 'staff'
            ? Actor::staff(User::query()->findOrFail((int) $options['actor-user']))
            : Actor::patient($profile->user);

        $action = static function () use ($service, $profile, $sessionId, $actor): array {
            $appointment = $service->book($profile, $sessionId, new BookingData(PaymentType::Umum), $actor);

            return [
                'queue' => $appointment->queue_number,
                'booking_code' => $appointment->booking_code,
                'status' => $appointment->status->value,
            ];
        };
    } elseif ($operation === 'transition') {
        $service = $app->make(AppointmentStatusService::class);
        $appointment = Appointment::query()->findOrFail((int) $options['appointment']);
        $to = AppointmentStatus::from((string) $options['to']);
        $expected = isset($options['expected']) ? AppointmentStatus::from((string) $options['expected']) : null;
        $reason = isset($options['reason']) ? (string) $options['reason'] : null;
        $actor = match ($as) {
            'staff' => Actor::staff(User::query()->findOrFail((int) $options['actor-user'])),
            'system' => Actor::system(),
            default => Actor::patient(User::query()->findOrFail((int) $options['actor-user'])),
        };

        $action = static function () use ($service, $appointment, $to, $actor, $reason, $expected): array {
            $updated = $service->transition($appointment, $to, $actor, $reason, $expected);

            return ['status' => $updated->status->value];
        };
    } elseif ($operation === 'mark-no-show') {
        // Aksi massal "Tandai tidak hadir" satu sesi (T2): petugas (`--as=staff`) atau sistem (`--as=system`).
        $service = $app->make(AppointmentStatusService::class);
        $session = PracticeSession::query()->findOrFail((int) $options['session']);
        $reason = isset($options['reason']) ? (string) $options['reason'] : null;
        $actor = $as === 'system'
            ? Actor::system()
            : Actor::staff(User::query()->findOrFail((int) $options['actor-user']));

        $action = static function () use ($service, $session, $actor, $reason): array {
            return ['marked' => $service->markNoShowForSession($session, $actor, $reason)];
        };
    } elseif ($operation === 'link') {
        // Hubungkan profil walk-in ke akun pasien (T3); selalu oleh petugas.
        $linker = $app->make(PatientAccountLinker::class);
        $walkIn = PatientProfile::query()->findOrFail((int) $options['profile']);
        $account = User::query()->findOrFail((int) $options['account']);
        $actor = Actor::staff(User::query()->findOrFail((int) $options['actor-user']));

        $action = static function () use ($linker, $walkIn, $account, $actor): array {
            $linked = $linker->link($walkIn, $account, $actor);

            return ['profile' => (int) $linked->getKey(), 'user' => (int) $account->getKey()];
        };
    } else {
        throw new InvalidArgumentException("Operasi tidak dikenal: {$operation}.");
    }

    $startAt = (float) ($options['start-at'] ?? 0);

    while (microtime(true) < $startAt) {
        usleep(500);
    }

    $begin = microtime(true);
    $payload = $action();

    $emit(['ok' => true] + $payload + ['begin' => $begin, 'end' => microtime(true)]);
} catch (BookingException $e) {
    $emit(['ok' => false, 'code' => $e->errorCode, 'message' => $e->getMessage(), 'begin' => $begin, 'end' => microtime(true)]);
} catch (ValidationException $e) {
    $emit(['ok' => false, 'code' => 'VALIDATION', 'message' => (string) collect($e->errors())->flatten()->first(), 'begin' => $begin, 'end' => microtime(true)]);
} catch (QueryException $e) {
    $emit(['ok' => false, 'code' => 'DB_'.($e->errorInfo[1] ?? 'ERR'), 'message' => $e->getMessage(), 'begin' => $begin, 'end' => microtime(true)]);
} catch (Throwable $e) {
    $emit(['ok' => false, 'code' => 'EXC_'.(new ReflectionClass($e))->getShortName(), 'message' => $e->getMessage(), 'begin' => $begin, 'end' => microtime(true)]);
}
