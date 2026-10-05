<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Models\DoctorSchedule;
use App\Models\ScheduleException;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pembangkit sesi praktik dari templat mingguan (00 F.2, 11 §6.4). Idempoten: `insertOrIgnore` terhadap
 * UNIQUE (`doctor_schedule_id`, `session_date`) tidak menyentuh sesi yang sudah ada, termasuk sesi yang
 * diubah manual, dan sesi tambahan manual (tanpa templat) tidak pernah dibuat atau diubah di sini.
 * Tidak membuat sesi lampau (BR-22, F3-R7). Audit tidak ditulis per sesi (12 §8).
 */
final class PracticeSessionGenerator
{
    /** Kunci `Cache::lock` pembangkitan harian dan perintah `sessions:generate` (F3-R8). */
    public const LOCK_KEY = 'sessions:generate';

    /** Jumlah baris per `insertOrIgnore`. */
    private const CHUNK_SIZE = 500;

    public function __construct(private readonly SettingsService $settings) {}

    /** Penanda "sudah dibangkitkan hari ini": `sessions:generated:Y-m-d`. */
    public static function markerKey(CarbonInterface $date): string
    {
        return 'sessions:generated:'.$date->format('Y-m-d');
    }

    /**
     * Membangkitkan sesi untuk `$from` s.d. `$to` (kedua ujung termasuk), diklem ke hari ini. `$only`
     * membatasi pada satu templat. Mengembalikan jumlah sesi BARU yang tersisip.
     *
     * Hanya templat aktif yang berlaku pada rentang itu, dengan dokter `active` dan poliklinik aktif,
     * yang dibangkitkan. Tanggal yang tercakup pengecualian menjadi sesi `cancelled` dengan
     * `schedule_exception_id` dan `status_reason` = alasan pengecualian; bila beberapa pengecualian
     * mencakup, yang khusus dokter didahulukan, lalu `id` terkecil (F3-R10).
     */
    public function ensureRange(CarbonInterface $from, CarbonInterface $to, ?DoctorSchedule $only = null): int
    {
        $today = CarbonImmutable::today();
        $start = $this->day($from);
        $end = $this->day($to);

        if ($start->lessThan($today)) {
            $start = $today;
        }

        if ($end->lessThan($start)) {
            return 0;
        }

        $schedules = DoctorSchedule::query()
            ->active()
            ->when($only !== null, fn (Builder $query): Builder => $query->whereKey($only?->getKey()))
            ->whereHas('doctor', fn (Builder $doctor): Builder => $doctor->where('status', DoctorStatus::Active->value))
            ->whereHas('clinic', fn (Builder $clinic): Builder => $clinic->where('is_active', true))
            ->where('valid_from', '<=', $end->toDateString())
            ->where(function (Builder $range) use ($start): void {
                $range->whereNull('valid_until')->orWhere('valid_until', '>=', $start->toDateString());
            })
            ->get();

        if ($schedules->isEmpty()) {
            return 0;
        }

        $exceptions = ScheduleException::query()
            ->where('end_date', '>=', $start->toDateString())
            ->where('start_date', '<=', $end->toDateString())
            ->get();

        $timestamp = now()->toDateTimeString();
        $rows = [];

        foreach ($schedules as $schedule) {
            $validFrom = CarbonImmutable::parse($schedule->valid_from->toDateString());
            $validUntil = $schedule->valid_until === null ? $end : CarbonImmutable::parse($schedule->valid_until->toDateString());

            $first = $start->greaterThan($validFrom) ? $start : $validFrom;
            $last = $end->lessThan($validUntil) ? $end : $validUntil;

            if ($first->greaterThan($last)) {
                continue;
            }

            // Loncat ke hari templat pertama pada atau setelah $first, lalu maju per minggu.
            $offset = ($schedule->day_of_week - $first->isoWeekday() + 7) % 7;

            for ($date = $first->addDays($offset); $date->lessThanOrEqualTo($last); $date = $date->addWeek()) {
                $exception = ScheduleException::pickFor($exceptions, (int) $schedule->doctor_id, $date);

                $rows[] = [
                    'doctor_id' => $schedule->doctor_id,
                    'clinic_id' => $schedule->clinic_id,
                    'doctor_schedule_id' => $schedule->id,
                    'session_date' => $date->toDateString(),
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'quota' => $schedule->quota,
                    'booked_count' => 0,
                    'last_queue_number' => 0,
                    'status' => $exception === null ? SessionStatus::Open->value : SessionStatus::Cancelled->value,
                    'status_reason' => $exception?->reason,
                    'schedule_exception_id' => $exception?->id,
                    'pending_change' => null,
                    'is_manually_modified' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        $created = 0;

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            $created += DB::table('practice_sessions')->insertOrIgnore($chunk);
        }

        return $created;
    }

    /**
     * Membangkitkan hari ini s.d. hari ini + `booking.window_days`, paling banyak sekali per hari
     * (pemicu b, 00 F.2): penanda `markerKey(today)` berlaku sampai akhir hari. Kunci tidak menunggu
     * (F3-R8): bila proses lain sedang membangkitkan, mengembalikan 0 dan halaman tetap tampil dengan
     * sesi yang ada.
     */
    public function ensureDailyHorizon(): int
    {
        $today = CarbonImmutable::today();
        $marker = self::markerKey($today);

        if (Cache::has($marker)) {
            return 0;
        }

        $created = Cache::lock(self::LOCK_KEY, 30)->get(function () use ($today, $marker): int {
            if (Cache::has($marker)) {
                return 0;
            }

            $created = $this->ensureRange($today, $today->addDays($this->windowDays()));

            Cache::put($marker, true, $today->endOfDay());

            return $created;
        });

        return $created === false ? 0 : (int) $created;
    }

    /** Jendela pembangkitan dalam hari: `booking.window_days` (bawaan 30, minimal 1). */
    private function windowDays(): int
    {
        return max(1, $this->settings->int('booking.window_days', 30));
    }

    /** Awal hari pada zona aplikasi. */
    private function day(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone((string) config('app.timezone'))->startOfDay();
    }
}
