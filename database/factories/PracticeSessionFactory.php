<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeSession>
 */
class PracticeSessionFactory extends Factory
{
    /**
     * Sesi hasil templat besok pukul 08.00-12.00, kuota 20, status open, belum ada booking. Templat dibuat
     * serasi (dokter, poliklinik, hari ISO, jam, kuota sama); gunakan manual() untuk sesi tambahan tanpa
     * templat. `booked_count` dan `last_queue_number` tidak pernah melanggar CHECK (lihat configure()).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'clinic_id' => fn (array $attributes): int => Doctor::query()->findOrFail($attributes['doctor_id'])->clinic_id,
            'session_date' => today()->addDay()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'doctor_schedule_id' => fn (array $attributes): int => DoctorSchedule::factory()->create([
                'doctor_id' => $attributes['doctor_id'],
                'clinic_id' => $attributes['clinic_id'],
                'day_of_week' => CarbonImmutable::parse($attributes['session_date'])->isoWeekday(),
                'start_time' => $attributes['start_time'],
                'end_time' => $attributes['end_time'],
                'quota' => $attributes['quota'],
            ])->id,
            'booked_count' => 0,
            'last_queue_number' => 0,
            'status' => SessionStatus::Open,
            'status_reason' => null,
            'schedule_exception_id' => null,
            'pending_change' => null,
            'is_manually_modified' => false,
        ];
    }

    /**
     * Menjaga CHECK `chk_sessions_quota` dan `chk_sessions_queue` pada nilai yang ditimpa tes:
     * `booked_count` dipangkas ke `quota`, dan `last_queue_number` tidak pernah di bawah `booked_count`.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PracticeSession $session): void {
            if ((int) $session->booked_count > (int) $session->quota) {
                $session->booked_count = (int) $session->quota;
            }

            if ((int) $session->last_queue_number < (int) $session->booked_count) {
                $session->last_queue_number = (int) $session->booked_count;
            }
        });
    }

    /** Sesi tambahan manual: tanpa templat (`doctor_schedule_id` NULL). */
    public function manual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'doctor_schedule_id' => null,
        ]);
    }

    /** Ditutup petugas (status `closed`). */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SessionStatus::Closed,
            'status_reason' => 'Ditutup sementara',
        ]);
    }

    /** Dibatalkan (status `cancelled`); isi `schedule_exception_id` untuk sesi yang dibatalkan pengecualian. */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SessionStatus::Cancelled,
            'status_reason' => 'Dibatalkan',
        ]);
    }

    /**
     * Menunggu tindak lanjut petugas pendaftaran, dengan `pending_change` menurut 00 F.4, mis.
     * `['source' => 'exception', 'exception_id' => 12]`.
     *
     * @param  array<string, mixed>  $pendingChange
     */
    public function needsReview(array $pendingChange): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SessionStatus::NeedsReview,
            'pending_change' => $pendingChange,
        ]);
    }

    /**
     * Sesi yang mulai `$minutes` menit dari sekarang (negatif = sudah mulai): `session_date` dan `start_time`
     * dihitung dari `now()` (ikuti `travelTo`), `end_time` = mulai + 4 jam, dipotong ke 23.59.59 bila melewati
     * tengah malam (CHECK `chk_sessions_time`). Hindari jam mulai tepat 23.59.59.
     */
    public function startingInMinutes(int $minutes): static
    {
        return $this->state(function (array $attributes) use ($minutes): array {
            $start = CarbonImmutable::now()->addMinutes($minutes);
            $end = $start->addHours(4);

            if ($end->toDateString() !== $start->toDateString()) {
                $end = $start->setTime(23, 59, 59);
            }

            return [
                'session_date' => $start->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
            ];
        });
    }

    /**
     * Sesi penuh: `booked_count` dan `last_queue_number` = `quota` akhir (setelah semua penimpaan). Memakai
     * `afterMaking` agar `create(['quota' => 3])` tetap konsisten dengan CHECK `chk_sessions_quota` dan
     * `chk_sessions_queue`.
     */
    public function full(): static
    {
        return $this->afterMaking(function (PracticeSession $session): void {
            $session->booked_count = (int) $session->quota;
            $session->last_queue_number = max((int) $session->last_queue_number, (int) $session->quota);
        });
    }
}
