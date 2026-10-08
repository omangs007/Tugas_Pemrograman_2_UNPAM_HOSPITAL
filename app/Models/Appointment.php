<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Services\Booking\QueueNumberFormatter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pendaftaran kunjungan (09 §4.1). Fase 3 hanya menyediakan skema, model, factory, dan pembantu tes;
 * `BookingService` dan `AppointmentStatusService` (fase 6) adalah satu-satunya jalur tulis. Relasi
 * `insurancePartner()` ditambahkan fase 6 (F6-R33) untuk tampilan detail dan bukti.
 * `booking_code`, `queue_number`, `status`, kolom waktu status, dan `active_flag` (generated) bukan fillable.
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /** Penutup tampilan referensi pembayaran: sembilan titik + empat karakter terakhir (04 V2; sama dengan PatientProfile::BPJS_MASK yang privat). */
    private const PAYMENT_MASK = '•••••••••';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_profile_id',
        'practice_session_id',
        'doctor_id',
        'clinic_id',
        'session_date',
        'channel',
        'payment_type',
        'insurance_partner_id',
        'payment_reference',
        'complaint',
        'created_by_user_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'payment_reference',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'queue_number' => 'integer',
            'status' => AppointmentStatus::class,
            'channel' => BookingChannel::class,
            'payment_type' => PaymentType::class,
            'payment_reference' => 'encrypted',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',
            'cancelled_by_type' => ActorType::class,
            'schedule_changed_at' => 'datetime',
            'active_flag' => 'integer',
        ];
    }

    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
    }

    public function practiceSession(): BelongsTo
    {
        return $this->belongsTo(PracticeSession::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /** Mitra asuransi pembayaran `asuransi`; NULL untuk pembayaran lain. */
    public function insurancePartner(): BelongsTo
    {
        return $this->belongsTo(InsurancePartner::class);
    }

    /** Riwayat status, urut kronologis. */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /** Petugas pembuat (kanal loket); NULL untuk online. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** Status aktif: pending, confirmed, checked_in (AppointmentStatus::ACTIVE; selaras dengan `active_flag`). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('appointments.status', self::values(AppointmentStatus::ACTIVE));
    }

    /** Status yang memakai kuota sesi: semua kecuali cancelled (AppointmentStatus::QUOTA_CONSUMING). */
    public function scopeQuotaConsuming(Builder $query): Builder
    {
        return $query->whereIn('appointments.status', self::values(AppointmentStatus::QUOTA_CONSUMING));
    }

    /**
     * Appointment aktif pada sesi yang belum berakhir pada `$now` (F3-R15): `session_date > hari ini`, atau
     * hari ini dengan `end_time > jam sekarang`. Satu-satunya implementasi definisi "aktif mendatang".
     */
    public function scopeUpcomingActive(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query
            ->active()
            ->whereHas('practiceSession', fn (Builder $session): Builder => $session->notEnded($now));
    }

    /** Pendaftaran milik satu profil pasien. */
    public function scopeForPatient(Builder $query, PatientProfile $profile): Builder
    {
        return $query->where('appointments.patient_profile_id', $profile->getKey());
    }

    /** Pendaftaran yang jadwalnya diubah rumah sakit (`schedule_changed_at` terisi). */
    public function scopeScheduleChanged(Builder $query): Builder
    {
        return $query->whereNotNull('appointments.schedule_changed_at');
    }

    /**
     * Label antrean tampilan, mis. "ANK-007" (F6-R6). Relasi `clinic` wajib sudah dimuat pada daftar
     * (mode ketat melarang lazy loading koleksi).
     *
     * @return Attribute<string, never>
     */
    protected function queueLabel(): Attribute
    {
        return Attribute::get(fn (): string => QueueNumberFormatter::format((string) $this->clinic->code, (int) $this->queue_number));
    }

    /**
     * Referensi pembayaran tersamar "•••••••••1234" dari `payment_reference_last4` (F6-R22), atau NULL bila
     * tidak ada. Nomor lengkap tidak pernah didekripsi untuk tampilan.
     *
     * @return Attribute<string|null, never>
     */
    protected function maskedPaymentReference(): Attribute
    {
        return Attribute::get(function (): ?string {
            $last4 = $this->payment_reference_last4;

            return $last4 === null || $last4 === '' ? null : self::PAYMENT_MASK.$last4;
        });
    }

    /** Waktu mulai sesi (`session_date` + `start_time`). Relasi `practiceSession` wajib sudah dimuat. */
    public function startsAt(): CarbonImmutable
    {
        return $this->practiceSession->starts_at;
    }

    /** Waktu selesai sesi (`session_date` + `end_time`). Relasi `practiceSession` wajib sudah dimuat. */
    public function endsAt(): CarbonImmutable
    {
        return $this->practiceSession->ends_at;
    }

    /**
     * Predikat tampilan: pasien masih boleh membatalkan (F6-R15, BR-73). Hanya `pending`/`confirmed` dan
     * `now <= start - cutoffMinutes` (inklusif). Penegakan dan pesan ada di AppointmentStatusService.
     */
    public function isCancellableByPatient(CarbonInterface $now, int $cutoffMinutes): bool
    {
        if (! in_array($this->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
            return false;
        }

        return CarbonImmutable::instance($now)->lessThanOrEqualTo($this->startsAt()->subMinutes($cutoffMinutes));
    }

    /**
     * @param  list<AppointmentStatus>  $statuses
     * @return list<string>
     */
    private static function values(array $statuses): array
    {
        return array_map(fn (AppointmentStatus $status): string => $status->value, $statuses);
    }
}
