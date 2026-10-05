<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis templat, pengecualian, dan sesi setelah disimpan (00 F.4, 11 §6.6). Setiap sesi
 * diproses dalam transaksinya sendiri dengan lockForUpdate(); audit eksplisit (model jadwal tidak diobservasi
 * AuditObserver) dan tanpa data pasien. Aturan mutlak (sesi lampau, kuota, hapus templat) berlaku untuk semua peran
 * karena dijaga di sini, bukan di Policy.
 */
final class ScheduleChangeService
{
    public const MESSAGE_CLINIC_CHANGE_BLOCKED = 'Poliklinik tidak dapat diganti karena masih ada sesi mendatang dengan pendaftaran aktif. Buat jadwal baru, lalu nonaktifkan jadwal lama.';

    /** Alasan sesi yang dibatalkan karena templat berubah (status_reason). */
    public const REASON_TEMPLATE_CHANGED = 'Jadwal berubah';

    public function __construct(
        private readonly PracticeSessionGenerator $generator,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Mengganti poliklinik templat ditolak bila ada sesi yang belum berakhir dengan pendaftaran aktif (BR-10,
     * CLINIC_CHANGE_BLOCKED; definisi tunggal di Appointment::scopeUpcomingActive). Dipanggil DoctorScheduleRequest
     * sebelum templat disimpan.
     */
    public function assertClinicChangeAllowed(DoctorSchedule $schedule, int $newClinicId): void
    {
        if ($newClinicId === (int) $schedule->getOriginal('clinic_id')) {
            return;
        }

        if ($schedule->hasUpcomingActiveAppointments()) {
            throw ValidationException::withMessages(['clinic_id' => self::MESSAGE_CLINIC_CHANGE_BLOCKED]);
        }
    }

    /**
     * Templat baru sudah tersimpan: bangkitkan sesi untuk jendela pendaftaran dan catat audit `schedule.created`.
     */
    public function applyTemplateCreated(DoctorSchedule $schedule, Actor $actor): ScheduleChangeReport
    {
        return DB::transaction(function () use ($schedule, $actor): ScheduleChangeReport {
            $report = new ScheduleChangeReport(regenerated: $this->generate($schedule));

            $this->audit->log(
                'schedule.created',
                $schedule,
                null,
                $this->scheduleValues($schedule) + ['impact' => $this->impact($report)],
                $this->describeSchedule($schedule, 'ditambahkan'),
                $actor,
            );

            return $report;
        });
    }

    /**
     * Templat sudah diperbarui; `$before` = getOriginal() sebelum update. Hanya sesi yang belum berakhir, berasal
     * dari templat ini, dan `is_manually_modified = false` yang disentuh (F3-R27, BR-83). Keputusan per sesi
     * mengikuti tabel F.4: tanpa appointment sama sekali dihapus lalu dibangkitkan ulang; hanya riwayat batal
     * diperbarui di tempat atau dibatalkan; dengan appointment aktif: kuota disesuaikan (`max(kuota, booked_count)`)
     * atau menjadi needs_review (`template_time` / `template_removed`) lewat setPendingChange().
     *
     * @param  array<string, mixed>  $before
     */
    public function applyTemplateChange(DoctorSchedule $schedule, array $before, Actor $actor): ScheduleChangeReport
    {
        return DB::transaction(function () use ($schedule, $before, $actor): ScheduleChangeReport {
            $counts = $this->counters();

            $ids = PracticeSession::query()
                ->where('doctor_schedule_id', $schedule->getKey())
                ->where('is_manually_modified', false)
                ->notEnded()
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                DB::transaction(function () use ($id, $schedule, $actor, &$counts): void {
                    $session = $this->lockById((int) $id);

                    if ($session === null || $session->is_manually_modified || $session->isEnded()) {
                        return;
                    }

                    $this->propagateTemplate($session, $schedule, $actor, $counts);
                });
            }

            $counts['regenerated'] += $this->generate($schedule);
            $report = $this->toReport($counts);

            $old = $this->scheduleValues($before);
            $new = $this->scheduleValues($schedule);
            $changed = array_keys(array_filter($new, fn (mixed $value, string $key): bool => $value !== $old[$key], ARRAY_FILTER_USE_BOTH));

            $this->audit->log(
                'schedule.updated',
                $schedule,
                array_intersect_key($old, array_flip($changed)),
                array_intersect_key($new, array_flip($changed)) + ['impact' => $this->impact($report)],
                $this->describeSchedule($schedule, 'diubah'),
                $actor,
            );

            return $report;
        });
    }

    /**
     * Hapus templat (BR-12): ditolak bila ada sesi miliknya yang pernah ber-appointment (termasuk riwayat batal).
     * Bila boleh, seluruh sesinya (semuanya tanpa appointment, termasuk yang lampau) dihapus lebih dulu agar tidak
     * ada sesi yatim, lalu templat dihapus. Berlaku untuk Super Admin juga.
     */
    public function deleteTemplate(DoctorSchedule $schedule, Actor $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            DoctorSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();
            PracticeSession::query()->where('doctor_schedule_id', $schedule->getKey())->lockForUpdate()->pluck('id');

            $reason = $schedule->deletionBlockedReason();

            if ($reason !== null) {
                throw ValidationException::withMessages(['schedule' => $reason]);
            }

            $values = $this->scheduleValues($schedule);
            $description = $this->describeSchedule($schedule, 'dihapus');
            $removed = PracticeSession::query()->where('doctor_schedule_id', $schedule->getKey())->delete();

            $schedule->delete();

            $this->audit->log('schedule.deleted', $schedule, $values, ['impact' => ['deleted_sessions' => $removed]], $description, $actor);
        });
    }

    // ---- pembantu privat ----

    /**
     * Terapkan keputusan F.4 pada satu sesi yang sudah dikunci (tanpa menyentuh sesi manual atau lampau).
     *
     * @param  array<string, int>  $counts
     */
    private function propagateTemplate(PracticeSession $session, DoctorSchedule $schedule, Actor $actor, array &$counts): void
    {
        $active = $session->appointments()->active()->count();
        $any = $active > 0 ? $active : $session->appointments()->count();
        $matches = $this->matchesTemplate($session, $schedule);

        if ($any === 0) {
            // Tanpa appointment sama sekali: dihapus lalu dibangkitkan ulang, kecuali sudah persis sama.
            if (! ($matches && $this->sameTimes($session, $schedule) && (int) $session->quota === (int) $schedule->quota)) {
                $session->delete();
            }

            return;
        }

        if ($active === 0) {
            $this->updateHistoryOnlySession($session, $schedule, $matches, $counts);

            return;
        }

        if (! $matches) {
            $this->setPendingChange($session, [
                'source' => PracticeSession::SOURCE_TEMPLATE_REMOVED,
                'schedule_id' => (int) $schedule->getKey(),
            ], $actor);
            $counts['needsReview']++;

            return;
        }

        $quota = max((int) $schedule->quota, (int) $session->booked_count);

        if ($quota !== (int) $session->quota) {
            $session->forceFill(['quota' => $quota])->save();
            $counts['quotaAdjusted']++;
        }

        if (! $this->sameTimes($session, $schedule)) {
            $this->setPendingChange($session, [
                'source' => PracticeSession::SOURCE_TEMPLATE_TIME,
                'schedule_id' => (int) $schedule->getKey(),
                'new_start_time' => $this->hhmm($schedule->start_time),
                'new_end_time' => $this->hhmm($schedule->end_time),
            ], $actor);
            $counts['needsReview']++;
        }
    }

    /**
     * Sesi yang hanya punya riwayat appointment non-aktif: diperbarui di tempat bila masih sah menurut templat,
     * selain itu dibatalkan. Bila dokter atau poliklinik templat berubah, sesi dilepas dari templat agar tanggal
     * itu dapat dibangkitkan ulang (UNIQUE doctor_schedule_id + session_date).
     *
     * @param  array<string, int>  $counts
     */
    private function updateHistoryOnlySession(PracticeSession $session, DoctorSchedule $schedule, bool $matches, array &$counts): void
    {
        if ($matches) {
            $quota = max((int) $schedule->quota, (int) $session->booked_count);

            if (! $this->sameTimes($session, $schedule) || $quota !== (int) $session->quota) {
                $session->forceFill([
                    'start_time' => $this->hhmm($schedule->start_time).':00',
                    'end_time' => $this->hhmm($schedule->end_time).':00',
                    'quota' => $quota,
                ])->save();
                $counts['updated']++;
            }

            return;
        }

        $changes = [];

        if ($session->status !== SessionStatus::Cancelled) {
            $changes = [
                'status' => SessionStatus::Cancelled,
                'status_reason' => self::REASON_TEMPLATE_CHANGED,
                'schedule_exception_id' => null,
                'pending_change' => null,
            ];
            $counts['cancelled']++;
        }

        if ((int) $session->doctor_id !== (int) $schedule->doctor_id || (int) $session->clinic_id !== (int) $schedule->clinic_id) {
            $changes['doctor_schedule_id'] = null;
        }

        if ($changes !== []) {
            $session->forceFill($changes)->save();
        }
    }

    /**
     * Satu sesi hanya menyimpan satu `pending_change` (00 F.4). Sumber jenis-batal mengalahkan jenis-jam; setingkat,
     * yang terbaru menggantikan. Setiap penggantian dicatat `session.pending_replaced`. Dipanggil di dalam transaksi
     * sesi yang sudah dikunci.
     *
     * @param  array<string, mixed>  $change
     */
    private function setPendingChange(PracticeSession $session, array $change, Actor $actor): void
    {
        $change = $this->normalizePending($change);
        $current = $session->status === SessionStatus::NeedsReview && is_array($session->pending_change) && isset($session->pending_change['source'])
            ? $this->normalizePending($session->pending_change)
            : null;

        if ($current !== null) {
            if (in_array($change['source'], PracticeSession::TIME_SOURCES, true) && in_array($current['source'], PracticeSession::CANCEL_SOURCES, true)) {
                return;
            }

            if ($current === $change) {
                return;
            }
        }

        $attributes = ['status' => SessionStatus::NeedsReview, 'pending_change' => $change];

        if ($change['source'] === PracticeSession::SOURCE_EXCEPTION) {
            $attributes['schedule_exception_id'] = $change['exception_id'];
        }

        $session->forceFill($attributes)->save();

        if ($current !== null) {
            $this->audit->log(
                'session.pending_replaced',
                $session,
                ['pending_change' => $current],
                ['pending_change' => $change],
                "Perubahan tertunda sesi {$this->describeSession($session)} diganti.",
                $actor,
            );
        }
    }

    /**
     * Bentuk baku `pending_change` (00 F.4): urutan kunci dan tipe tetap, jam "HH:MM".
     *
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function normalizePending(array $change): array
    {
        return match ($change['source'] ?? null) {
            PracticeSession::SOURCE_EXCEPTION => [
                'source' => PracticeSession::SOURCE_EXCEPTION,
                'exception_id' => (int) $change['exception_id'],
            ],
            PracticeSession::SOURCE_TEMPLATE_TIME => [
                'source' => PracticeSession::SOURCE_TEMPLATE_TIME,
                'schedule_id' => (int) $change['schedule_id'],
                'new_start_time' => $this->hhmm($change['new_start_time']),
                'new_end_time' => $this->hhmm($change['new_end_time']),
            ],
            PracticeSession::SOURCE_TEMPLATE_REMOVED => [
                'source' => PracticeSession::SOURCE_TEMPLATE_REMOVED,
                'schedule_id' => (int) $change['schedule_id'],
            ],
            PracticeSession::SOURCE_MANUAL_TIME => [
                'source' => PracticeSession::SOURCE_MANUAL_TIME,
                'new_start_time' => $this->hhmm($change['new_start_time']),
                'new_end_time' => $this->hhmm($change['new_end_time']),
            ],
            PracticeSession::SOURCE_MANUAL_CANCEL => [
                'source' => PracticeSession::SOURCE_MANUAL_CANCEL,
                'reason' => (string) $change['reason'],
            ],
            PracticeSession::SOURCE_DOCTOR_INACTIVE => [
                'source' => PracticeSession::SOURCE_DOCTOR_INACTIVE,
                'doctor_id' => (int) $change['doctor_id'],
            ],
            default => throw new InvalidArgumentException('Sumber perubahan sesi tidak dikenal.'),
        };
    }

    /**
     * Sesi masih sah menurut templat: templat aktif, dokter dan poliklinik sama, hari sama, dan tanggal dalam
     * rentang berlaku.
     */
    private function matchesTemplate(PracticeSession $session, DoctorSchedule $schedule): bool
    {
        if (
            ! $schedule->is_active
            || (int) $session->doctor_id !== (int) $schedule->doctor_id
            || (int) $session->clinic_id !== (int) $schedule->clinic_id
            || (int) $session->session_date->isoWeekday() !== (int) $schedule->day_of_week
        ) {
            return false;
        }

        $day = $session->session_date->format('Y-m-d');
        $until = $this->dateString($schedule->valid_until);

        return $day >= (string) $this->dateString($schedule->valid_from) && ($until === null || $day <= $until);
    }

    private function sameTimes(PracticeSession $session, DoctorSchedule $schedule): bool
    {
        return $this->hhmm($session->start_time) === $this->hhmm($schedule->start_time)
            && $this->hhmm($session->end_time) === $this->hhmm($schedule->end_time);
    }

    private function lockById(int $id): ?PracticeSession
    {
        return PracticeSession::query()->whereKey($id)->lockForUpdate()->first();
    }

    /**
     * Bangkitkan sesi templat untuk hari ini … hari ini + booking.window_days (idempoten).
     */
    private function generate(DoctorSchedule $schedule): int
    {
        $today = CarbonImmutable::today();

        return $this->generator->ensureRange($today, $today->addDays($this->settings->bookingRules()->windowDays), $schedule);
    }

    /**
     * @return array<string, int>
     */
    private function counters(): array
    {
        return ['regenerated' => 0, 'updated' => 0, 'quotaAdjusted' => 0, 'needsReview' => 0, 'cancelled' => 0, 'reopened' => 0];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function toReport(array $counts): ScheduleChangeReport
    {
        return new ScheduleChangeReport(
            regenerated: $counts['regenerated'],
            updated: $counts['updated'],
            quotaAdjusted: $counts['quotaAdjusted'],
            needsReview: $counts['needsReview'],
            cancelled: $counts['cancelled'],
            reopened: $counts['reopened'],
        );
    }

    /**
     * Dampak untuk audit (hanya angka, tanpa data pasien).
     *
     * @return array<string, int>
     */
    private function impact(ScheduleChangeReport $report): array
    {
        return [
            'regenerated' => $report->regenerated,
            'updated' => $report->updated,
            'quota_adjusted' => $report->quotaAdjusted,
            'needs_review' => $report->needsReview,
            'cancelled' => $report->cancelled,
            'reopened' => $report->reopened,
        ];
    }

    /**
     * Nilai templat yang dicatat di audit, dinormalkan agar `getOriginal()` (bertipe cast) dan model dapat dibandingkan.
     *
     * @param  DoctorSchedule|array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function scheduleValues(DoctorSchedule|array $source): array
    {
        $get = fn (string $key): mixed => $source instanceof DoctorSchedule ? $source->getAttribute($key) : ($source[$key] ?? null);

        return [
            'doctor_id' => (int) $get('doctor_id'),
            'clinic_id' => (int) $get('clinic_id'),
            'day_of_week' => (int) $get('day_of_week'),
            'start_time' => $this->hhmm($get('start_time')),
            'end_time' => $this->hhmm($get('end_time')),
            'quota' => (int) $get('quota'),
            'valid_from' => $this->dateString($get('valid_from')),
            'valid_until' => $this->dateString($get('valid_until')),
            'is_active' => (bool) $get('is_active'),
            'notes' => $get('notes') === null ? null : (string) $get('notes'),
        ];
    }

    private function describeSchedule(DoctorSchedule $schedule, string $verb): string
    {
        $name = Doctor::query()->find($schedule->doctor_id)?->displayName ?? '-';

        return "Templat jadwal {$name} hari {$schedule->day_name} {$schedule->time_range} {$verb}.";
    }

    private function describeSession(PracticeSession $session): string
    {
        return $session->session_date->format('d/m/Y').' '.$session->time_range;
    }

    /** "08:00:00" atau "08:00" menjadi "08:00". */
    private function hhmm(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }
}
