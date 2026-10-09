<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PatientIndexRequest;
use App\Http\Requests\Admin\StorePatientRequest;
use App\Http\Requests\Admin\UpdatePatientRequest;
use App\Http\Requests\Admin\UpdatePatientStatusRequest;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Services\Patients\PatientRegistrationService;
use App\Services\Patients\PatientSearch;
use App\Support\Actor;
use App\Support\SafeRedirect;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(PatientIndexRequest $request, PatientSearch $search): View
    {
        $this->authorize('viewAny', PatientProfile::class);

        $patients = $request
            ->applyTo(PatientProfile::query(), $search)
            ->paginate($request->perPage());

        return view('admin.patients.index', [
            'patients' => $patients,
            'kanalFilter' => $request->kanalFilter(),
            'statusFilter' => $request->statusFilter(),
        ]);
    }

    public function show(PatientProfile $patient): View
    {
        $this->authorize('view', $patient);

        $patient->loadMissing(['user', 'creator']);

        $appointments = Appointment::query()
            ->forPatient($patient)
            ->with(['clinic', 'doctor', 'practiceSession'])
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'riwayat');

        return view('admin.patients.show', [
            'patient' => $patient,
            'appointments' => $appointments,
        ]);
    }

    public function create(Request $request, PatientSearch $search): View
    {
        $this->authorize('create', PatientProfile::class);

        $kembali = self::walkInReturn($request->query('kembali'));
        $similar = $this->similarProfiles($request, $search);

        return view('admin.patients.create', [
            'kembali' => $kembali,
            'similar' => $similar,
            'pickUrls' => $similar
                ->mapWithKeys(fn (PatientProfile $candidate): array => [
                    $candidate->id => $kembali === null ? null : url(self::walkInUrl($kembali, (int) $candidate->id)),
                ])
                ->all(),
        ]);
    }

    public function store(StorePatientRequest $request, PatientRegistrationService $service): RedirectResponse
    {
        $this->authorize('create', PatientProfile::class);

        $profile = $service->createWalkIn($request->walkInData(), $request->user());
        $message = __('patients.walk_in.created', ['number' => $profile->patient_number]);

        $kembali = self::walkInReturn($request->input('kembali'));

        if ($kembali !== null) {
            return redirect(self::walkInUrl($kembali, (int) $profile->getKey()))->with('success', $message);
        }

        return redirect()->route('admin.patients.show', $profile)->with('success', $message);
    }

    public function edit(PatientProfile $patient): View
    {
        $this->authorize('update', $patient);

        return view('admin.patients.edit', ['patient' => $patient]);
    }

    public function update(UpdatePatientRequest $request, PatientProfile $patient, PatientRegistrationService $service): RedirectResponse
    {
        $this->authorize('update', $patient);

        $service->updateProfile($patient, $request->profileData(), Actor::staff($request->user()));

        return redirect()->route('admin.patients.show', $patient)
            ->with('success', __('patients.walk_in.updated', ['number' => $patient->patient_number]));
    }

    public function status(UpdatePatientStatusRequest $request, PatientProfile $patient, PatientRegistrationService $service): RedirectResponse
    {
        $this->authorize('changeStatus', $patient);

        $active = $request->isActive();

        try {
            $service->changeStatus($patient, $active, Actor::staff($request->user()), $request->reason());
        } catch (ValidationException $exception) {
            // Modal sudah tertutup: galat service (BR-105) juga dikirim sebagai flash `error`.
            session()->flash('error', (string) collect($exception->errors())->flatten()->first());

            throw $exception;
        }

        return redirect()->route('admin.patients.show', $patient)->with(
            'success',
            __($active ? 'patients.status.activated' : 'patients.status.deactivated', ['number' => $patient->patient_number]),
        );
    }

    /**
     * Profil aktif yang mirip dengan input lama bila submit terakhir ditolak karena `confirm_not_duplicate` (BR-102).
     * Tidak menyimpan apa pun di sesi: dihitung ulang dari old input (nilai sudah dinormalkan Form Request).
     *
     * @return Collection<int, PatientProfile>
     */
    private function similarProfiles(Request $request, PatientSearch $search): Collection
    {
        $errors = $request->session()->get('errors');

        if (! $errors instanceof ViewErrorBag || ! $errors->has('confirm_not_duplicate')) {
            return new Collection;
        }

        $name = $request->old('full_name');
        $birthDate = $request->old('birth_date');
        $phone = $request->old('phone');

        if (! is_string($name) || ! is_string($birthDate) || ! is_string($phone)) {
            return new Collection;
        }

        return $search->similarTo($name, $birthDate, $phone);
    }

    /**
     * Path walk-in yang sah dari `kembali` (F7A-R31): path internal (SafeRedirect) DAN persis `/admin/pendaftaran/buat`,
     * dengan atau tanpa query. Nilai lain (larik, URL eksternal, path lain, awalan palsu) menghasilkan null.
     */
    private static function walkInReturn(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $path = SafeRedirect::internalPath($value);

        if ($path === null) {
            return null;
        }

        $parts = parse_url($path);

        if (! is_array($parts) || ($parts['path'] ?? null) !== '/admin/pendaftaran/buat') {
            return null;
        }

        $query = $parts['query'] ?? '';

        return $query === '' ? $parts['path'] : $parts['path'].'?'.$query;
    }

    /**
     * Path walk-in dengan query `pasien` diganti atau ditambah id profil; query lain dipertahankan. Hanya dipanggil
     * dengan hasil walkInReturn().
     */
    private static function walkInUrl(string $path, int $patientId): string
    {
        $parts = parse_url($path);
        $query = [];

        parse_str(is_array($parts) ? ($parts['query'] ?? '') : '', $query);
        $query['pasien'] = (string) $patientId;

        return '/admin/pendaftaran/buat?'.http_build_query($query);
    }
}
