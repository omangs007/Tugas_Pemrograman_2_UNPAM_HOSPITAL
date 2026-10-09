<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppointmentIndexRequest;
use App\Http\Requests\Admin\CreateWalkInAppointmentRequest;
use App\Http\Requests\Admin\StoreWalkInAppointmentRequest;
use App\Http\Requests\Admin\TransitionAppointmentRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Booking\AppointmentProofService;
use App\Services\Booking\AppointmentStatusService;
use App\Services\Booking\BookingService;
use App\Services\Patients\PatientSearch;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use App\Support\AdminReturn;
use App\Support\SensitiveInput;
use App\Support\WalkInReturn;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     * Form walk-in bertahap (F7A-R21, W-20): pasien, lalu sesi (`DoctorAvailabilityQuery` mode loket), lalu pembayaran.
     * `sesi` yang valid menurunkan poli, dokter, dan tanggal (menimpa query lain). Bagian 3 hanya untuk sesi yang
     * `isBookableCounter`; aturan lengkap tetap di `precheck()`/`book()`.
     */
    public function create(
        CreateWalkInAppointmentRequest $request,
        PracticeSessionGenerator $generator,
        DoctorAvailabilityQuery $availability,
        SettingsService $settings,
        PatientSearch $search,
    ): View {
        $this->authorize('createCounter', Appointment::class);

        $generator->ensureDailyHorizon();

        $filters = $request->filters();
        $windowDays = max(1, $settings->bookingRules()->windowDays);
        $today = CarbonImmutable::today();

        // Bagian 1: pasien. Profil nonaktif tampil di hasil pencarian tetapi tidak dapat dipilih.
        $profile = null;

        if (isset($filters['pasien'])) {
            $found = PatientProfile::query()->find((int) $filters['pasien']);

            if ($found !== null && $found->is_active) {
                $profile = $found;
            } else {
                session()->now('warning', __('booking.errors.PROFILE_INACTIVE'));
            }
        }

        $term = $filters['cari'] ?? null;
        $results = $term === null
            ? collect()
            : $search->apply(PatientProfile::query(), $term)->orderBy('full_name')->orderBy('id')->limit(10)->get();

        // Bagian 2: sesi. Sesi terpilih menurunkan poli, dokter, dan tanggal.
        $chosen = null;

        if ($profile !== null && isset($filters['sesi'])) {
            $chosen = PracticeSession::query()->with(['clinic', 'doctor', 'exception'])->find((int) $filters['sesi']);
        }

        $clinicSlug = $chosen !== null ? $chosen->clinic->slug : ($filters['poli'] ?? null);
        $doctorSlug = $chosen !== null ? $chosen->doctor->slug : ($filters['dokter'] ?? null);
        $date = $chosen !== null ? $chosen->session_date->toDateString() : ($filters['tanggal'] ?? $today->toDateString());

        $clinic = $clinicSlug === null ? null : Clinic::query()->bookable()->where('slug', $clinicSlug)->first();
        $doctor = $clinic !== null && $doctorSlug !== null
            ? Doctor::query()->active()->inClinic($clinic->id)->where('slug', $doctorSlug)->first()
            : null;

        $items = collect();

        if ($profile !== null && $clinic !== null && $doctor !== null) {
            $items = $availability->sessions(new AvailabilityFilter(
                clinicId: $clinic->id,
                doctorId: $doctor->id,
                date: CarbonImmutable::createFromFormat('!Y-m-d', $date) ?: $today,
                forLoket: true,
            ));
        }

        // Bagian 3: hanya untuk sesi yang dapat didaftarkan loket.
        $chosenItem = null;

        if ($chosen !== null) {
            $item = $availability->forSession($chosen, true);

            if ($item->isBookableCounter) {
                $chosenItem = $item;
            } else {
                session()->now('warning', 'Sesi yang dipilih tidak dapat didaftarkan: '.$item->availability->label().'.');
            }
        }

        return view('admin.appointments.create', [
            'profile' => $profile,
            'term' => $term,
            'results' => $results,
            'clinicOptions' => Clinic::query()->bookable()->ordered()->pluck('name', 'slug')->all(),
            'doctorOptions' => $clinic === null
                ? []
                : Doctor::query()->active()->inClinic($clinic->id)->ordered()->get()
                    ->mapWithKeys(fn (Doctor $item): array => [$item->slug => $item->display_name])
                    ->all(),
            'clinic' => $clinic,
            'doctor' => $doctor,
            'date' => $date,
            'minDate' => $today->toDateString(),
            'maxDate' => $today->addDays($windowDays)->toDateString(),
            'windowDays' => $windowDays,
            'items' => $items,
            'chosenItem' => $chosenItem,
            'partners' => InsurancePartner::query()->active()->ordered()->get(['id', 'name']),
            'storedBpjs' => $profile?->masked_bpjs,
            'kembali' => $filters['kembali'] ?? null,
            'backUrl' => AdminReturn::path($filters['kembali'] ?? null, route('admin.appointments.index')),
            'returnPath' => route('admin.appointments.create', $filters, false),
        ]);
    }

    /**
     * Simpan walk-in (V-14, F7A-R21): kunci kirim-ganda per petugas di controller (bukan service, supaya tes konkurensi
     * tetap menguji lapis DB), lalu `BookingService::book()` dengan aktor petugas (kanal loket, status `confirmed`).
     * Penolakan aturan dari `book()` ditangani handler `BookingException` global dan `BookingRedirect`.
     */
    public function store(StoreWalkInAppointmentRequest $request, BookingService $booking): RedirectResponse
    {
        $user = $request->user();
        $profile = PatientProfile::query()->findOrFail((int) $request->validated('patient_profile_id'));
        $sessionId = (int) $request->validated('practice_session_id');

        $lock = Cache::lock('walkin-submit:'.$user->getKey(), 10);

        if (! $lock->get()) {
            return redirect()->to(WalkInReturn::url($profile->id, $sessionId, true))
                ->withInput($request->except(SensitiveInput::FIELDS))
                ->with('error', __('booking.submit_in_progress'));
        }

        try {
            $appointment = $booking->book($profile, $sessionId, $request->toBookingData(), Actor::staff($user));
        } finally {
            $lock->release();
        }

        return redirect()->route('admin.appointments.show', ['appointment' => $appointment->booking_code])
            ->with('success', __('booking.admin.walk_in_created', [
                'code' => $appointment->booking_code,
                'queue' => $appointment->queue_label,
            ]));
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
