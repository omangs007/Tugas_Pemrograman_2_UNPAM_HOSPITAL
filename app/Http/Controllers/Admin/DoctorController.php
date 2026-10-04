<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorIndexRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\View\View;

class DoctorController extends Controller
{
    public function index(DoctorIndexRequest $request): View
    {
        $this->authorize('viewAny', Doctor::class);

        $doctors = $request
            ->applyTo(Doctor::query()->with(['clinic', 'specialties', 'media']))
            ->paginate($request->perPage());

        return view('admin.doctors.index', [
            'doctors' => $doctors,
            'statusFilter' => $request->statusFilter(),
            'clinicOptions' => Clinic::query()->ordered()->pluck('name', 'slug')->all(),
            'specialtyOptions' => Specialty::query()->ordered()->pluck('name', 'slug')->all(),
        ]);
    }

    public function show(Doctor $doctor): View
    {
        $this->authorize('view', $doctor);

        return view('admin.doctors.show', [
            'doctor' => $doctor->load(['clinic', 'specialties', 'media']),
        ]);
    }
}
