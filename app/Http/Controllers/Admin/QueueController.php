<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkNoShowRequest;
use App\Http\Requests\Admin\QueueIndexRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Booking\AppointmentStatusService;
use App\Services\Booking\BookingCodeGenerator;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Antrean Hari Ini (W-19, F7A-R23) dan aksi massal Tandai tidak hadir. Daftar membaca per sesi dalam satu kueri
 * (urut nomor antrean, eager-load penuh); tombol per baris dihitung `AppointmentStatusService::transitionChecks()`
 * (evaluator yang sama dengan `transition()`), dengan satu `$now` per muat halaman. Tulis status hanya lewat
 * AppointmentStatusService (`markNoShowForSession` untuk aksi massal; aksi per baris memakai route
 * `admin.appointments.transition` milik AppointmentController). Pemindaian kode hanya mencari dan menyorot (BR-69).
 */
class QueueController extends Controller
{
    /** Urutan ringkasan per status (BP 06 alur 7). */
    private const STATUS_ORDER = [
        AppointmentStatus::Pending,
        AppointmentStatus::Confirmed,
        AppointmentStatus::CheckedIn,
        AppointmentStatus::Completed,
        AppointmentStatus::NoShow,
        AppointmentStatus::Cancelled,
    ];

    public function index(QueueIndexRequest $request, AppointmentStatusService $statuses): View
    {
        $this->authorize('viewAny', Appointment::class);

        $user = $request->user();
        $loadedAt = CarbonImmutable::now();
        $date = $request->queueDate();
        $clinicSlug = $request->clinicSlug();
        $doctorSlug = $request->doctorSlug();

        [$scan, $found] = $this->scan($request->bookingCode(), $date);

        $sessions = $this->sessionsOn($date, $clinicSlug, $doctorSlug);

        // Kode yang ditemukan pada tanggal ini selalu ditampilkan, juga bila filter poli/dokter menyembunyikan sesinya.
        if ($found !== null && ! $sessions->contains('id', (int) $found->practice_session_id)) {
            $clinicSlug = null;
            $doctorSlug = null;
            $sessions = $this->sessionsOn($date, null, null);
        }

        $wanted = $found !== null ? (int) $found->practice_session_id : $request->sessionId();
        $session = $wanted === null
            ? null
            : $sessions->first(fn (PracticeSession $candidate): bool => (int) $candidate->id === $wanted);
        $session ??= $sessions->first();

        $returnUrl = route('admin.queue.index', array_filter([
            'tanggal' => $date->toDateString(),
            'sesi' => $session?->id,
            'poli' => $clinicSlug,
            'dokter' => $doctorSlug,
        ], fn (mixed $value): bool => $value !== null), false);

        /** @var Collection<int, Appointment> $appointments */
        $appointments = new Collection;
        $checks = [];
        $pendingCount = 0;
        $ended = false;
        $underReview = false;
        $canManage = false;
        $canMarkNoShow = false;

        if ($session !== null) {
            $appointments = Appointment::query()
                ->with(['patientProfile', 'clinic', 'practiceSession'])
                ->where('appointments.practice_session_id', $session->id)
                ->orderBy('appointments.queue_number')
                ->orderBy('appointments.id')
                ->get();

            $canManage = $appointments->isNotEmpty() && $user->can('transition', $appointments->first());

            if ($canManage) {
                $actor = Actor::staff($user);

                foreach ($appointments as $appointment) {
                    $checks[$appointment->id] = $statuses->transitionChecks($appointment, $actor, $loadedAt);
                }
            }

            // Batas strik, sama dengan markNoShowForSession(): tepat pada end_time sesi BELUM berakhir.
            $ended = $loadedAt->greaterThan($session->ends_at);
            $underReview = $session->status === SessionStatus::NeedsReview;
            $pendingCount = $statuses->pendingNoShowCount($session);
            $canMarkNoShow = $user->can('markNoShow', $session);
        }

        return view('admin.queue.index', [
            'date' => $date,
            'loadedAt' => $loadedAt,
            'clinicOptions' => Clinic::query()->ordered()->pluck('name', 'slug')->all(),
            'doctorOptions' => Doctor::query()->ordered()->get()
                ->mapWithKeys(fn (Doctor $doctor): array => [$doctor->slug => $doctor->display_name])
                ->all(),
            'clinicSlug' => $clinicSlug,
            'doctorSlug' => $doctorSlug,
            'sessions' => $sessions,
            'session' => $session,
            'appointments' => $appointments,
            'checks' => $checks,
            'statusOrder' => self::STATUS_ORDER,
            'statusCounts' => $appointments->countBy(fn (Appointment $appointment): string => $appointment->status->value)->all(),
            'scan' => $scan,
            'codeInput' => (string) ($request->bookingCode() ?? ''),
            'highlightId' => $found !== null ? (int) $found->id : null,
            'returnUrl' => $returnUrl,
            'ended' => $ended,
            'underReview' => $underReview,
            'pendingCount' => $pendingCount,
            'showBanner' => $ended && ! $underReview && $pendingCount > 0,
            'awaiting' => $appointments
                ->filter(fn (Appointment $appointment): bool => in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true))
                ->values(),
            'canManage' => $canManage,
            'canMarkNoShow' => $canMarkNoShow,
        ]);
    }

    /**
     * Tandai tidak hadir semua `pending`/`confirmed` sesi (V-16). Galat aturan (`SESSION_NOT_ENDED`,
     * `SESSION_UNDER_REVIEW`, `SYSTEM_BUSY`) dilempar service dan ditangani handler global + `BookingRedirect`
     * (antrean sesi yang sama dengan flash `error`); tidak ada yang ditulis bila gagal sebelum loop.
     */
    public function markNoShow(MarkNoShowRequest $request, PracticeSession $session, AppointmentStatusService $statuses): RedirectResponse
    {
        $this->authorize('markNoShow', $session);

        $count = $statuses->markNoShowForSession($session, Actor::staff($request->user()), $request->reason());

        $target = redirect()->to(route('admin.queue.index', ['tanggal' => $session->session_date->toDateString(), 'sesi' => $session->id]));

        if ($count === 0) {
            return $target->with('warning', __('booking.no_show.nothing'));
        }

        return $target->with('success', __('booking.no_show.done', ['count' => $count]));
    }

    /**
     * Pemindaian kode (F7A-R23, BR-69): hanya menemukan, tidak menulis. Mengembalikan pesan untuk ditampilkan dan, bila
     * kode milik tanggal yang sedang ditampilkan, pendaftarannya (untuk berpindah sesi dan menyorot baris).
     *
     * @return array{0: ?array{type: string, message: string, url: ?string}, 1: ?Appointment}
     */
    private function scan(?string $raw, CarbonImmutable $date): array
    {
        if ($raw === null) {
            return [null, null];
        }

        $code = BookingCodeGenerator::normalize($raw);
        $appointment = BookingCodeGenerator::isValid($code)
            ? Appointment::query()->with('clinic')->where('appointments.booking_code', $code)->first()
            : null;

        if ($appointment === null) {
            return [['type' => 'missing', 'message' => 'Kode booking tidak ditemukan.', 'url' => null], null];
        }

        if ($appointment->session_date->toDateString() !== $date->toDateString()) {
            return [[
                'type' => 'other-date',
                'message' => 'Pendaftaran ini untuk tanggal '.$appointment->session_date->format('d/m/Y').', bukan tanggal yang sedang ditampilkan.',
                'url' => route('admin.queue.index', [
                    'tanggal' => $appointment->session_date->toDateString(),
                    'sesi' => $appointment->practice_session_id,
                    'kode' => $appointment->booking_code,
                ]),
            ], null];
        }

        return [[
            'type' => 'found',
            'message' => 'Kode booking '.$appointment->booking_code.' ditemukan pada antrean '.$appointment->queue_label.'.',
            'url' => null,
        ], $appointment];
    }

    /**
     * Sesi pada satu tanggal (semua status), urut jam mulai, nama poliklinik, lalu id.
     *
     * @return Collection<int, PracticeSession>
     */
    private function sessionsOn(CarbonImmutable $date, ?string $clinicSlug, ?string $doctorSlug): Collection
    {
        return PracticeSession::query()
            ->with(['clinic', 'doctor'])
            ->onDate($date)
            ->when($clinicSlug !== null, fn (Builder $query): Builder => $query->whereIn(
                'practice_sessions.clinic_id',
                Clinic::query()->where('slug', $clinicSlug)->select('id'),
            ))
            ->when($doctorSlug !== null, fn (Builder $query): Builder => $query->whereIn(
                'practice_sessions.doctor_id',
                Doctor::query()->where('slug', $doctorSlug)->select('id'),
            ))
            ->orderBy('practice_sessions.start_time')
            ->orderBy(Clinic::query()->select('clinics.name')->whereColumn('clinics.id', 'practice_sessions.clinic_id'))
            ->orderBy('practice_sessions.id')
            ->get();
    }
}
