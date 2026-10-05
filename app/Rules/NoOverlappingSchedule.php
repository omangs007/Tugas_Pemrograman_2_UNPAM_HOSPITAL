<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\DoctorSchedule;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BR-08 / V-23: templat aktif seorang dokter pada hari yang sama tidak boleh beririsan jam
 * (`start1 < end2 AND start2 < end1`) dalam rentang berlaku yang beririsan (`valid_until` NULL = tanpa batas).
 * BR-11: pembandingan per dokter, bukan per poliklinik. Dipasang pada `start_time`.
 *
 * Aturan ini hanya bekerja bila isian lain valid; isian yang salah dilaporkan aturannya sendiri. Templat
 * nonaktif tidak dihitung bentrok, dan templat yang sedang diubah dikecualikan lewat `$ignoreId`.
 */
final class NoOverlappingSchedule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly ?int $doctorId,
        private readonly ?int $ignoreId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->doctorId === null || ! $this->willBeActive()) {
            return;
        }

        $start = $this->time($value);
        $end = $this->time($this->data['end_time'] ?? null);
        $day = filter_var($this->data['day_of_week'] ?? null, FILTER_VALIDATE_INT);
        $from = $this->date($this->data['valid_from'] ?? null);

        if ($start === null || $end === null || $start >= $end || $day === false || $day < 1 || $day > 7 || $from === null) {
            return;
        }

        $untilInput = $this->data['valid_until'] ?? null;
        $until = null;

        if ($untilInput !== null && $untilInput !== '') {
            $until = $this->date($untilInput);

            if ($until === null || $until < $from) {
                return;
            }
        }

        $conflict = DoctorSchedule::query()
            ->active()
            ->overlapping($this->doctorId, $day, $start, $end, $from, $until, $this->ignoreId)
            ->orderBy('id')
            ->first();

        if ($conflict === null) {
            return;
        }

        $range = $conflict->valid_from->translatedFormat('j M Y').' – '
            .($conflict->valid_until === null ? 'tanpa batas' : $conflict->valid_until->translatedFormat('j M Y'));

        $fail(sprintf(
            'Jadwal bertabrakan dengan jadwal aktif lain milik dokter ini pada hari yang sama (%s %s, berlaku %s).',
            $conflict->day_name,
            $conflict->time_range,
            $range,
        ));
    }

    /** Templat yang dinonaktifkan tidak memakai slot, sehingga tidak diperiksa (BR-08). */
    private function willBeActive(): bool
    {
        $active = filter_var($this->data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $active === true;
    }

    private function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        $date = CarbonImmutable::canBeCreatedFromFormat($value, '!Y-m-d') ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
