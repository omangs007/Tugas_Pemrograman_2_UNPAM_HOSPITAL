<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\BookingChannel;
use App\Enums\DoctorStatus;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Snapshot pengaturan `booking.*` (11 §6.1), dibaca sekali per operasi, beserta aturan F.5 #1-#8
 * (`assertBookable()`, F6-R11). Aturan hanya MEMBACA: pemanggil (`BookingService::book()`) yang mengunci profil
 * dan sesi dan mengoper baris terkunci; `precheck()` memanggilnya tanpa kunci.
 */
final readonly class BookingRules
{
    public function __construct(
        public int $windowDays,
        public int $onlineCutoffMinutes,
        public int $cancelCutoffMinutes,
        public int $checkinOpenMinutes,
        public int $maxActivePerPatient,
        public bool $autoConfirm,
    ) {}

    /**
     * Nilai cadangan sama dengan default seeder (00 E.7) sehingga tabel pengaturan yang kosong tidak
     * menghasilkan jendela 0 hari.
     */
    public static function fromSettings(SettingsService $settings): self
    {
        return new self(
            windowDays: $settings->int('booking.window_days', 30),
            onlineCutoffMinutes: $settings->int('booking.online_cutoff_minutes', 60),
            cancelCutoffMinutes: $settings->int('booking.cancel_cutoff_minutes', 120),
            checkinOpenMinutes: $settings->int('booking.checkin_open_minutes', 60),
            maxActivePerPatient: $settings->int('booking.max_active_per_patient', 3),
            autoConfirm: $settings->bool('booking.auto_confirm', false),
        );
    }

    /**
     * Aturan F.5 #1-#8 menurut urutan 00 F.5; yang pertama gagal dilempar sebagai BookingException (kode,
     * pesan Indonesia, konteks `practice_session_id` serta `appointment_code` untuk DUPLICATE/OVERLAPPING).
     * Kanal dari `$actor`: staf = loket (#3 memakai akhir sesi, #7 dilewati), selain itu online. Profil
     * dianggap sudah valid dan aktif (diperiksa middleware, Policy, dan `book()`). `booked_count` dibaca dari
     * instans `$session` yang dioper (pemanggil menjamin kesegarannya).
     *
     * @throws BookingException
     */
    public function assertBookable(PatientProfile $profile, PracticeSession $session, BookingData $data, Actor $actor, CarbonImmutable $now): void
    {
        $counter = $actor->channel() === BookingChannel::Loket;
        $session->loadMissing(['doctor', 'clinic']);

        $this->assertSessionAvailable($session);
        $this->assertInsideWindow($session, $now);
        $this->assertBeforeCutoff($session, $now, $counter);
        $this->assertHasQuota($session);
        $this->assertNoDuplicate($profile, $session, $counter);
        $this->assertNoOverlap($profile, $session, $counter);

        if (! $counter) {
            $this->assertBelowActiveLimit($profile, $session, $now);
        }

        $this->assertValidPayment($session, $data);
    }

    /** #1: sesi `open`, dokter `active`, poliklinik `is_active` dan `is_bookable`. */
    private function assertSessionAvailable(PracticeSession $session): void
    {
        $available = $session->status === SessionStatus::Open
            && $session->doctor->status === DoctorStatus::Active
            && $session->clinic->is_active
            && $session->clinic->is_bookable;

        if (! $available) {
            throw BookingException::make(BookingException::SESSION_UNAVAILABLE, [], $this->context($session));
        }
    }

    /** #2: hari ini <= `session_date` <= hari ini + `windowDays` (hari ke-N masih diterima). */
    private function assertInsideWindow(PracticeSession $session, CarbonImmutable $now): void
    {
        $today = $now->setTimezone((string) config('app.timezone'))->startOfDay();
        $date = $session->session_date->toDateString();

        if ($date < $today->toDateString() || $date > $today->addDays($this->windowDays)->toDateString()) {
            throw BookingException::make(BookingException::OUTSIDE_WINDOW, ['days' => $this->windowDays], $this->context($session));
        }
    }

    /** #3: online `now >= start - onlineCutoffMinutes`; loket `now >= end`. */
    private function assertBeforeCutoff(PracticeSession $session, CarbonImmutable $now, bool $counter): void
    {
        if ($counter) {
            if ($now->greaterThanOrEqualTo($session->ends_at)) {
                throw BookingException::makeVariant(BookingException::CUTOFF_PASSED, 'CUTOFF_PASSED_COUNTER', [], $this->context($session));
            }

            return;
        }

        if ($now->greaterThanOrEqualTo($session->starts_at->subMinutes($this->onlineCutoffMinutes))) {
            throw BookingException::make(BookingException::CUTOFF_PASSED, ['minutes' => $this->onlineCutoffMinutes], $this->context($session));
        }
    }

    /** #4: `booked_count < quota`. */
    private function assertHasQuota(PracticeSession $session): void
    {
        if ((int) $session->booked_count >= (int) $session->quota) {
            throw BookingException::make(BookingException::QUOTA_FULL, [], $this->context($session));
        }
    }

    /** #5: tidak ada pendaftaran aktif pasien pada sesi yang sama. */
    private function assertNoDuplicate(PatientProfile $profile, PracticeSession $session, bool $counter): void
    {
        $code = Appointment::query()
            ->forPatient($profile)
            ->active()
            ->where('appointments.practice_session_id', $session->getKey())
            ->value('appointments.booking_code');

        if ($code !== null) {
            throw BookingException::make(
                BookingException::DUPLICATE_BOOKING,
                ['subjek' => $this->subject($counter)],
                $this->context($session, ['appointment_code' => (string) $code]),
            );
        }
    }

    /** #6: tidak ada pendaftaran aktif pasien pada tanggal sama di sesi lain berjam beririsan (`start1 < end2 AND start2 < end1`). */
    private function assertNoOverlap(PatientProfile $profile, PracticeSession $session, bool $counter): void
    {
        $start = $session->starts_at->format('H:i:s');
        $end = $session->ends_at->format('H:i:s');

        $other = Appointment::query()
            ->forPatient($profile)
            ->active()
            ->where('appointments.session_date', $session->session_date->toDateString())
            ->where('appointments.practice_session_id', '!=', $session->getKey())
            ->whereHas('practiceSession', fn (Builder $existing): Builder => $existing
                ->where('practice_sessions.start_time', '<', $end)
                ->where('practice_sessions.end_time', '>', $start))
            ->with(['practiceSession', 'doctor'])
            ->orderBy('appointments.id')
            ->first();

        if ($other !== null) {
            throw BookingException::make(
                BookingException::OVERLAPPING_BOOKING,
                [
                    'subjek' => $this->subject($counter),
                    'doctor' => $other->doctor->display_name,
                    'start' => $other->practiceSession->starts_at->format('H.i'),
                    'end' => $other->practiceSession->ends_at->format('H.i'),
                ],
                $this->context($session, ['appointment_code' => $other->booking_code]),
            );
        }
    }

    /** #7 (online saja): jumlah pendaftaran aktif mendatang pasien < `maxActivePerPatient`. */
    private function assertBelowActiveLimit(PatientProfile $profile, PracticeSession $session, CarbonImmutable $now): void
    {
        $count = Appointment::query()->forPatient($profile)->upcomingActive($now)->count();

        if ($count >= $this->maxActivePerPatient) {
            throw BookingException::make(
                BookingException::ACTIVE_LIMIT_REACHED,
                ['subjek' => $this->subject(false), 'max' => $this->maxActivePerPatient],
                $this->context($session),
            );
        }
    }

    /** #8: bpjs = 13 digit tanpa mitra; asuransi = mitra aktif (+ polis opsional); umum = tanpa referensi dan mitra. */
    private function assertValidPayment(PracticeSession $session, BookingData $data): void
    {
        $reference = $data->paymentReference;
        $partnerId = $data->insurancePartnerId;

        $valid = match ($data->paymentType) {
            PaymentType::Bpjs => $partnerId === null
                && $reference !== null
                && preg_match('/\A[0-9]{13}\z/', $reference) === 1,
            PaymentType::Asuransi => $partnerId !== null
                && ($reference === null || preg_match('/\A[A-Za-z0-9\/-]{1,30}\z/', $reference) === 1)
                && InsurancePartner::query()->whereKey($partnerId)->where('is_active', true)->exists(),
            PaymentType::Umum => $reference === null && $partnerId === null,
        };

        if (! $valid) {
            throw BookingException::make(BookingException::INVALID_PAYMENT, [], $this->context($session));
        }
    }

    /** "Anda" (online) atau "pasien ini" (loket) untuk `:subjek`/`:Subjek`. */
    private function subject(bool $counter): string
    {
        return $counter ? 'pasien ini' : 'Anda';
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(PracticeSession $session, array $extra = []): array
    {
        return ['practice_session_id' => $session->getKey()] + $extra;
    }
}
