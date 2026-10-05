<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DoctorScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Templat jadwal mingguan (lapis 1, 00 F.1). Sesi per tanggal dibangkitkan dari templat ini oleh
 * PracticeSessionGenerator; perubahan setelah ada booking melalui ScheduleChangeService (00 F.4).
 * Model ini tidak diobservasi AuditObserver: audit ditulis service.
 */
class DoctorSchedule extends Model
{
    /** @use HasFactory<DoctorScheduleFactory> */
    use HasFactory;

    /** Nama hari menurut ISO-8601 (1 = Senin ... 7 = Minggu). */
    public const DAY_NAMES = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * `created_by` dan `updated_by` sengaja tidak termasuk: diisi event `saving` dari Auth::id().
     *
     * @var list<string>
     */
    protected $fillable = [
        'doctor_id',
        'clinic_id',
        'day_of_week',
        'start_time',
        'end_time',
        'quota',
        'valid_from',
        'valid_until',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'quota' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $schedule): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            if (! $schedule->exists && $schedule->created_by === null) {
                $schedule->created_by = $userId;
            }

            $schedule->updated_by = $userId;
        });
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PracticeSession::class, 'doctor_schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('doctor_schedules.is_active', true);
    }

    /** Berlaku pada tanggal tertentu: `valid_from <= tanggal` dan (`valid_until` NULL atau `>= tanggal`). */
    public function scopeEffectiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return $query
            ->where('doctor_schedules.valid_from', '<=', $day)
            ->where(function (Builder $range) use ($day): void {
                $range->whereNull('doctor_schedules.valid_until')->orWhere('doctor_schedules.valid_until', '>=', $day);
            });
    }

    /** Hari ISO-8601 (1 = Senin ... 7 = Minggu). */
    public function scopeOnDay(Builder $query, int $isoDay): Builder
    {
        return $query->where('doctor_schedules.day_of_week', $isoDay);
    }

    /**
     * Templat AKTIF dokter yang sama pada hari yang sama dengan jam beririsan dan rentang berlaku beririsan
     * (V-23). Jam bersebelahan (selesai 12:00, mulai 12:00) tidak beririsan. Templat nonaktif tidak dihitung.
     * `$ignoreId` mengecualikan templat itu sendiri saat ubah.
     */
    public function scopeOverlapping(
        Builder $query,
        int $doctorId,
        int $dayOfWeek,
        string $start,
        string $end,
        CarbonInterface $validFrom,
        ?CarbonInterface $validUntil,
        ?int $ignoreId = null,
    ): Builder {
        return $query
            ->where('doctor_schedules.doctor_id', $doctorId)
            ->where('doctor_schedules.is_active', true)
            ->where('doctor_schedules.day_of_week', $dayOfWeek)
            ->where('doctor_schedules.start_time', '<', self::normalizeTime($end))
            ->where('doctor_schedules.end_time', '>', self::normalizeTime($start))
            ->where(function (Builder $range) use ($validFrom): void {
                $range->whereNull('doctor_schedules.valid_until')
                    ->orWhere('doctor_schedules.valid_until', '>=', $validFrom->toDateString());
            })
            ->when(
                $validUntil !== null,
                fn (Builder $inner): Builder => $inner->where('doctor_schedules.valid_from', '<=', $validUntil?->toDateString()),
            )
            ->when(
                $ignoreId !== null,
                fn (Builder $inner): Builder => $inner->where('doctor_schedules.id', '!=', $ignoreId),
            );
    }

    /** Nama hari: "Senin" ... "Minggu". */
    protected function dayName(): Attribute
    {
        return Attribute::get(fn (): string => self::DAY_NAMES[(int) $this->day_of_week] ?? '');
    }

    /** Rentang jam tampilan, mis. "08.00–12.00". */
    protected function timeRange(): Attribute
    {
        return Attribute::get(fn (): string => self::displayTime((string) $this->start_time).'–'.self::displayTime((string) $this->end_time));
    }

    /**
     * Ada sesi milik templat ini pada waktu mendatang yang punya appointment aktif (F.4: dasar penolakan
     * ganti poliklinik dan arsip dokter). Memakai Appointment::upcomingActive().
     */
    public function hasUpcomingActiveAppointments(?CarbonInterface $now = null): bool
    {
        return $this->sessions()
            ->whereHas('appointments', fn (Builder $appointments): Builder => $appointments->upcomingActive($now))
            ->exists();
    }

    /**
     * Alasan hapus ditolak (BR-12), atau null bila boleh dihapus: ditolak bila ada sesi milik templat ini
     * yang pernah memiliki appointment (termasuk yang hanya riwayat batal).
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->sessions()->whereHas('appointments')->exists()) {
            return 'Jadwal ini sudah memiliki sesi dengan pendaftaran dan hanya dapat dinonaktifkan.';
        }

        return null;
    }

    /** "08:00" atau "08:00:00" menjadi "08:00:00" agar dibandingkan sebagai TIME. */
    private static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    /** "08:00:00" menjadi "08.00". */
    private static function displayTime(string $time): string
    {
        return str_replace(':', '.', substr($time, 0, 5));
    }
}
