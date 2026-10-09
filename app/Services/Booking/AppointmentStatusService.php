<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\SessionStatus;
use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SanitizedBookingFault;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur tulis status appointment setelah pembuatan (00 F.8, F6-R15). Tabel transisi T4-T10
 * ada sebagai data (TRANSITIONS dan RULES): pembuatan (T1-T3) milik BookingService. Kunci baku 00 F.6:
 * patient_profiles, practice_sessions, appointments (hanya lewat PK) sebelum kueri lain; semua pemeriksaan
 * dibaca ulang di bawah kunci. Otorisasi (izin Spatie, Policy) ada di lapisan HTTP; service menegakkan
 * aturan domain menurut JENIS pelaku dan kepemilikan. Pemeriksaan aturan ada di SATU evaluator (F7A-R5) yang
 * dipakai `transition()` (di bawah kunci) dan `transitionChecks()` (tombol UI, tanpa kunci), sehingga tombol
 * tidak pernah berbeda dari aturan server.
 */
final class AppointmentStatusService
{
    public const REASON_MIN_LENGTH = 5;

    public const REASON_MAX_LENGTH = 255;

    /** Peta dari-ke yang sah (00 F.8). Status final tidak punya jalan keluar. Dasar kandidat `transitionChecks()`. */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'checked_in', 'cancelled', 'no_show'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['completed', 'confirmed'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    /**
     * Tabel F.8 T4-T10 per pasangan "dari>ke": `actors` = jenis pelaku yang boleh; `window` = jendela waktu
     * (before_end, checkin_window, same_day, cancel_rule, after_end, atau null); `reason` = jenis pelaku yang
     * wajib mengisi alasan (5-255 karakter).
     */
    public const RULES = [
        'pending>confirmed' => ['step' => 'T4', 'actors' => ['staff'], 'window' => 'before_end', 'reason' => []],
        'pending>checked_in' => ['step' => 'T5', 'actors' => ['staff'], 'window' => 'checkin_window', 'reason' => []],
        'confirmed>checked_in' => ['step' => 'T5', 'actors' => ['staff'], 'window' => 'checkin_window', 'reason' => []],
        'checked_in>completed' => ['step' => 'T6', 'actors' => ['staff'], 'window' => null, 'reason' => []],
        'checked_in>confirmed' => ['step' => 'T7', 'actors' => ['staff'], 'window' => 'same_day', 'reason' => ['staff']],
        'pending>cancelled' => ['step' => 'T8/T9', 'actors' => ['patient', 'staff', 'system'], 'window' => 'cancel_rule', 'reason' => ['staff', 'system']],
        'confirmed>cancelled' => ['step' => 'T8/T9', 'actors' => ['patient', 'staff', 'system'], 'window' => 'cancel_rule', 'reason' => ['staff', 'system']],
        'pending>no_show' => ['step' => 'T10', 'actors' => ['staff', 'system'], 'window' => 'after_end', 'reason' => []],
        'confirmed>no_show' => ['step' => 'T10', 'actors' => ['staff', 'system'], 'window' => 'after_end', 'reason' => []],
    ];

    /**
     * Status tujuan yang boleh diminta lewat aksi per appointment di UI (F7A-R5, F7A-R18). `no_show` TIDAK
     * termasuk: hanya lewat aksi massal per sesi (`markNoShowForSession`) atau perintah terjadwal.
     */
    public const INDIVIDUAL_TARGETS = [
        AppointmentStatus::Confirmed,
        AppointmentStatus::CheckedIn,
        AppointmentStatus::Completed,
        AppointmentStatus::Cancelled,
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Mengubah status satu appointment. `$expected` (BR-67) = status yang dilihat pengguna saat memuat
     * halaman; berbeda dengan status di bawah kunci → STALE_STATUS. Instance `$appointment` pemanggil
     * disinkronkan dengan keadaan baru dan dikembalikan.
     *
     * @throws InvalidTransitionException aturan F.8 dilanggar (tidak ada yang ditulis)
     * @throws BookingException SYSTEM_BUSY bila galat konkurensi (QueryException atau DeadlockException) lolos dari 3 percobaan
     */
    public function transition(Appointment $appointment, AppointmentStatus $to, Actor $actor, ?string $reason = null, ?AppointmentStatus $expected = null): Appointment
    {
        $reason = $this->normalizeReason($reason);

        if ($actor->isPatient() && $reason !== null) {
            $reason = mb_substr($reason, 0, self::REASON_MAX_LENGTH);
        }

        $appointmentId = (int) $appointment->getKey();
        $profileId = (int) $appointment->patient_profile_id;
        $sessionId = (int) $appointment->practice_session_id;

        try {
            $locked = DB::transaction(
                fn (): Appointment => $this->applyLocked($appointmentId, $profileId, $sessionId, $to, $actor, $reason, $expected),
                3,
            );
        } catch (QueryException|DeadlockException $e) {
            // DeadlockException dilempar Laravel di dalam transaksi pembungkus (tanpa pengulangan); selalu galat konkurensi.
            if ($e instanceof DeadlockException || app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($e)) {
                SanitizedBookingFault::capture('SYSTEM_BUSY (transisi status)', $e, ['practice_session_id' => $sessionId, 'patient_profile_id' => $profileId]);

                throw BookingException::make(BookingException::SYSTEM_BUSY, [], [], $e);
            }

            throw $e;
        }

        $appointment->setRawAttributes($locked->getRawOriginal(), true);
        $appointment->setRelations($locked->getRelations());

        return $appointment;
    }

    /**
     * Pemeriksaan tombol untuk satu appointment (F7A-R5): kandidat = TRANSITIONS[status] yang juga ada di
     * INDIVIDUAL_TARGETS (urut INDIVIDUAL_TARGETS); status final menghasilkan []. Memakai evaluator yang sama
     * dengan `transition()` tetapi tanpa kunci (SELECT biasa) dan MENGABAIKAN `expected` dan alasan (modal
     * mengumpulkan alasan; server tetap memeriksa). Kandidat yang ditolak tetap dikembalikan dengan kode dan
     * pesan penolakan (mis. `CHECKIN_NOT_OPEN` + `opensAt()` untuk tombol "Dibuka HH.MM").
     *
     * @return list<TransitionCheck>
     */
    public function transitionChecks(Appointment $appointment, Actor $actor, ?CarbonImmutable $now = null): array
    {
        $appointment->loadMissing(['practiceSession', 'patientProfile']);

        $from = $appointment->status;

        if ($from->isFinal()) {
            return [];
        }

        $moment = $this->momentOf($now);
        $legal = self::TRANSITIONS[$from->value];
        $checks = [];

        foreach (self::INDIVIDUAL_TARGETS as $to) {
            if (! in_array($to->value, $legal, true)) {
                continue;
            }

            $rejection = $this->evaluate($appointment->patientProfile, $appointment->practiceSession, $from, $to, $actor, $moment, false);

            $checks[] = $rejection === null
                ? new TransitionCheck($to)
                : new TransitionCheck($to, $rejection['code'], (string) __('booking.transitions.'.$rejection['key'], $rejection['replace']), $rejection['replace']);
        }

        return $checks;
    }

    /**
     * Status tujuan yang boleh dipilih aktor sekarang (tombol yang tampil): pemeriksaan `transitionChecks()`
     * yang `allowed()`, dalam urutan yang sama.
     *
     * @return list<AppointmentStatus>
     */
    public function availableTransitions(Appointment $appointment, Actor $actor, ?CarbonImmutable $now = null): array
    {
        $allowed = array_filter(
            $this->transitionChecks($appointment, $actor, $now),
            fn (TransitionCheck $check): bool => $check->allowed(),
        );

        return array_values(array_map(fn (TransitionCheck $check): AppointmentStatus => $check->to, $allowed));
    }

    /**
     * Menandai `no_show` semua appointment `pending`/`confirmed` pada satu sesi yang sudah berakhir (F7A-R6,
     * BR-65/BR-66). Setiap appointment diproses satu per satu lewat transition() (transaksi sendiri, urutan kunci
     * baku, 3 percobaan) TANPA transaksi luar, sehingga deadlock tidak merembet lintas pasien dan proses aman
     * diulang. Appointment yang statusnya sudah diubah pihak lain (transisi tak sah, status final) dilewati
     * dan tidak dihitung; SYSTEM_BUSY menghentikan proses dan diteruskan ke pemanggil. `expected` tidak dipakai.
     *
     * @return int jumlah appointment yang benar-benar ditandai
     *
     * @throws InvalidTransitionException sesi `needs_review` (INVALID_TRANSITION) atau belum berakhir (SESSION_NOT_ENDED); tidak ada yang ditulis
     * @throws BookingException SYSTEM_BUSY
     */
    public function markNoShowForSession(PracticeSession $session, Actor $actor, ?string $reason = null): int
    {
        $sessionId = (int) $session->getKey();
        // Baca ulang (SELECT biasa): status dan jam pada instance pemanggil bisa basi.
        $current = PracticeSession::query()->findOrFail($sessionId);

        if ($current->status === SessionStatus::NeedsReview) {
            throw InvalidTransitionException::makeVariant(
                InvalidTransitionException::INVALID_TRANSITION,
                'SESSION_UNDER_REVIEW',
                [],
                ['practice_session_id' => $sessionId],
            );
        }

        // Batas strik (sama dengan jendela after_end): tepat pada end_time belum berakhir. Bukan PracticeSession::isEnded() (<=).
        if (! $this->momentOf(null)->greaterThan($current->ends_at)) {
            throw InvalidTransitionException::make(
                InvalidTransitionException::SESSION_NOT_ENDED,
                ['time' => $current->ends_at->format('H.i')],
                ['practice_session_id' => $sessionId],
            );
        }

        $appointments = Appointment::query()
            ->where('appointments.practice_session_id', $sessionId)
            ->whereIn('appointments.status', $this->awaitingStatuses())
            ->orderBy('appointments.queue_number')
            ->orderBy('appointments.id')
            ->get();

        $marked = 0;

        foreach ($appointments as $appointment) {
            try {
                $this->transition($appointment, AppointmentStatus::NoShow, $actor, $reason);
                $marked++;
            } catch (InvalidTransitionException) {
                // Diubah pihak lain sejak daftar dibaca (check-in, sudah no_show, dibatalkan): dilewati, tidak dihitung.
                continue;
            }
        }

        return $marked;
    }

    /**
     * Menandai tidak hadir semua sesi yang sudah berakhir pada `$date` (null = semua sesi lampau yang masih punya
     * appointment `pending`/`confirmed`). Sesi `needs_review` tidak pernah masuk daftar; sesi yang berubah
     * menjadi `needs_review` di tengah proses dilewati. `SYSTEM_BUSY` diteruskan ke pemanggil.
     *
     * @return int total appointment yang ditandai
     */
    public function markNoShowForDate(?CarbonInterface $date, Actor $actor): int
    {
        $total = 0;

        foreach ($this->sessionsAwaitingNoShow($date) as $session) {
            try {
                $total += $this->markNoShowForSession($session, $actor);
            } catch (InvalidTransitionException) {
                continue;
            }
        }

        return $total;
    }

    /**
     * Sesi yang sudah berakhir dan masih punya appointment `pending`/`confirmed` (F7A-R6): status `open`/`closed`
     * (bukan `needs_review`/`cancelled`), `now > ends_at` STRIK (tepat `end_time` belum berakhir), opsional satu
     * tanggal; urut `session_date`, `start_time`, `id`; eager `clinic` dan `doctor`.
     *
     * @return Collection<int, PracticeSession>
     */
    public function sessionsAwaitingNoShow(?CarbonInterface $date = null, ?CarbonImmutable $now = null): Collection
    {
        $moment = $this->momentOf($now);

        return PracticeSession::query()
            ->with(['clinic', 'doctor'])
            ->whereIn('practice_sessions.status', [SessionStatus::Open->value, SessionStatus::Closed->value])
            ->where(function (Builder $ended) use ($moment): void {
                $ended->where('practice_sessions.session_date', '<', $moment->toDateString())
                    ->orWhere(function (Builder $today) use ($moment): void {
                        $today->where('practice_sessions.session_date', $moment->toDateString())
                            ->where('practice_sessions.end_time', '<', $moment->format('H:i:s'));
                    });
            })
            ->whereHas('appointments', fn (Builder $appointments): Builder => $appointments->whereIn('appointments.status', $this->awaitingStatuses()))
            ->when($date !== null, fn (Builder $query): Builder => $query->where('practice_sessions.session_date', $date->toDateString()))
            ->orderBy('practice_sessions.session_date')
            ->orderBy('practice_sessions.start_time')
            ->orderBy('practice_sessions.id')
            ->get();
    }

    /** Jumlah appointment `pending`/`confirmed` pada sesi itu, tanpa syarat waktu (untuk banner antrean). */
    public function pendingNoShowCount(PracticeSession $session): int
    {
        return Appointment::query()
            ->where('appointments.practice_session_id', $session->getKey())
            ->whereIn('appointments.status', $this->awaitingStatuses())
            ->count();
    }

    /** @return list<string> */
    private function awaitingStatuses(): array
    {
        return [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value];
    }

    private function applyLocked(int $appointmentId, int $profileId, int $sessionId, AppointmentStatus $to, Actor $actor, ?string $reason, ?AppointmentStatus $expected): Appointment
    {
        // Urutan kunci baku (00 F.6): profil, sesi, appointment; hanya lewat PK dan tanpa kueri lain sebelumnya.
        $profile = PatientProfile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();
        $session = PracticeSession::query()->whereKey($sessionId)->lockForUpdate()->firstOrFail();
        $appointment = Appointment::query()->whereKey($appointmentId)->lockForUpdate()->firstOrFail();

        $from = $appointment->status;
        $now = $this->momentOf(null);

        $rejection = $this->evaluate($profile, $session, $from, $to, $actor, $now, true, $reason, $expected);

        if ($rejection !== null) {
            throw InvalidTransitionException::makeVariant($rejection['code'], $rejection['key'], $rejection['replace'], $this->context($appointment, $from, $to));
        }

        $appointment->forceFill($this->changes($appointment, $from, $to, $actor, $reason, $now))->save();

        if ($to === AppointmentStatus::Cancelled) {
            $session->forceFill(['booked_count' => max(0, (int) $session->booked_count - 1)])->save();
        }

        AppointmentStatusHistory::query()->create([
            'appointment_id' => $appointment->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actor->type,
            'actor_user_id' => $actor->userId,
            'reason' => $reason,
        ]);

        $this->audit->log(
            'appointment.status_changed',
            $appointment,
            ['status' => $from->value],
            ['status' => $to->value] + ($reason === null ? [] : ['reason' => $reason]),
            "Status pendaftaran {$appointment->booking_code} berubah dari {$from->label()} ke {$to->label()}.",
            $actor,
        );

        // refresh() lebih dulu (memuat kolom generated active_flag), baru relasi yang dibutuhkan notifier.
        $appointment->refresh();
        $appointment->setRelation('practiceSession', $session);
        $appointment->setRelation('patientProfile', $profile->load('user'));
        $appointment->load(['clinic', 'doctor']);

        $this->notifier->statusChanged($appointment, $from, $to, $reason);

        return $appointment;
    }

    /**
     * Evaluator tunggal aturan F.8. Urutan pemeriksaan (tidak boleh berubah; dikunci matriks 108 kasus):
     * pemilik, expected, final, pelaku-ke-status, pasangan, pelaku-di-aturan, alasan, lalu jendela waktu.
     * `$enforceInput` = true di `transition()` (di bawah kunci); false di `transitionChecks()`, yang melewati
     * `expected` dan alasan. Mengembalikan penolakan pertama (kode, kunci pesan `booking.transitions.*`,
     * placeholder) atau null bila diizinkan.
     *
     * @return array{code: string, key: string, replace: array<string, scalar>}|null
     */
    private function evaluate(PatientProfile $profile, PracticeSession $session, AppointmentStatus $from, AppointmentStatus $to, Actor $actor, CarbonImmutable $now, bool $enforceInput, ?string $reason = null, ?AppointmentStatus $expected = null): ?array
    {
        if ($actor->isPatient() && ($profile->user_id === null || (int) $profile->user_id !== $actor->userId)) {
            return $this->rejection('FORBIDDEN_TRANSITION');
        }

        if ($enforceInput && $expected !== null && $expected !== $from) {
            return $this->rejection('STALE_STATUS');
        }

        if ($from->isFinal()) {
            return $this->rejection('ALREADY_FINAL', ['status' => mb_strtolower($from->label())]);
        }

        if (! $this->actorMayTarget($actor, $to)) {
            return $this->rejection('FORBIDDEN_TRANSITION');
        }

        $rule = self::RULES[$from->value.'>'.$to->value] ?? null;

        if ($rule === null) {
            if ($actor->isPatient() && $from === AppointmentStatus::CheckedIn && $to === AppointmentStatus::Cancelled) {
                return $this->rejection('INVALID_TRANSITION', [], 'CANCEL_CHECKED_IN');
            }

            return $this->rejection('INVALID_TRANSITION', ['dari' => $from->label(), 'ke' => $to->label()]);
        }

        if (! in_array($actor->type->value, $rule['actors'], true)) {
            return $this->rejection('FORBIDDEN_TRANSITION');
        }

        if ($enforceInput && in_array($actor->type->value, $rule['reason'], true)) {
            $length = $reason === null ? 0 : mb_strlen($reason);

            if ($length < self::REASON_MIN_LENGTH || $length > self::REASON_MAX_LENGTH) {
                return $this->rejection('REASON_REQUIRED');
            }
        }

        return $this->evaluateWindow($rule['window'], $session, $actor, $now);
    }

    /** Jendela waktu pasangan (setelah semua pemeriksaan struktur lolos); null bila tidak ada jendela atau lolos. */
    private function evaluateWindow(?string $window, PracticeSession $session, Actor $actor, CarbonImmutable $now): ?array
    {
        if ($window === null) {
            return null;
        }

        $rules = $this->settings->bookingRules();
        $isSessionDay = $session->session_date->toDateString() === $now->toDateString();

        if ($window === 'before_end' && ! $now->lessThan($session->ends_at)) {
            return $this->rejection('CONFIRM_AFTER_END');
        }

        if ($window === 'checkin_window') {
            if (! $isSessionDay) {
                return $this->rejection('CHECKIN_NOT_TODAY', ['date' => $session->session_date->translatedFormat('l, j F Y')]);
            }

            $opensAt = $session->starts_at->subMinutes($rules->checkinOpenMinutes);

            if ($now->lessThan($opensAt)) {
                return $this->rejection('CHECKIN_NOT_OPEN', ['time' => $opensAt->format('H.i')]);
            }

            if ($now->greaterThan($session->ends_at)) {
                return $this->rejection('CHECKIN_CLOSED');
            }
        }

        if ($window === 'same_day' && ! $isSessionDay) {
            return $this->rejection('CORRECTION_NOT_SAME_DAY');
        }

        if ($window === 'cancel_rule' && $actor->isPatient()) {
            $deadline = $session->starts_at->subMinutes($rules->cancelCutoffMinutes);

            if ($now->greaterThan($deadline)) {
                // Nomor telepon rumah sakit boleh kosong di pengaturan: pesan tetap utuh.
                $phone = trim($this->settings->string('contact.phone'));

                return $this->rejection('CANCEL_CUTOFF_PASSED', [
                    'minutes' => $rules->cancelCutoffMinutes,
                    'phone' => $phone === '' ? 'nomor rumah sakit' : $phone,
                ]);
            }
        }

        if ($window === 'after_end' && ! $now->greaterThan($session->ends_at)) {
            return $this->rejection('SESSION_NOT_ENDED', ['time' => $session->ends_at->format('H.i')]);
        }

        return null;
    }

    /** Staf boleh menuju status apa pun (pasangan yang tidak sah ditolak sebagai INVALID_TRANSITION). */
    private function actorMayTarget(Actor $actor, AppointmentStatus $to): bool
    {
        if ($actor->isStaff()) {
            return true;
        }

        foreach (self::RULES as $pair => $rule) {
            if (str_ends_with($pair, '>'.$to->value) && in_array($actor->type->value, $rule['actors'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kolom yang berubah (I-14): status, kolom waktu, dan data pembatalan.
     *
     * @return array<string, mixed>
     */
    private function changes(Appointment $appointment, AppointmentStatus $from, AppointmentStatus $to, Actor $actor, ?string $reason, CarbonImmutable $now): array
    {
        $changes = ['status' => $to];

        switch ($to) {
            case AppointmentStatus::Confirmed:
                if ($from === AppointmentStatus::CheckedIn) {
                    $changes['checked_in_at'] = null;
                } else {
                    $changes['confirmed_at'] = $now;
                }
                break;
            case AppointmentStatus::CheckedIn:
                $changes['checked_in_at'] = $now;
                $changes['confirmed_at'] = $appointment->confirmed_at ?? $now;
                break;
            case AppointmentStatus::Completed:
                $changes['completed_at'] = $now;
                break;
            case AppointmentStatus::Cancelled:
                $changes['cancelled_at'] = $now;
                $changes['cancelled_by_type'] = $actor->type;
                $changes['cancelled_by_user_id'] = $actor->userId;
                $changes['cancel_reason'] = $reason;
                break;
            case AppointmentStatus::NoShow:
                $changes['no_show_at'] = $now;
                break;
            default:
                break;
        }

        return $changes;
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }

    /** Waktu "sekarang" dalam zona aplikasi (Asia/Jakarta); `$now` dari pemanggil menggantikan jam dinding. */
    private function momentOf(?CarbonImmutable $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->setTimezone((string) config('app.timezone'));
    }

    /**
     * @param  array<string, scalar>  $replace
     * @return array{code: string, key: string, replace: array<string, scalar>}
     */
    private function rejection(string $code, array $replace = [], ?string $key = null): array
    {
        return ['code' => $code, 'key' => $key ?? $code, 'replace' => $replace];
    }

    /**
     * @return array<string, string>
     */
    private function context(Appointment $appointment, AppointmentStatus $from, AppointmentStatus $to): array
    {
        return [
            'appointment_code' => (string) $appointment->booking_code,
            'from' => $from->value,
            'to' => $to->value,
        ];
    }
}
