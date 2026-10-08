<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Exceptions\SanitizedBookingFault;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Satu-satunya jalur pembuatan pendaftaran kunjungan (00 F.5-F.7, F6-R12, F6-R13, F6-R35).
 *
 * Konkurensi (F6-R13): `DB::transaction(..., 3)` dibungkus loop luar. Urutan kunci baku `patient_profiles` ->
 * `practice_sessions` -> INSERT `appointments`; kunci hanya lewat PK dan pernyataan PERTAMA transaksi adalah kunci
 * profil (snapshot REPEATABLE READ baru dibuat sesudah kedua kunci). Karena itu pengaturan (`booking.*`) dibaca
 * SEBELUM transaksi dan tidak ada SELECT lain sebelum kedua kunci. Closure transaksi tanpa efek samping di luar
 * DB: notifikasi dijadwalkan `DB::afterCommit` oleh AppointmentNotifier. Pelanggaran UNIQUE/CHECK (lapis DB
 * terakhir) dipetakan ke BookingException; deadlock/lock wait yang lolos dari 3 percobaan menjadi SYSTEM_BUSY.
 *
 * `Cache::lock` anti-kirim-ganda sengaja BUKAN di sini melainkan di controller (F6-R9) supaya tes konkurensi
 * tetap menguji lapis DB.
 */
final class BookingService
{
    /** Percobaan `DB::transaction` (hanya berlaku di transaksi terluar; deadlock/lock wait diulang utuh). */
    public const TRANSACTION_ATTEMPTS = 3;

    /** Putaran luar untuk tabrakan UNIQUE kode booking/nomor antrean (kode baru tiap putaran). */
    public const UNIQUE_ROUNDS = 3;

    /** Percobaan memilih kode booking yang belum ada di dalam satu putaran. */
    public const CODE_ATTEMPTS = 5;

    private const INDEX_ACTIVE_DUPLICATE = 'appointments_patient_session_active_unique';

    private const INDEX_BOOKING_CODE = 'appointments_booking_code_unique';

    private const INDEX_QUEUE_NUMBER = 'appointments_practice_session_id_queue_number_unique';

    private const CONSTRAINT_QUOTA = 'chk_sessions_quota';

    /** MariaDB 10.4 melaporkan CHECK dengan 4025, MySQL 8 dengan 3819. */
    private const CHECK_ERRORS = [4025, 3819];

    /** 1205 = lock wait timeout, 1213 = deadlock. */
    private const CONCURRENCY_ERRORS = [1205, 1213];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Membuat pendaftaran. Pelaku `patient` hanya boleh untuk profilnya sendiri; pelaku `staff` untuk profil
     * mana pun (kanal loket, status awal `confirmed`); pelaku `system` ditolak.
     *
     * @throws BookingException kode F.5 (SESSION_UNAVAILABLE ... INVALID_PAYMENT) atau SYSTEM_BUSY
     * @throws AuthorizationException pasien mendaftarkan profil orang lain
     */
    public function book(PatientProfile $profile, int $practiceSessionId, BookingData $data, Actor $actor): Appointment
    {
        $this->assertActorMayBook($profile, $actor);

        // Dibaca di luar transaksi: pembacaan pertama di dalam transaksi harus kunci profil (F6-R13).
        $rules = $this->settings->bookingRules();

        $ids = ['practice_session_id' => $practiceSessionId, 'patient_profile_id' => (int) $profile->getKey()];
        $round = 0;

        while (true) {
            $round++;

            try {
                $appointment = DB::transaction(
                    fn (): Appointment => $this->createInTransaction($rules, $profile, $practiceSessionId, $data, $actor),
                    self::TRANSACTION_ATTEMPTS,
                );
            } catch (UniqueConstraintViolationException $e) {
                if ($round < self::UNIQUE_ROUNDS && $this->isRetryableUniqueViolation($e)) {
                    continue;
                }

                throw $this->translate($e, $actor, $ids);
            } catch (QueryException|DeadlockException $e) {
                throw $this->translate($e, $actor, $ids);
            }

            return Appointment::query()
                ->with(['clinic', 'doctor', 'practiceSession'])
                ->findOrFail($appointment->getKey());
        }
    }

    /**
     * Pemeriksaan awal F.5 tanpa kunci, tanpa transaksi, dan tanpa menulis apa pun (F6-R12): sesi dimuat ulang
     * dengan SELECT biasa supaya hitungan kuota tidak basi. Hasilnya tidak mengikat (book() tetap memeriksa
     * ulang di bawah kunci) dan tidak pernah SYSTEM_BUSY.
     *
     * @throws BookingException kode F.5
     */
    public function precheck(PatientProfile $profile, PracticeSession $session, BookingData $data, Actor $actor): void
    {
        $this->assertActorMayBook($profile, $actor);

        if (! $profile->is_active) {
            throw BookingException::profileInactive();
        }

        $current = PracticeSession::query()->with(['clinic', 'doctor'])->find($session->getKey());

        if ($current === null) {
            throw BookingException::make(BookingException::SESSION_UNAVAILABLE);
        }

        $this->settings->bookingRules()->assertBookable($profile, $current, $data, $actor, CarbonImmutable::now());
    }

    // ------------------------------------------------------------------ di dalam transaksi

    private function createInTransaction(BookingRules $rules, PatientProfile $profile, int $practiceSessionId, BookingData $data, Actor $actor): Appointment
    {
        // 1) Kunci profil (pernyataan pertama), lalu periksa ulang bahwa profil masih aktif.
        $locked = PatientProfile::query()->whereKey($profile->getKey())->lockForUpdate()->first();

        if ($locked === null || ! $locked->is_active) {
            throw BookingException::profileInactive();
        }

        // 2) Kunci sesi (sesudah profil; urutan baku).
        $session = PracticeSession::query()->whereKey($practiceSessionId)->lockForUpdate()->first();

        if ($session === null) {
            throw BookingException::make(BookingException::SESSION_UNAVAILABLE);
        }

        // 3) Relasi untuk aturan F.5 dimuat SESUDAH kedua kunci.
        $session->load(['clinic', 'doctor']);
        $now = CarbonImmutable::now();

        // 4) Delapan aturan F.5 (urutan 1 sampai 8) di bawah kunci.
        $rules->assertBookable($locked, $session, $data, $actor, $now);

        $status = ($actor->isStaff() || $rules->autoConfirm) ? AppointmentStatus::Confirmed : AppointmentStatus::Pending;
        $channel = $actor->channel();
        $queueNumber = (int) $session->last_queue_number + 1;
        $code = $this->uniqueCode((int) $session->getKey(), (int) $locked->getKey());

        // 5) Kuota dan antrean (nomor antrean tidak pernah dipakai ulang).
        $session->forceFill([
            'booked_count' => (int) $session->booked_count + 1,
            'last_queue_number' => $queueNumber,
        ])->save();

        // 6) Pendaftaran; kolom jadwal disalin dari sesi (BR-52).
        $appointment = (new Appointment)->forceFill([
            'booking_code' => $code,
            'patient_profile_id' => $locked->getKey(),
            'practice_session_id' => $session->getKey(),
            'doctor_id' => $session->doctor_id,
            'clinic_id' => $session->clinic_id,
            'session_date' => $session->session_date->toDateString(),
            'queue_number' => $queueNumber,
            'status' => $status,
            'channel' => $channel,
            'payment_type' => $data->paymentType,
            'insurance_partner_id' => $data->insurancePartnerId,
            'payment_reference' => $data->paymentReference,
            'payment_reference_last4' => $data->paymentReferenceLast4(),
            'complaint' => $data->complaint,
            'created_by_user_id' => $actor->isStaff() ? $actor->userId : null,
            'confirmed_at' => $status === AppointmentStatus::Confirmed ? $now : null,
        ]);
        $appointment->save();

        // 7) Riwayat status pertama (from_status NULL).
        AppointmentStatusHistory::query()->create([
            'appointment_id' => $appointment->getKey(),
            'from_status' => null,
            'to_status' => $status,
            'actor_type' => $actor->type,
            'actor_user_id' => $actor->userId,
            'reason' => $actor->isPatient() && $status === AppointmentStatus::Confirmed ? 'Konfirmasi otomatis' : null,
        ]);

        // 8) Audit tanpa nama, telepon, nomor BPJS/polis, dan keluhan (F6-R35).
        $this->audit->log(
            'appointment.created',
            $appointment,
            null,
            [
                'booking_code' => $code,
                'queue_number' => $queueNumber,
                'status' => $status->value,
                'channel' => $channel->value,
                'payment_type' => $data->paymentType->value,
            ],
            "Pendaftaran {$code} dibuat ({$channel->label()}).",
            $actor,
        );

        // 9) "Simpan nomor BPJS ke profil saya" (hook `saving` model mengisi bpjs_number_last4).
        if ($data->saveBpjs && $data->paymentType === PaymentType::Bpjs && $data->paymentReference !== null) {
            $locked->bpjs_number = $data->paymentReference;
            $locked->save();

            // R-a: perubahan profil harus tercatat. AuditLogger meredaksi bpjs_number otomatis; last4 turunan dibuang.
            $diff = $this->audit->diff($locked);
            $old = array_diff_key($diff['old'], ['bpjs_number_last4' => true]);
            $new = array_diff_key($diff['new'], ['bpjs_number_last4' => true]);

            if ($new !== []) {
                $this->audit->log(
                    'patient.updated',
                    $locked,
                    $old,
                    $new,
                    'Profil pasien '.($locked->patient_number ?? '-').' diperbarui (nomor BPJS disimpan dari pendaftaran).',
                    $actor,
                );
            }
        }

        // 10) Notifikasi dijadwalkan sesudah commit (dan hanya bila profil punya akun).
        $this->notifier->statusChanged($appointment, null, $status);

        return $appointment;
    }

    /**
     * Kode booking yang belum dipakai, diperiksa di bawah kunci. Tabrakan yang lolos (balapan antar-sesi) tetap
     * ditangkap UNIQUE dan diulang oleh loop luar.
     */
    private function uniqueCode(int $sessionId, int $profileId): string
    {
        for ($attempt = 0; $attempt < self::CODE_ATTEMPTS; $attempt++) {
            $code = BookingCodeGenerator::generate();

            if (! Appointment::query()->where('booking_code', $code)->exists()) {
                return $code;
            }
        }

        SanitizedBookingFault::capture('SYSTEM_BUSY (kode booking habis percobaan)', null, ['practice_session_id' => $sessionId, 'patient_profile_id' => $profileId]);

        throw BookingException::make(BookingException::SYSTEM_BUSY);
    }

    // ------------------------------------------------------------------ pelaku

    /**
     * Pemeriksaan tanpa DB: pelaku sistem tidak membuat pendaftaran; pasien hanya untuk profilnya sendiri.
     */
    private function assertActorMayBook(PatientProfile $profile, Actor $actor): void
    {
        if ($actor->isSystem()) {
            throw new LogicException('Pendaftaran tidak dapat dibuat oleh pelaku sistem.');
        }

        if ($actor->isPatient() && ($profile->user_id === null || (int) $profile->user_id !== $actor->userId)) {
            throw new AuthorizationException('Pasien hanya dapat mendaftarkan dirinya sendiri.');
        }
    }

    // ------------------------------------------------------------------ penerjemahan galat driver

    private function isRetryableUniqueViolation(UniqueConstraintViolationException $e): bool
    {
        return in_array($this->violatedIndex($e), [self::INDEX_BOOKING_CODE, self::INDEX_QUEUE_NUMBER], true);
    }

    /**
     * Mengubah galat DB menjadi BookingException; mengembalikan galat aslinya bila bukan kasus yang dikenal
     * (galat sistem 500 yang tercatat). Kegagalan sistem dilaporkan dalam bentuk tersanitasi (tanpa bindings).
     *
     * @param  array{practice_session_id: int, patient_profile_id: int}  $ids
     */
    private function translate(QueryException|DeadlockException $e, Actor $actor, array $ids): Throwable
    {
        if ($e instanceof UniqueConstraintViolationException) {
            $index = $this->violatedIndex($e);

            if ($index === self::INDEX_ACTIVE_DUPLICATE) {
                return BookingException::make(
                    BookingException::DUPLICATE_BOOKING,
                    ['subjek' => $actor->isStaff() ? 'pasien ini' : 'Anda'],
                    previous: $e,
                );
            }

            if ($index === self::INDEX_BOOKING_CODE || $index === self::INDEX_QUEUE_NUMBER) {
                // Tabrakan berulang tidak seharusnya terjadi: catat dan minta pengguna mencoba lagi.
                SanitizedBookingFault::capture('SYSTEM_BUSY (tabrakan kode/antrean)', $e, $ids);

                return BookingException::make(BookingException::SYSTEM_BUSY, previous: $e);
            }

            return $e;
        }

        if ($e instanceof QueryException && $this->isQuotaCheckViolation($e)) {
            // Hanya terpicu bila pencacah booked_count menyimpang dari kenyataan: catat agar kerusakan data terlihat.
            SanitizedBookingFault::capture('QUOTA_DRIFT (chk_sessions_quota)', $e, $ids);

            return BookingException::make(BookingException::QUOTA_FULL, previous: $e);
        }

        if ($this->isConcurrencyFailure($e)) {
            SanitizedBookingFault::capture('SYSTEM_BUSY (deadlock/lock wait)', $e, $ids);

            return BookingException::make(BookingException::SYSTEM_BUSY, previous: $e);
        }

        return $e;
    }

    /**
     * Nama indeks dari pesan driver: MariaDB `for key 'nama'`, MySQL 8 `for key 'tabel.nama'`. Memakai
     * `errorInfo[2]` (pesan driver tanpa SQL dan bindings).
     */
    private function violatedIndex(QueryException $e): string
    {
        $message = (string) ($e->errorInfo[2] ?? $e->getMessage());

        if (preg_match("/for key '([^']+)'\s*$/", $message, $matches) !== 1) {
            return '';
        }

        $key = $matches[1];
        $dot = strrpos($key, '.');

        return $dot === false ? $key : substr($key, $dot + 1);
    }

    /**
     * CHECK `chk_sessions_quota` (bukan `chk_sessions_quota_range`): nama dengan tanda kutip penutup agar
     * awalan yang sama tidak cocok.
     */
    private function isQuotaCheckViolation(QueryException $e): bool
    {
        if (! in_array((int) ($e->errorInfo[1] ?? 0), self::CHECK_ERRORS, true)) {
            return false;
        }

        $message = (string) ($e->errorInfo[2] ?? $e->getMessage());

        return str_contains($message, '`'.self::CONSTRAINT_QUOTA.'`')
            || str_contains($message, "'".self::CONSTRAINT_QUOTA."'");
    }

    /**
     * Deadlock/lock wait yang tersisa sesudah 3 percobaan. Di dalam transaksi pembungkus (tes dengan
     * RefreshDatabase) Laravel melempar DeadlockException tanpa mengulang.
     */
    private function isConcurrencyFailure(QueryException|DeadlockException $e): bool
    {
        if ($e instanceof DeadlockException) {
            return true;
        }

        if (in_array((int) ($e->errorInfo[1] ?? 0), self::CONCURRENCY_ERRORS, true)) {
            return true;
        }

        return app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($e);
    }
}
