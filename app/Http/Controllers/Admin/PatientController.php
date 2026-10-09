<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PatientIndexRequest;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Services\Patients\PatientSearch;
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
}
