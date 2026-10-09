<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppointmentIndexRequest;
use App\Http\Requests\Admin\TransitionAppointmentRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Services\Booking\AppointmentProofService;
use App\Services\Booking\AppointmentStatusService;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use App\Support\AdminReturn;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pendaftaran kunjungan #38 sisi petugas (F7A-R17, R18, R22): daftar, detail, transisi status per pendaftaran, dan bukti
 * cetak. Tulis status hanya lewat AppointmentStatusService (otorisasi izin di route dan Form Request; service tidak
 * memeriksa izin). T9 menambah form walk-in (`create`/`store`).
 */
class AppointmentController extends Controller
{
    public function index(AppointmentIndexRequest $request): View
    {
        $this->authorize('viewAny', Appointment::class);

        $appointments = $request
            ->applyTo(Appointment::query()->with(['clinic', 'doctor', 'practiceSession', 'patientProfile']))
            ->paginate($request->perPage());

        return view('admin.appointments.index', [
            'appointments' => $appointments,
            'range' => $request->range(),
            'clinicOptions' => Clinic::query()->ordered()->pluck('name', 'slug')->all(),
            'doctorOptions' => Doctor::query()->ordered()->get()
                ->mapWithKeys(fn (Doctor $doctor): array => [$doctor->slug => $doctor->display_name])
                ->all(),
            'statusOptions' => AppointmentStatus::options(),
            'channelOptions' => BookingChannel::options(),
        ]);
    }

    public function show(Request $request, Appointment $appointment, AppointmentStatusService $statuses): View
    {
        $this->authorize('view', $appointment);

        $appointment->load(['patientProfile', 'clinic', 'doctor', 'practiceSession', 'insurancePartner', 'creator', 'statusHistories.actor']);

        $user = $request->user();
        $checks = $user->can('transition', $appointment)
            ? $statuses->transitionChecks($appointment, Actor::staff($user))
            : [];
        $returnTo = $this->returnTo($request->query('kembali'));

        return view('admin.appointments.show', [
            'appointment' => $appointment,
            'checks' => $checks,
            'returnTo' => $returnTo,
            'backUrl' => $returnTo ?? route('admin.appointments.index'),
        ]);
    }

    /**
     * Satu transisi (V-15). Galat aturan (`InvalidTransitionException`) ditangani handler global dan
     * `BookingRedirect` (kembali ke `kembali` atau detail dengan flash `error`); tidak ada yang ditulis bila gagal.
     */
    public function transition(TransitionAppointmentRequest $request, Appointment $appointment, AppointmentStatusService $statuses): RedirectResponse
    {
        $from = $appointment->status;
        $to = $request->target();

        $updated = $statuses->transition(
            $appointment,
            $to,
            Actor::staff($request->user()),
            $request->reason(),
            $request->expectedStatus(),
        );
        $updated->loadMissing('clinic');

        return redirect()
            ->to(AdminReturn::path($request->returnTo(), route('admin.appointments.show', ['appointment' => $updated->booking_code])))
            ->with('success', $this->successMessage($from, $to, $updated));
    }

    /** Bukti cetak (F7A-R22): isi bukti sama dengan sisi pasien, tanpa PDF. */
    public function proof(Appointment $appointment, AppointmentProofService $proof, SettingsService $settings): View
    {
        $this->authorize('view', $appointment);

        $appointment->loadMissing(['clinic', 'doctor', 'practiceSession', 'patientProfile', 'insurancePartner']);

        return view('admin.appointments.proof', [
            'appointment' => $appointment,
            'qrDataUri' => $proof->qrSvgDataUri($appointment),
            'siteName' => $settings->string('site.name', (string) config('app.name')),
            'logoUrl' => $settings->logoUrl(),
            'checkinMinutes' => $settings->bookingRules()->checkinOpenMinutes,
            'printedAt' => CarbonImmutable::now(),
        ]);
    }

    private function successMessage(AppointmentStatus $from, AppointmentStatus $to, Appointment $appointment): string
    {
        $replace = ['code' => $appointment->booking_code, 'queue' => $appointment->queue_label];

        return match (true) {
            $to === AppointmentStatus::Confirmed && $from === AppointmentStatus::CheckedIn => __('booking.admin.corrected', $replace),
            $to === AppointmentStatus::Confirmed => __('booking.admin.confirmed', $replace),
            $to === AppointmentStatus::CheckedIn => __('booking.admin.checked_in', $replace),
            $to === AppointmentStatus::Completed => __('booking.admin.completed', $replace),
            default => __('booking.admin.cancelled', $replace),
        };
    }

    /** `kembali` query yang sah (detail atau antrean), selain itu null. */
    private function returnTo(mixed $value): ?string
    {
        $path = AdminReturn::path(is_string($value) ? $value : null, '');

        return $path === '' ? null : $path;
    }
}
