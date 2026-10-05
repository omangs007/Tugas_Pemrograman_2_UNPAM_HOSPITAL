<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis templat, pengecualian, dan sesi setelah disimpan (00 F.4, 11 §6.6). Setiap sesi
 * diproses dalam savepoint (transaksi bersarang di dalam transaksi pemanggil) dengan lockForUpdate(), dan jumlah
 * appointment-nya dibaca dengan pembacaan terkunci agar tidak memakai snapshot lama; audit eksplisit (model jadwal tidak diobservasi
 * AuditObserver) dan tanpa data pasien. Aturan mutlak (sesi lampau, kuota, hapus templat) berlaku untuk semua peran
 * karena dijaga di sini, bukan di Policy.
 */
final class ScheduleChangeService
{
    public const MESSAGE_CLINIC_CHANGE_BLOCKED = 'Poliklinik tidak dapat diganti karena masih ada sesi mendatang dengan pendaftaran aktif. Buat jadwal baru, lalu nonaktifkan jadwal lama.';

    /** Alasan sesi yang dibatalkan karena templat berubah (status_reason). */
    public const REASON_TEMPLATE_CHANGED = 'Jadwal berubah';

    /** Alasan sesi yang dibatalkan karena dokter dinonaktifkan atau diarsipkan (status_reason). */
    public const REASON_DOCTOR_INACTIVE = 'Dokter tidak aktif';

    /** Pesan baku V-24/BR-33 untuk sesi yang sudah berakhir (kunci validasi: session). */
    public const MESSAGE_SESSION_PAST = 'Sesi yang sudah lewat tidak dapat diubah.';

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

    /**
     * Pengecualian sudah tersimpan (baru, atau diubah dengan `$previous` = ['start_date', 'end_date', 'doctor_id']
     * sebelum diubah). Sesi yang belum berakhir dalam rentang dan lingkup dokter diproses tanpa melihat
     * `is_manually_modified` (BR-27): tanpa appointment aktif menjadi `cancelled`, dengan appointment aktif menjadi
     * `needs_review` sumber `exception` lewat setPendingChange() (juga untuk sesi yang sudah `needs_review`, F3-R11).
     * Sesi yang terlepas dari rentang atau lingkup baru dibuka kembali (penyempitan/perpindahan). Sesi yang belum
     * ada dibangkitkan langsung `cancelled` oleh generator.
     *
     * @param  array<string, mixed>|null  $previous
     */
    public function applyException(ScheduleException $exception, ?array $previous, Actor $actor): ScheduleChangeReport
    {
        $this->assertExceptionAllowed($exception, $previous);

        return DB::transaction(function () use ($exception, $previous, $actor): ScheduleChangeReport {
            $counts = $this->counters();

            if ($previous !== null) {
                foreach ($this->detachedSessionIds($exception) as $id) {
                    DB::transaction(function () use ($id, $exception, $actor, &$counts): void {
                        $session = $this->lockById((int) $id);

                        if (
                            $session === null
                            || $session->isEnded()
                            || (int) $session->schedule_exception_id !== (int) $exception->getKey()
                            || $this->isCoveredBy($session, $exception)
                        ) {
                            return;
                        }

                        $this->releaseFromException($session, $exception, $actor, $counts);
                    });
                }
            }

            foreach ($this->coveredSessionIds($exception) as $id) {
                DB::transaction(function () use ($id, $exception, $actor, &$counts): void {
                    $session = $this->lockById((int) $id);

                    if ($session === null || ! $this->isTarget($session, $exception)) {
                        return;
                    }

                    $this->blockForException($session, $exception, $actor, $counts);
                });
            }

            $today = CarbonImmutable::today();
            $this->generator->ensureRange($today, $today->addDays($this->settings->bookingRules()->windowDays));

            $report = $this->toReport($counts);
            $new = $this->exceptionValues($exception) + ['impact' => $this->impact($report)];

            if ($previous === null) {
                $this->audit->log('exception.created', $exception, null, $new, $this->describeException($exception, 'ditambahkan'), $actor);

                return $report;
            }

            $old = [
                'start_date' => $this->dateString($previous['start_date'] ?? null),
                'end_date' => $this->dateString($previous['end_date'] ?? null),
                'doctor_id' => ($previous['doctor_id'] ?? null) === null ? null : (int) $previous['doctor_id'],
            ];

            $this->audit->log('exception.updated', $exception, $old, $new, $this->describeException($exception, 'diubah'), $actor);

            return $report;
        });
    }

    /**
     * Cabut pengecualian; dipanggil SEBELUM barisnya dihapus (pemanggil menghapus sesudahnya dalam transaksi yang
     * sama). Hanya sesi bertaut yang belum berakhir: `cancelled` dibuka kembali, `needs_review` sumber `exception`
     * kembali `open` (appointment tetap), dan bila tanggalnya masih tercakup pengecualian lain, sesi dipindahkan ke
     * pengecualian itu (F3-R10: khusus dokter lebih dulu, lalu id terkecil).
     */
    public function removeException(ScheduleException $exception, Actor $actor): ScheduleChangeReport
    {
        return DB::transaction(function () use ($exception, $actor): ScheduleChangeReport {
            $counts = $this->counters();

            $ids = PracticeSession::query()
                ->where('schedule_exception_id', $exception->getKey())
                ->notEnded()
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                DB::transaction(function () use ($id, $exception, $actor, &$counts): void {
                    $session = $this->lockById((int) $id);

                    if ($session === null || $session->isEnded() || (int) $session->schedule_exception_id !== (int) $exception->getKey()) {
                        return;
                    }

                    $this->releaseFromException($session, $exception, $actor, $counts);
                });
            }

            $report = $this->toReport($counts);

            $this->audit->log(
                'exception.deleted',
                $exception,
                $this->exceptionValues($exception),
                ['impact' => $this->impact($report)],
                $this->describeException($exception, 'dihapus'),
                $actor,
            );

            return $report;
        });
    }

    /**
     * Sesi tambahan manual (`doctor_schedule_id` NULL; F3-R26, V-24, BR-29): dokter harus `active`, poliklinik aktif
     * dan dibuka untuk pendaftaran, tanggal hari ini … 365 hari ke depan dan belum berakhir, tidak dalam rentang
     * pengecualian, dan jamnya tidak beririsan dengan sesi non-batal dokter itu pada tanggal yang sama. Baris
     * dokter dikunci selama pemeriksaan irisan.
     *
     * @param  array<string, mixed>  $attributes  doctor_id, clinic_id, session_date, start_time, end_time, quota
     */
    public function createManualSession(array $attributes, Actor $actor): PracticeSession
    {
        return DB::transaction(function () use ($attributes, $actor): PracticeSession {
            $errors = [];
            $today = CarbonImmutable::today();

            $doctorId = (int) ($attributes['doctor_id'] ?? 0);
            $doctor = Doctor::query()->whereKey($doctorId)->lockForUpdate()->first(['id', 'status']);

            if ($doctor === null || $doctor->status !== DoctorStatus::Active) {
                $errors['doctor_id'] = 'Dokter yang dipilih tidak tersedia.';
            }

            $clinicId = (int) ($attributes['clinic_id'] ?? 0);
            $clinic = Clinic::query()->whereKey($clinicId)->first(['id', 'is_active', 'is_bookable']);

            if ($clinic === null || ! $clinic->is_active || ! $clinic->is_bookable) {
                $errors['clinic_id'] = 'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.';
            }

            $start = $this->timeOrNull($attributes['start_time'] ?? null);
            $end = $this->timeOrNull($attributes['end_time'] ?? null);

            if ($start === null) {
                $errors['start_time'] = 'Format jam mulai tidak valid. Gunakan format JJ:MM.';
            }

            if ($end === null) {
                $errors['end_time'] = 'Format jam selesai tidak valid. Gunakan format JJ:MM.';
            } elseif ($start !== null && $end <= $start) {
                $errors['end_time'] = 'Jam selesai harus setelah jam mulai.';
            }

            $quota = (int) ($attributes['quota'] ?? 0);

            if ($quota < 1 || $quota > 200) {
                $errors['quota'] = 'Kuota minimal 1 dan maksimal 200.';
            }

            $date = $this->dateString($attributes['session_date'] ?? null);

            if ($date === null) {
                $errors['session_date'] = 'Tanggal sesi wajib diisi.';
            } elseif (
                $date < $today->format('Y-m-d')
                || ($date === $today->format('Y-m-d') && $end !== null && $end.':00' <= CarbonImmutable::now()->format('H:i:s'))
            ) {
                $errors['session_date'] = 'Tanggal sesi tidak boleh di masa lalu.';
            } elseif ($date > $today->addDays(365)->format('Y-m-d')) {
                $errors['session_date'] = 'Tanggal sesi maksimal 365 hari ke depan.';
            }

            if ($errors === []) {
                $day = CarbonImmutable::parse($date);
                $covering = ScheduleException::query()->covering($day)->forDoctor($doctorId)->get();
                $picked = ScheduleException::pickFor($covering, $doctorId, $day);

                if ($picked !== null) {
                    $errors['session_date'] = "Tanggal ini berada dalam pengecualian jadwal ({$picked->reason}). Hapus atau ubah pengecualian terlebih dahulu.";
                } elseif ($this->overlappingSessionExists($doctorId, $date, $start, $end, null)) {
                    $errors['start_time'] = 'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $session = new PracticeSession;
            $session->fill([
                'doctor_id' => $doctorId,
                'clinic_id' => $clinicId,
                'doctor_schedule_id' => null,
                'session_date' => $date,
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'quota' => $quota,
                'status' => SessionStatus::Open,
                'is_manually_modified' => true,
            ]);
            $session->save();
            $session->refresh();

            $this->audit->log(
                'session.created',
                $session,
                null,
                $this->sessionValues($session),
                "Sesi tambahan {$this->describeSession($session)} dibuat.",
                $actor,
            );

            return $session;
        });
    }

    /**
     * Ubah satu sesi (BR-30/BR-35, F3-R27): kuota langsung (1–200 dan tidak di bawah `booked_count`); jam langsung
     * bila tidak ada appointment aktif, selain itu sesi menjadi `needs_review` sumber `manual_time` dan jam lama
     * tetap berlaku sampai resolusi. Selalu menyetel `is_manually_modified = true`. Sesi lampau ditolak.
     *
     * @param  array<string, mixed>  $attributes  quota, start_time, end_time (yang tidak dikirim tidak diubah)
     */
    public function updateSession(PracticeSession $session, array $attributes, Actor $actor): PracticeSession
    {
        return DB::transaction(function () use ($session, $attributes, $actor): PracticeSession {
            $this->lockFresh($session);
            $this->assertNotEnded($session);

            $errors = [];
            $quota = (int) $session->quota;
            $start = $this->hhmm($session->start_time);
            $end = $this->hhmm($session->end_time);

            if (array_key_exists('quota', $attributes)) {
                $quota = (int) $attributes['quota'];

                if ($quota < 1 || $quota > 200) {
                    $errors['quota'] = 'Kuota minimal 1 dan maksimal 200.';
                } elseif ($quota < (int) $session->booked_count) {
                    $errors['quota'] = "Kuota tidak boleh kurang dari jumlah pendaftaran yang sudah ada ({$session->booked_count}).";
                }
            }

            if (array_key_exists('start_time', $attributes)) {
                $value = $this->timeOrNull($attributes['start_time']);

                if ($value === null) {
                    $errors['start_time'] = 'Format jam mulai tidak valid. Gunakan format JJ:MM.';
                } else {
                    $start = $value;
                }
            }

            if (array_key_exists('end_time', $attributes)) {
                $value = $this->timeOrNull($attributes['end_time']);

                if ($value === null) {
                    $errors['end_time'] = 'Format jam selesai tidak valid. Gunakan format JJ:MM.';
                } else {
                    $end = $value;
                }
            }

            if (! isset($errors['start_time']) && ! isset($errors['end_time']) && $end <= $start) {
                $errors['end_time'] = 'Jam selesai harus setelah jam mulai.';
            }

            $timesChanged = $start !== $this->hhmm($session->start_time) || $end !== $this->hhmm($session->end_time);

            if (
                $errors === []
                && $timesChanged
                && $session->status !== SessionStatus::Cancelled
                && $this->overlappingSessionExists((int) $session->doctor_id, $session->session_date->format('Y-m-d'), $start, $end, (int) $session->getKey())
            ) {
                $errors['start_time'] = 'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $old = [];
            $new = [];
            $values = ['is_manually_modified' => true];

            if ($quota !== (int) $session->quota) {
                $old['quota'] = (int) $session->quota;
                $new['quota'] = $quota;
                $values['quota'] = $quota;
            }

            $held = $timesChanged && $this->activeAppointmentCount($session) > 0;

            if ($timesChanged && ! $held) {
                $old['start_time'] = $this->hhmm($session->start_time);
                $old['end_time'] = $this->hhmm($session->end_time);
                $new['start_time'] = $start;
                $new['end_time'] = $end;
                $values['start_time'] = $start.':00';
                $values['end_time'] = $end.':00';
            }

            $oldStatus = $session->status;
            $session->forceFill($values)->save();

            if ($held) {
                $this->setPendingChange($session, [
                    'source' => PracticeSession::SOURCE_MANUAL_TIME,
                    'new_start_time' => $start,
                    'new_end_time' => $end,
                ], $actor);

                $old['status'] = $oldStatus->value;
                $new['status'] = $session->status->value;
                $new['pending_change'] = $session->pending_change;
            }

            if ($new !== []) {
                $this->audit->log('session.updated', $session, $old, $new, "Sesi {$this->describeSession($session)} diubah.", $actor);
            }

            return $session;
        });
    }

    /**
     * Buka/tutup/batalkan satu sesi (BR-35, BR-177, F3-R27). `open ↔ closed` langsung (appointment lama tetap berlaku).
     * Pembatalan wajib beralasan: langsung `cancelled` bila tidak ada appointment aktif, selain itu `needs_review`
     * sumber `manual_cancel`. Membuka kembali sesi `cancelled` ditolak bila masih tercakup pengecualian atau dokter
     * belum `active`. Sesi `needs_review` hanya diselesaikan lewat layar Sesi terdampak (fase 7). Selalu menyetel
     * `is_manually_modified = true`.
     */
    public function setSessionStatus(PracticeSession $session, SessionStatus $to, Actor $actor, ?string $reason = null): PracticeSession
    {
        if ($to === SessionStatus::NeedsReview) {
            throw new InvalidArgumentException('Status needs_review hanya ditetapkan oleh sistem.');
        }

        return DB::transaction(function () use ($session, $to, $actor, $reason): PracticeSession {
            $this->lockFresh($session);
            $this->assertNotEnded($session);

            $from = $session->status;
            $reason = $this->nullableTrim($reason);

            if ($from === SessionStatus::NeedsReview) {
                throw ValidationException::withMessages(['status' => 'Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak.']);
            }

            if ($from === $to) {
                return $session;
            }

            if ($reason !== null && mb_strlen($reason) > 255) {
                throw ValidationException::withMessages(['reason' => 'Alasan maksimal 255 karakter.']);
            }

            $old = ['status' => $from->value, 'status_reason' => $session->status_reason];

            if ($to === SessionStatus::Cancelled) {
                if ($reason === null) {
                    throw ValidationException::withMessages(['reason' => 'Alasan pembatalan sesi wajib diisi.']);
                }

                if ($this->activeAppointmentCount($session) > 0) {
                    $session->forceFill(['is_manually_modified' => true])->save();
                    $this->setPendingChange($session, ['source' => PracticeSession::SOURCE_MANUAL_CANCEL, 'reason' => $reason], $actor);
                } else {
                    $session->forceFill([
                        'status' => SessionStatus::Cancelled,
                        'status_reason' => $reason,
                        'schedule_exception_id' => null,
                        'pending_change' => null,
                        'is_manually_modified' => true,
                    ])->save();
                }
            } elseif ($to === SessionStatus::Open) {
                if ($from === SessionStatus::Cancelled) {
                    $this->assertReopenAllowed($session);
                }

                $session->forceFill([
                    'status' => SessionStatus::Open,
                    'status_reason' => null,
                    'schedule_exception_id' => null,
                    'pending_change' => null,
                    'is_manually_modified' => true,
                ])->save();
            } else {
                if ($from === SessionStatus::Cancelled) {
                    throw ValidationException::withMessages(['status' => 'Sesi yang dibatalkan hanya dapat dibuka kembali.']);
                }

                $session->forceFill(['status' => SessionStatus::Closed, 'status_reason' => $reason, 'is_manually_modified' => true])->save();
            }

            $new = ['status' => $session->status->value, 'status_reason' => $session->status_reason];

            if ($session->status === SessionStatus::NeedsReview) {
                $new['pending_change'] = $session->pending_change;
            }

            $this->audit->log(
                'session.status_changed',
                $session,
                $old,
                $new,
                "Status sesi {$this->describeSession($session)} diubah dari {$from->label()} menjadi {$session->status->label()}.",
                $actor,
            );

            return $session;
        });
    }

    /**
     * Hapus sesi tambahan manual (00 C, F3-R20, JDW-19): hanya `doctor_schedule_id` NULL tanpa appointment sama
     * sekali (`PracticeSession::deletionBlockedReason()`), dan belum berakhir. Berlaku untuk Super Admin juga.
     */
    public function deleteManualSession(PracticeSession $session, Actor $actor): void
    {
        DB::transaction(function () use ($session, $actor): void {
            $this->lockFresh($session);

            $reason = $session->deletionBlockedReason();

            if ($reason !== null) {
                throw ValidationException::withMessages(['session' => $reason]);
            }

            $this->assertNotEnded($session);

            $values = $this->sessionValues($session);
            $description = "Sesi tambahan {$this->describeSession($session)} dihapus.";

            $session->delete();

            $this->audit->log('session.deleted', $session, $values, null, $description, $actor);
        });
    }

    /**
     * Dokter dinonaktifkan atau diarsipkan (00 F.4, BR-05/BR-177). Sesi mendatang yang belum berakhir: tanpa
     * appointment aktif menjadi `cancelled` ("Dokter tidak aktif"), dengan appointment aktif menjadi `needs_review`
     * sumber `doctor_inactive` lewat setPendingChange(). Sesi yang sudah `cancelled` tidak disentuh. Audit
     * `doctor.status_changed`/`doctor.archived` ditulis DoctorService dengan ringkasan dampak dari laporan ini.
     */
    public function applyDoctorInactive(Doctor $doctor, Actor $actor): ScheduleChangeReport
    {
        return DB::transaction(function () use ($doctor, $actor): ScheduleChangeReport {
            $counts = $this->counters();
            $open = [SessionStatus::Open, SessionStatus::Closed, SessionStatus::NeedsReview];

            $ids = PracticeSession::query()
                ->where('doctor_id', $doctor->getKey())
                ->whereIn('status', array_map(fn (SessionStatus $status): string => $status->value, $open))
                ->notEnded()
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                DB::transaction(function () use ($id, $doctor, $actor, $open, &$counts): void {
                    $session = $this->lockById((int) $id);

                    if ($session === null || $session->isEnded() || ! in_array($session->status, $open, true)) {
                        return;
                    }

                    if ($this->activeAppointmentCount($session) === 0) {
                        $session->forceFill([
                            'status' => SessionStatus::Cancelled,
                            'status_reason' => self::REASON_DOCTOR_INACTIVE,
                            'schedule_exception_id' => null,
                            'pending_change' => null,
                        ])->save();
                        $counts['cancelled']++;

                        return;
                    }

                    $this->setPendingChange($session, [
                        'source' => PracticeSession::SOURCE_DOCTOR_INACTIVE,
                        'doctor_id' => (int) $doctor->getKey(),
                    ], $actor);
                    $counts['needsReview']++;
                });
            }

            return $this->toReport($counts);
        });
    }

    // ---- pembantu privat ----

    /**
     * Baca ulang baris sesi dengan kunci tulis dan perbarui instance pemanggil, agar instance basi tidak menimpa
     * perubahan lain. Dipanggil di dalam transaksi.
     */
    private function lockFresh(PracticeSession $session): void
    {
        $fresh = PracticeSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

        $session->setRawAttributes($fresh->getRawOriginal(), true);
    }

    /** Sesi yang sudah berakhir tidak boleh diubah, ditutup, dibatalkan, atau dihapus (F3-R27, BR-33). */
    private function assertNotEnded(PracticeSession $session): void
    {
        if ($session->isEnded()) {
            throw ValidationException::withMessages(['session' => self::MESSAGE_SESSION_PAST]);
        }
    }

    /**
     * Sesi `cancelled` hanya boleh dibuka bila dokter `active` dan tanggalnya tidak lagi tercakup pengecualian.
     */
    private function assertReopenAllowed(PracticeSession $session): void
    {
        $doctor = Doctor::query()->whereKey($session->doctor_id)->first(['id', 'status']);

        if ($doctor === null || $doctor->status !== DoctorStatus::Active) {
            throw ValidationException::withMessages(['status' => 'Sesi tidak dapat dibuka karena dokter belum aktif kembali.']);
        }

        $covering = ScheduleException::query()->covering($session->session_date)->forDoctor((int) $session->doctor_id)->get();
        $picked = ScheduleException::pickFor($covering, (int) $session->doctor_id, $session->session_date);

        if ($picked !== null) {
            throw ValidationException::withMessages([
                'status' => "Tanggal ini berada dalam pengecualian jadwal ({$picked->reason}). Hapus atau ubah pengecualian terlebih dahulu.",
            ]);
        }
    }

    /**
     * Irisan jam dengan sesi non-batal dokter yang sama pada tanggal itu (BR-29, I-28): `start1 < end2 AND start2 < end1`.
     */
    private function overlappingSessionExists(int $doctorId, string $date, string $start, string $end, ?int $ignoreId): bool
    {
        return PracticeSession::query()
            ->where('doctor_id', $doctorId)
            ->where('session_date', $date)
            ->where('status', '!=', SessionStatus::Cancelled->value)
            ->where('start_time', '<', $end.':00')
            ->where('end_time', '>', $start.':00')
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Nilai sesi yang dicatat di audit (tanpa data pasien).
     *
     * @return array<string, mixed>
     */
    private function sessionValues(PracticeSession $session): array
    {
        return [
            'doctor_id' => (int) $session->doctor_id,
            'clinic_id' => (int) $session->clinic_id,
            'session_date' => $session->session_date->format('Y-m-d'),
            'start_time' => $this->hhmm($session->start_time),
            'end_time' => $this->hhmm($session->end_time),
            'quota' => (int) $session->quota,
            'status' => $session->status->value,
        ];
    }

    /** "HH:MM" atau "HH:MM:SS" yang sah menjadi "HH:MM"; selain itu null. */
    private function timeOrNull(mixed $time): ?string
    {
        if (! is_string($time) || preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time) !== 1) {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Pagar bagi pemanggil non-HTTP (kombinasi jenis vs dokter E.2, urutan tanggal, dan F3-R21: `start_date` lampau
     * hanya boleh bila pengecualian lama dan tanggalnya tidak berubah).
     *
     * @param  array<string, mixed>|null  $previous
     */
    private function assertExceptionAllowed(ScheduleException $exception, ?array $previous): void
    {
        $errors = [];

        if ($exception->type->requiresDoctor() && $exception->doctor_id === null) {
            $errors['doctor_id'] = 'Pilih dokter untuk cuti dokter.';
        }

        if ($exception->type->forbidsDoctor() && $exception->doctor_id !== null) {
            $errors['doctor_id'] = 'Libur rumah sakit atau libur nasional berlaku untuk semua dokter. Kosongkan pilihan dokter.';
        }

        $start = (string) $this->dateString($exception->start_date);
        $end = (string) $this->dateString($exception->end_date);

        if ($end < $start) {
            $errors['end_date'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        $startChanged = $previous === null || $this->dateString($previous['start_date'] ?? null) !== $start;

        if ($startChanged && $start < CarbonImmutable::today()->format('Y-m-d')) {
            $errors['start_date'] = 'Tanggal mulai tidak boleh di masa lalu.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Sesi yang belum berakhir, berstatus open/closed/needs_review, dalam rentang dan lingkup dokter pengecualian.
     *
     * @return Collection<int, int>
     */
    private function coveredSessionIds(ScheduleException $exception): Collection
    {
        return PracticeSession::query()
            ->whereBetween('session_date', [$this->dateString($exception->start_date), $this->dateString($exception->end_date)])
            ->whereIn('status', [SessionStatus::Open->value, SessionStatus::Closed->value, SessionStatus::NeedsReview->value])
            ->when($exception->doctor_id !== null, fn (Builder $query) => $query->where('doctor_id', $exception->doctor_id))
            ->notEnded()
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Sesi bertaut ke pengecualian ini yang kini berada di luar rentang atau lingkup dokternya.
     *
     * @return Collection<int, int>
     */
    private function detachedSessionIds(ScheduleException $exception): Collection
    {
        return PracticeSession::query()
            ->where('schedule_exception_id', $exception->getKey())
            ->notEnded()
            ->where(function (Builder $outside) use ($exception): void {
                $outside->where('session_date', '<', $this->dateString($exception->start_date))
                    ->orWhere('session_date', '>', $this->dateString($exception->end_date));

                if ($exception->doctor_id !== null) {
                    $outside->orWhere('doctor_id', '!=', $exception->doctor_id);
                }
            })
            ->orderBy('id')
            ->pluck('id');
    }

    private function isCoveredBy(PracticeSession $session, ScheduleException $exception): bool
    {
        $day = $session->session_date->format('Y-m-d');

        return $day >= (string) $this->dateString($exception->start_date)
            && $day <= (string) $this->dateString($exception->end_date)
            && ($exception->doctor_id === null || (int) $session->doctor_id === (int) $exception->doctor_id);
    }

    private function isTarget(PracticeSession $session, ScheduleException $exception): bool
    {
        return $this->isCoveredBy($session, $exception)
            && ! $session->isEnded()
            && in_array($session->status, [SessionStatus::Open, SessionStatus::Closed, SessionStatus::NeedsReview], true);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function blockForException(PracticeSession $session, ScheduleException $exception, Actor $actor, array &$counts): void
    {
        if ($this->activeAppointmentCount($session) === 0) {
            $session->forceFill([
                'status' => SessionStatus::Cancelled,
                'schedule_exception_id' => $exception->getKey(),
                'status_reason' => $exception->reason,
                'pending_change' => null,
            ])->save();
            $counts['cancelled']++;

            return;
        }

        $pending = $session->status === SessionStatus::NeedsReview ? $session->pending_change : null;

        if (
            is_array($pending)
            && ($pending['source'] ?? null) === PracticeSession::SOURCE_EXCEPTION
            && (int) ($pending['exception_id'] ?? 0) === (int) $exception->getKey()
        ) {
            return;
        }

        $this->setPendingChange($session, [
            'source' => PracticeSession::SOURCE_EXCEPTION,
            'exception_id' => (int) $exception->getKey(),
        ], $actor);
        $counts['needsReview']++;
    }

    /**
     * Lepaskan sesi dari pengecualian: pindah ke pengecualian lain yang masih mencakup tanggalnya (F3-R10), atau
     * buka kembali. Sesi `needs_review` bersumber lain hanya dilepas tautannya.
     *
     * @param  array<string, int>  $counts
     */
    private function releaseFromException(PracticeSession $session, ScheduleException $exception, Actor $actor, array &$counts): void
    {
        $date = $session->session_date;
        $others = ScheduleException::query()
            ->whereKeyNot($exception->getKey())
            ->covering($date)
            ->forDoctor((int) $session->doctor_id)
            ->get();
        $other = ScheduleException::pickFor($others, (int) $session->doctor_id, $date);

        if ($session->status === SessionStatus::Cancelled) {
            if ($other !== null) {
                $session->forceFill(['schedule_exception_id' => $other->getKey(), 'status_reason' => $other->reason])->save();

                return;
            }

            $this->reopenSession($session, $actor, $counts);

            return;
        }

        if ($session->status === SessionStatus::NeedsReview && ($session->pending_change['source'] ?? null) === PracticeSession::SOURCE_EXCEPTION) {
            if ($other !== null) {
                $this->setPendingChange($session, [
                    'source' => PracticeSession::SOURCE_EXCEPTION,
                    'exception_id' => (int) $other->getKey(),
                ], $actor);

                return;
            }

            $this->reopenSession($session, $actor, $counts);

            return;
        }

        $session->forceFill(['schedule_exception_id' => null])->save();
    }

    /**
     * Evaluasi ulang sesi yang lepas dari pengecualian (BR-28): dokter non-aktif atau templat yang sudah tidak
     * berlaku menahan sesi (batal, atau needs_review bila ada appointment aktif); jam templat yang berubah selama
     * pengecualian berlaku ditunda ke tinjauan; selain itu sesi dibuka kembali.
     *
     * @param  array<string, int>  $counts
     */
    private function reopenSession(PracticeSession $session, Actor $actor, array &$counts): void
    {
        $active = $this->activeAppointmentCount($session);
        $doctor = Doctor::query()->whereKey($session->doctor_id)->first(['id', 'status']);
        $schedule = $session->doctor_schedule_id === null ? null : DoctorSchedule::query()->find($session->doctor_schedule_id);

        if ($doctor === null || $doctor->status !== DoctorStatus::Active) {
            $this->holdAfterRelease($session, $active, [
                'source' => PracticeSession::SOURCE_DOCTOR_INACTIVE,
                'doctor_id' => (int) $session->doctor_id,
            ], self::REASON_DOCTOR_INACTIVE, $actor, $counts);

            return;
        }

        if ($schedule !== null && ! $this->matchesTemplate($session, $schedule)) {
            $this->holdAfterRelease($session, $active, [
                'source' => PracticeSession::SOURCE_TEMPLATE_REMOVED,
                'schedule_id' => (int) $schedule->getKey(),
            ], self::REASON_TEMPLATE_CHANGED, $actor, $counts);

            return;
        }

        $session->forceFill([
            'status' => SessionStatus::Open,
            'status_reason' => null,
            'schedule_exception_id' => null,
            'pending_change' => null,
        ])->save();

        if ($schedule !== null && $active > 0 && ! $this->sameTimes($session, $schedule)) {
            $this->setPendingChange($session, [
                'source' => PracticeSession::SOURCE_TEMPLATE_TIME,
                'schedule_id' => (int) $schedule->getKey(),
                'new_start_time' => $this->hhmm($schedule->start_time),
                'new_end_time' => $this->hhmm($schedule->end_time),
            ], $actor);
            $counts['needsReview']++;

            return;
        }

        $counts['reopened']++;
    }

    /**
     * @param  array<string, mixed>  $pending
     * @param  array<string, int>  $counts
     */
    private function holdAfterRelease(PracticeSession $session, int $active, array $pending, string $reason, Actor $actor, array &$counts): void
    {
        $session->forceFill(['schedule_exception_id' => null])->save();

        if ($active > 0) {
            $this->setPendingChange($session, $pending, $actor);
            $counts['needsReview']++;

            return;
        }

        $session->forceFill(['status' => SessionStatus::Cancelled, 'status_reason' => $reason, 'pending_change' => null])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionValues(ScheduleException $exception): array
    {
        return [
            'type' => $exception->type->value,
            'doctor_id' => $exception->doctor_id === null ? null : (int) $exception->doctor_id,
            'start_date' => $this->dateString($exception->start_date),
            'end_date' => $this->dateString($exception->end_date),
            'reason' => (string) $exception->reason,
        ];
    }

    private function describeException(ScheduleException $exception, string $verb): string
    {
        $range = CarbonImmutable::parse((string) $this->dateString($exception->start_date))->format('d/m/Y')
            .'–'.CarbonImmutable::parse((string) $this->dateString($exception->end_date))->format('d/m/Y');

        return "Pengecualian jadwal \"{$exception->reason}\" ({$range}) {$verb}.";
    }

    /**
     * Terapkan keputusan F.4 pada satu sesi yang sudah dikunci (tanpa menyentuh sesi manual atau lampau).
     *
     * @param  array<string, int>  $counts
     */
    private function propagateTemplate(PracticeSession $session, DoctorSchedule $schedule, Actor $actor, array &$counts): void
    {
        $active = $this->activeAppointmentCount($session);
        $any = $active > 0 ? $active : $this->appointmentCount($session);
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

    /**
     * Hitung appointment aktif sesi yang sudah dikunci dengan pembacaan terkunci (FOR UPDATE), agar yang terbaca baris
     * terkomit terbaru dan bukan snapshot REPEATABLE READ transaksi pemanggil.
     */
    private function activeAppointmentCount(PracticeSession $session): int
    {
        return $session->appointments()->active()->lockForUpdate()->count();
    }

    /** Seperti activeAppointmentCount(), tetapi seluruh appointment sesi (termasuk riwayat batal). */
    private function appointmentCount(PracticeSession $session): int
    {
        return $session->appointments()->lockForUpdate()->count();
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
