<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pendaftaran kunjungan (09 §4.1). Fase 3 hanya menyediakan skema, model, factory, dan pembantu tes;
 * `BookingService` dan `AppointmentStatusService` (fase 6) adalah satu-satunya jalur tulis. Relasi
 * `insurancePartner()` ditambahkan fase 4/6, ketika model InsurancePartner ada (F3-R3).
 * `booking_code`, `queue_number`, `status`, kolom waktu status, dan `active_flag` (generated) bukan fillable.
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

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

    /**
     * @param  list<AppointmentStatus>  $statuses
     * @return list<string>
     */
    private static function values(array $statuses): array
    {
        return array_map(fn (AppointmentStatus $status): string => $status->value, $statuses);
    }
}
