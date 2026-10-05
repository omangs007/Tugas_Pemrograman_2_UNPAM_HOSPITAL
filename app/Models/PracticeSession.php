<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PracticeSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sesi praktik konkret per tanggal (lapis 3, 00 F.1): hasil pembangkitan dari templat atau sesi tambahan
 * manual (`doctor_schedule_id` NULL). Baris ini dikunci (lockForUpdate) saat booking. `booked_count` dan
 * `last_queue_number` bukan fillable: hanya service di bawah kunci yang mengubahnya (forceFill).
 */
class PracticeSession extends Model
{
    /** @use HasFactory<PracticeSessionFactory> */
    use HasFactory;

    /** Sumber `pending_change` (F3-R14, 00 F.4). Nilai disimpan di JSON `pending_change.source`. */
    public const SOURCE_EXCEPTION = 'exception';

    public const SOURCE_TEMPLATE_TIME = 'template_time';

    public const SOURCE_TEMPLATE_REMOVED = 'template_removed';

    public const SOURCE_MANUAL_TIME = 'manual_time';

    public const SOURCE_MANUAL_CANCEL = 'manual_cancel';

    public const SOURCE_DOCTOR_INACTIVE = 'doctor_inactive';

    /** Sumber jenis-batal: mengalahkan jenis-jam pada prioritas `pending_change`. */
    public const CANCEL_SOURCES = [
        self::SOURCE_EXCEPTION,
        self::SOURCE_TEMPLATE_REMOVED,
        self::SOURCE_MANUAL_CANCEL,
        self::SOURCE_DOCTOR_INACTIVE,
    ];

    /** Sumber jenis-jam: tidak pernah menimpa jenis-batal. */
    public const TIME_SOURCES = [
        self::SOURCE_TEMPLATE_TIME,
        self::SOURCE_MANUAL_TIME,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'doctor_id',
        'clinic_id',
        'doctor_schedule_id',
        'session_date',
        'start_time',
        'end_time',
        'quota',
        'status',
        'status_reason',
        'schedule_exception_id',
        'pending_change',
        'is_manually_modified',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'quota' => 'integer',
            'booked_count' => 'integer',
            'last_queue_number' => 'integer',
            'status' => SessionStatus::class,
            'pending_change' => 'array',
            'is_manually_modified' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /** Templat asal; NULL untuk sesi tambahan manual. */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id');
    }

    /** Pengecualian penyebab `cancelled`/`needs_review`. */
    public function exception(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'schedule_exception_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Tanggal sesi di antara dua tanggal (kedua ujung termasuk); menerima Carbon atau "Y-m-d". */
    public function scopeBetween(Builder $query, CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        return $query->whereBetween('practice_sessions.session_date', [
            Carbon::parse($from)->toDateString(),
            Carbon::parse($to)->toDateString(),
        ]);
    }

    /** Tanggal sesi persis; menerima Carbon atau "Y-m-d". */
    public function scopeOnDate(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->where('practice_sessions.session_date', Carbon::parse($date)->toDateString());
    }

    /** Tanggal sesi hari ini atau sesudahnya (tidak memeriksa jam). */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('practice_sessions.session_date', '>=', today()->toDateString());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('practice_sessions.status', SessionStatus::Open->value);
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('practice_sessions.status', SessionStatus::NeedsReview->value);
    }

    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('practice_sessions.doctor_id', $doctorId);
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('practice_sessions.clinic_id', $clinicId);
    }

    /**
     * Belum berakhir pada `$now` (bawaan sekarang): tanggal sesudah hari ini, atau hari ini dengan
     * `end_time > jam sekarang`. Sesi yang berakhir tepat pada `$now` dianggap sudah berakhir.
     */
    public function scopeNotEnded(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $moment = self::momentOf($now);

        return $query->where(function (Builder $ended) use ($moment): void {
            $ended->where('practice_sessions.session_date', '>', $moment->toDateString())
                ->orWhere(function (Builder $today) use ($moment): void {
                    $today->where('practice_sessions.session_date', $moment->toDateString())
                        ->where('practice_sessions.end_time', '>', $moment->format('H:i:s'));
                });
        });
    }

    /** `quota - booked_count`, tidak pernah negatif. */
    protected function remaining(): Attribute
    {
        return Attribute::get(fn (): int => max(0, (int) $this->quota - (int) $this->booked_count));
    }

    /**
     * Waktu mulai (`CarbonImmutable`, zona aplikasi). Tanpa cache objek: mengikuti perubahan `start_time`.
     *
     * @return Attribute<CarbonImmutable, never>
     */
    protected function startsAt(): Attribute
    {
        return Attribute::get(fn (): CarbonImmutable => $this->combine((string) $this->start_time))->withoutObjectCaching();
    }

    /**
     * Waktu selesai (`CarbonImmutable`, zona aplikasi). Tanpa cache objek.
     *
     * @return Attribute<CarbonImmutable, never>
     */
    protected function endsAt(): Attribute
    {
        return Attribute::get(fn (): CarbonImmutable => $this->combine((string) $this->end_time))->withoutObjectCaching();
    }

    /** Rentang jam tampilan, mis. "08.00–12.00". */
    protected function timeRange(): Attribute
    {
        return Attribute::get(fn (): string => str_replace(':', '.', substr((string) $this->start_time, 0, 5))
            .'–'
            .str_replace(':', '.', substr((string) $this->end_time, 0, 5)));
    }

    /** Sesi sudah berakhir: `ends_at <= $now` (bawaan sekarang). */
    public function isEnded(?CarbonInterface $now = null): bool
    {
        return $this->ends_at->lessThanOrEqualTo(self::momentOf($now));
    }

    /** Sesi tambahan manual (bukan hasil templat). */
    public function isManual(): bool
    {
        return $this->doctor_schedule_id === null;
    }

    public function hasActiveAppointments(): bool
    {
        return $this->appointments()->active()->exists();
    }

    /** Nilai `pending_change.source`, atau NULL bila tidak ada `pending_change`. */
    public function pendingSource(): ?string
    {
        $source = is_array($this->pending_change) ? ($this->pending_change['source'] ?? null) : null;

        return is_string($source) ? $source : null;
    }

    /**
     * Alasan hapus ditolak (00 C, F3-R20), atau null bila boleh dihapus: hanya sesi tambahan manual yang
     * tidak pernah memiliki appointment.
     */
    public function deletionBlockedReason(): ?string
    {
        if (! $this->isManual()) {
            return 'Sesi dari templat tidak dapat dihapus. Tutup atau batalkan sesi ini.';
        }

        if ($this->appointments()->exists()) {
            return 'Sesi ini sudah memiliki pendaftaran. Tutup atau batalkan sesi ini.';
        }

        return null;
    }

    private function combine(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($this->session_date->toDateString().' '.$time, (string) config('app.timezone'));
    }

    private static function momentOf(?CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->setTimezone((string) config('app.timezone'));
    }
}
