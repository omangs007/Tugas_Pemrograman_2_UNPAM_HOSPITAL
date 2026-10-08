<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur tulis status appointment setelah pembuatan (00 F.8, F6-R15). Tabel transisi T4-T10
 * ada sebagai data (TRANSITIONS dan RULES): pembuatan (T1-T3) milik BookingService. Kunci baku 00 F.6:
 * patient_profiles, practice_sessions, appointments (hanya lewat PK) sebelum kueri lain; semua pemeriksaan
 * dibaca ulang di bawah kunci. Otorisasi (izin Spatie, Policy) ada di lapisan HTTP; service menegakkan
 * aturan domain menurut JENIS pelaku dan kepemilikan.
 */
final class AppointmentStatusService
{
    public const REASON_MIN_LENGTH = 5;

    public const REASON_MAX_LENGTH = 255;

    /** Peta dari-ke yang sah (00 F.8). Status final tidak punya jalan keluar. */
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
     * @throws BookingException SYSTEM_BUSY bila galat konkurensi lolos dari 3 percobaan
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
        } catch (QueryException $e) {
            if (app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($e)) {
                throw BookingException::make(BookingException::SYSTEM_BUSY, [], [], $e);
            }

            throw $e;
        }

        $appointment->setRawAttributes($locked->getRawOriginal(), true);
        $appointment->setRelations($locked->getRelations());

        return $appointment;
    }

    private function applyLocked(int $appointmentId, int $profileId, int $sessionId, AppointmentStatus $to, Actor $actor, ?string $reason, ?AppointmentStatus $expected): Appointment
    {
        // Urutan kunci baku (00 F.6): profil, sesi, appointment; hanya lewat PK dan tanpa kueri lain sebelumnya.
        $profile = PatientProfile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();
        $session = PracticeSession::query()->whereKey($sessionId)->lockForUpdate()->firstOrFail();
        $appointment = Appointment::query()->whereKey($appointmentId)->lockForUpdate()->firstOrFail();

        $from = $appointment->status;
        $now = CarbonImmutable::instance(now())->setTimezone((string) config('app.timezone'));

        $rule = $this->authorize($appointment, $profile, $from, $to, $actor, $reason, $expected);
        $this->assertWindow($rule['window'], $appointment, $from, $to, $actor, $session, $now);

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
     * Urutan pemeriksaan (semuanya di bawah kunci): pemilik, expected, final, pelaku-ke-status, pasangan,
     * alasan. Mengembalikan aturan pasangan itu untuk pemeriksaan jendela.
     *
     * @return array{step: string, actors: list<string>, window: ?string, reason: list<string>}
     */
    private function authorize(Appointment $appointment, PatientProfile $profile, AppointmentStatus $from, AppointmentStatus $to, Actor $actor, ?string $reason, ?AppointmentStatus $expected): array
    {
        if ($actor->isPatient() && ($profile->user_id === null || (int) $profile->user_id !== $actor->userId)) {
            $this->reject('FORBIDDEN_TRANSITION', $appointment, $from, $to);
        }

        if ($expected !== null && $expected !== $from) {
            $this->reject('STALE_STATUS', $appointment, $from, $to);
        }

        if ($from->isFinal()) {
            $this->reject('ALREADY_FINAL', $appointment, $from, $to, ['status' => mb_strtolower($from->label())]);
        }

        if (! $this->actorMayTarget($actor, $to)) {
            $this->reject('FORBIDDEN_TRANSITION', $appointment, $from, $to);
        }

        $rule = self::RULES[$from->value.'>'.$to->value] ?? null;

        if ($rule === null) {
            if ($actor->isPatient() && $from === AppointmentStatus::CheckedIn && $to === AppointmentStatus::Cancelled) {
                throw new InvalidTransitionException('INVALID_TRANSITION', __('booking.transitions.CANCEL_CHECKED_IN'), $this->context($appointment, $from, $to));
            }

            $this->reject('INVALID_TRANSITION', $appointment, $from, $to, ['dari' => $from->label(), 'ke' => $to->label()]);
        }

        if (! in_array($actor->type->value, $rule['actors'], true)) {
            $this->reject('FORBIDDEN_TRANSITION', $appointment, $from, $to);
        }

        if (in_array($actor->type->value, $rule['reason'], true)) {
            $length = $reason === null ? 0 : mb_strlen($reason);

            if ($length < self::REASON_MIN_LENGTH || $length > self::REASON_MAX_LENGTH) {
                $this->reject('REASON_REQUIRED', $appointment, $from, $to);
            }
        }

        return $rule;
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

    private function assertWindow(?string $window, Appointment $appointment, AppointmentStatus $from, AppointmentStatus $to, Actor $actor, PracticeSession $session, CarbonImmutable $now): void
    {
        if ($window === null) {
            return;
        }

        $rules = $this->settings->bookingRules();
        $isSessionDay = $session->session_date->toDateString() === $now->toDateString();

        if ($window === 'before_end' && ! $now->lessThan($session->ends_at)) {
            $this->reject('CONFIRM_AFTER_END', $appointment, $from, $to);
        }

        if ($window === 'checkin_window') {
            if (! $isSessionDay) {
                $this->reject('CHECKIN_NOT_TODAY', $appointment, $from, $to, ['date' => $session->session_date->translatedFormat('l, j F Y')]);
            }

            $opensAt = $session->starts_at->subMinutes($rules->checkinOpenMinutes);

            if ($now->lessThan($opensAt)) {
                $this->reject('CHECKIN_NOT_OPEN', $appointment, $from, $to, ['time' => $opensAt->format('H.i')]);
            }

            if ($now->greaterThan($session->ends_at)) {
                $this->reject('CHECKIN_CLOSED', $appointment, $from, $to);
            }
        }

        if ($window === 'same_day' && ! $isSessionDay) {
            $this->reject('CORRECTION_NOT_SAME_DAY', $appointment, $from, $to);
        }

        if ($window === 'cancel_rule' && $actor->isPatient()) {
            $deadline = $session->starts_at->subMinutes($rules->cancelCutoffMinutes);

            if ($now->greaterThan($deadline)) {
                $this->reject('CANCEL_CUTOFF_PASSED', $appointment, $from, $to, [
                    'minutes' => $rules->cancelCutoffMinutes,
                    'phone' => $this->settings->string('contact.phone'),
                ]);
            }
        }

        if ($window === 'after_end' && ! $now->greaterThan($session->ends_at)) {
            $this->reject('SESSION_NOT_ENDED', $appointment, $from, $to, ['time' => $session->ends_at->format('H.i')]);
        }
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

    /**
     * @param  array<string, scalar>  $replace
     */
    private function reject(string $code, Appointment $appointment, AppointmentStatus $from, AppointmentStatus $to, array $replace = []): never
    {
        throw InvalidTransitionException::make($code, $replace, $this->context($appointment, $from, $to));
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
