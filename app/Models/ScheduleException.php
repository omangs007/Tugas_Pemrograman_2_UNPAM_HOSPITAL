<?php

namespace App\Models;

use App\Enums\ExceptionType;
use Carbon\CarbonInterface;
use Database\Factories\ScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Pengecualian jadwal (lapis 2, 00 F.1): cuti dokter, libur rumah sakit, libur nasional, atau lainnya,
 * selalu satu hari penuh. `doctor_id` NULL berarti berlaku untuk seluruh dokter. Tidak ada penghapusan
 * langsung: "hapus" = ScheduleChangeService::removeException() lalu delete().
 */
class ScheduleException extends Model
{
    /** @use HasFactory<ScheduleExceptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'doctor_id',
        'type',
        'start_date',
        'end_date',
        'reason',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExceptionType::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $exception): void {
            $userId = Auth::id();

            if ($userId !== null && $exception->created_by === null) {
                $exception->created_by = $userId;
            }
        });
    }

    /** Dokter yang bercuti; NULL untuk pengecualian yang berlaku bagi seluruh dokter. */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PracticeSession::class, 'schedule_exception_id');
    }

    /** Mencakup tanggal tertentu (kedua ujung termasuk). */
    public function scopeCovering(Builder $query, CarbonInterface $date): Builder
    {
        $day = $date->toDateString();

        return $query
            ->where('schedule_exceptions.start_date', '<=', $day)
            ->where('schedule_exceptions.end_date', '>=', $day);
    }

    /** Berlaku bagi dokter itu: `doctor_id` NULL (semua dokter) atau sama dengan dokter. */
    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where(function (Builder $scope) use ($doctorId): void {
            $scope->whereNull('schedule_exceptions.doctor_id')->orWhere('schedule_exceptions.doctor_id', $doctorId);
        });
    }

    /** Belum berakhir: `end_date >= hari ini`. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('schedule_exceptions.end_date', '>=', today()->toDateString());
    }

    /**
     * Memilih satu pengecualian untuk dokter pada tanggal tertentu dari kumpulan yang sudah dimuat (BR-21,
     * F3-R10): hanya yang mencakup tanggal dan berlaku bagi dokter; yang khusus dokter didahulukan, lalu
     * `id` terkecil. NULL bila tidak ada.
     *
     * @param  Collection<int, self>  $exceptions
     */
    public static function pickFor(Collection $exceptions, int $doctorId, CarbonInterface $date): ?self
    {
        $day = $date->toDateString();

        return $exceptions
            ->filter(fn (self $exception): bool => $exception->start_date->toDateString() <= $day
                && $exception->end_date->toDateString() >= $day
                && ($exception->doctor_id === null || (int) $exception->doctor_id === $doctorId))
            ->sort(function (self $a, self $b): int {
                $rankA = $a->doctor_id === null ? 1 : 0;
                $rankB = $b->doctor_id === null ? 1 : 0;

                return [$rankA, (int) $a->id] <=> [$rankB, (int) $b->id];
            })
            ->first();
    }

    /** Berlaku untuk seluruh dokter. */
    protected function isGlobal(): Attribute
    {
        return Attribute::get(fn (): bool => $this->doctor_id === null);
    }

    /** "12 Oktober 2026" untuk satu hari, atau "12 Oktober 2026 – 14 Oktober 2026" untuk rentang. */
    protected function rangeLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $first = $this->start_date->translatedFormat('j F Y');

            return $this->start_date->isSameDay($this->end_date)
                ? $first
                : $first.' – '.$this->end_date->translatedFormat('j F Y');
        });
    }
}
