<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PracticeSessionIndexRequest;
use App\Http\Requests\Admin\PracticeSessionRequest;
use App\Http\Requests\Admin\UpdateSessionStatusRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman #35, tab Sesi per Tanggal (F3-R19/R20/R22/R27/R30). Semua tulis lewat ScheduleChangeService.
 *
 * Admin Konten tidak pernah menerima data pasien (F3-R30, 04 §6 V1): daftar hanya membaca `booked_count`/`quota`
 * dan jumlah appointment (`withCount`); tidak ada relasi pasien yang dimuat.
 */
class PracticeSessionController extends Controller
{
    private const PAST_MESSAGE = 'Sesi yang sudah lewat tidak dapat diubah.';

    public function index(PracticeSessionIndexRequest $request): View
    {
        $this->authorize('viewAny', PracticeSession::class);

        $sessions = $request
            ->applyTo(PracticeSession::query()->with(['doctor', 'clinic'])->withCount('appointments'))
            ->paginate($request->perPage());

        return view('admin.sessions.index', [
            'sessions' => $sessions,
            'range' => $request->range(),
            'doctorOptions' => Doctor::query()->ordered()->get()
                ->mapWithKeys(fn (Doctor $doctor): array => [$doctor->slug => $doctor->display_name])
                ->all(),
            'clinicOptions' => Clinic::query()->ordered()->pluck('name', 'slug')->all(),
            'statusOptions' => SessionStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PracticeSession::class);

        return view('admin.sessions.create', $this->formOptions());
    }

    public function store(PracticeSessionRequest $request, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('create', PracticeSession::class);

        $session = $service->createManualSession($request->sessionAttributes(), Actor::staff($request->user()));
        $date = $session->session_date->toDateString();

        // Daftar dibuka pada tanggal sesi baru supaya hasilnya langsung terlihat.
        return redirect()->route('admin.sessions.index', ['dari' => $date, 'sampai' => $date])
            ->with('success', __('messages.created', ['entity' => __('messages.entities.session')]));
    }

    public function edit(PracticeSession $session): View|RedirectResponse
    {
        $this->authorize('update', $session);

        if ($session->isEnded()) {
            return redirect()->route('admin.sessions.index')->with('error', self::PAST_MESSAGE);
        }

        return view('admin.sessions.edit', ['session' => $session->load(['doctor', 'clinic'])] + $this->formOptions());
    }

    public function update(PracticeSessionRequest $request, PracticeSession $session, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('update', $session);

        $updated = $service->updateSession($session, $request->sessionAttributes(), Actor::staff($request->user()));

        $redirect = redirect()->route('admin.sessions.index', [
            'dari' => $updated->session_date->toDateString(),
            'sampai' => $updated->session_date->toDateString(),
        ])->with('success', __('messages.updated', ['entity' => __('messages.entities.session')]));

        if ($updated->status === SessionStatus::NeedsReview && $updated->pendingSource() === PracticeSession::SOURCE_MANUAL_TIME) {
            $redirect->with('warning', $this->pendingMessage($updated));
        }

        return $redirect;
    }

    public function status(UpdateSessionStatusRequest $request, PracticeSession $session, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('changeStatus', $session);

        $target = $request->targetStatus();

        try {
            $updated = $service->setSessionStatus($session, $target, Actor::staff($request->user()), $request->reason());
        } catch (ValidationException $exception) {
            // Modal ditutup setelah kirim: galat service (kunci `status`/`reason`/`session`) juga dipasang sebagai
            // flash `error` agar terlihat di halaman asal; galatnya tetap dilempar ulang (kembali dengan $errors).
            session()->flash('error', (string) collect($exception->errors())->flatten()->first());

            throw $exception;
        }

        $redirect = back(fallback: route('admin.sessions.index'));

        if ($target === SessionStatus::Cancelled && $updated->status === SessionStatus::NeedsReview) {
            return $redirect->with('warning', $this->pendingMessage($updated));
        }

        return $redirect->with('success', match ($target) {
            SessionStatus::Closed => 'Sesi ditutup untuk pendaftaran baru.',
            SessionStatus::Open => 'Sesi dibuka kembali.',
            default => 'Sesi dibatalkan.',
        });
    }

    /**
     * Penolakan datang dari service (F3-R20, F3-R27) sebagai ValidationException, bukan Policy, sehingga berlaku
     * juga untuk Super Admin. Pesannya ditampilkan sebagai flash `error`.
     */
    public function destroy(Request $request, PracticeSession $session, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('delete', $session);

        $redirect = back(fallback: route('admin.sessions.index'));

        if ($session->isEnded()) {
            return $redirect->with('error', self::PAST_MESSAGE);
        }

        try {
            $service->deleteManualSession($session, Actor::staff($request->user()));
        } catch (ValidationException $exception) {
            return $redirect->with('error', __('messages.delete_blocked', ['reason' => (string) collect($exception->errors())->flatten()->first()]));
        }

        return $redirect->with('success', __('messages.deleted', ['entity' => __('messages.entities.session')]));
    }

    /** BR-35: perubahan jam atau pembatalan sesi ber-appointment aktif dikirim ke layar Sesi terdampak. */
    private function pendingMessage(PracticeSession $session): string
    {
        $count = $session->appointments()->active()->count();

        return "Sesi memiliki {$count} pendaftaran aktif. Perubahan dikirim ke layar Sesi terdampak untuk diselesaikan petugas pendaftaran.";
    }

    /**
     * Pilihan form tambah sesi: dokter aktif dan poliklinik aktif yang dapat didaftar (V-24, F3-R26).
     *
     * @return array{doctorOptions: array<int, string>, clinicOptions: array<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'doctorOptions' => Doctor::query()
                ->where('status', DoctorStatus::Active->value)
                ->ordered()
                ->get()
                ->mapWithKeys(fn (Doctor $doctor): array => [$doctor->id => $doctor->display_name])
                ->all(),
            'clinicOptions' => Clinic::query()->bookable()->ordered()->pluck('name', 'id')->all(),
        ];
    }
}
