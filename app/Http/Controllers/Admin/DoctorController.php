<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DoctorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchiveDoctorRequest;
use App\Http\Requests\Admin\DoctorIndexRequest;
use App\Http\Requests\Admin\StoreDoctorRequest;
use App\Http\Requests\Admin\UpdateDoctorRequest;
use App\Http\Requests\Admin\UpdateDoctorStatusRequest;
use App\Http\Requests\Admin\UploadDoctorPhotoRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\Doctors\DoctorService;
use App\Support\Actor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
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

    public function create(): View
    {
        $this->authorize('create', Doctor::class);

        return view('admin.doctors.create', $this->formOptions(null));
    }

    public function store(StoreDoctorRequest $request, DoctorService $service): RedirectResponse
    {
        $this->authorize('create', Doctor::class);

        $service->create($request->doctorData(), Actor::staff($request->user()), $request->photoUpload(), $request->photoAlt());

        return redirect()->route('admin.doctors.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.doctor')]));
    }

    public function edit(Doctor $doctor): View
    {
        $this->authorize('update', $doctor);

        $doctor->load(['clinic', 'specialties', 'media']);

        return view('admin.doctors.edit', ['doctor' => $doctor] + $this->formOptions($doctor));
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('update', $doctor);

        $service->update($doctor, $request->doctorData(), Actor::staff($request->user()), $request->photoUpload(), $request->photoAlt());

        return redirect()->route('admin.doctors.show', $doctor)
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.doctor')]));
    }

    public function status(UpdateDoctorStatusRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('changeStatus', $doctor);

        $restoring = $doctor->status === DoctorStatus::Archived;
        $target = $request->targetStatus();

        $service->changeStatus($doctor, $target, $request->reason(), Actor::staff($request->user()));

        $message = match (true) {
            $restoring => 'Dokter dipulihkan sebagai nonaktif.',
            $target === DoctorStatus::Active => 'Dokter diaktifkan.',
            default => 'Dokter dinonaktifkan.',
        };

        return back(fallback: route('admin.doctors.show', $doctor))->with('success', $message);
    }

    public function archive(ArchiveDoctorRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('archive', $doctor);

        if ($doctor->status === DoctorStatus::Archived) {
            return back(fallback: route('admin.doctors.show', $doctor))->with('error', 'Dokter sudah diarsipkan.');
        }

        $service->archive($doctor, $request->reason(), Actor::staff($request->user()));

        return back(fallback: route('admin.doctors.show', $doctor))->with('success', 'Dokter diarsipkan.');
    }

    public function photo(UploadDoctorPhotoRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('update', $doctor);

        $service->replacePhoto($doctor, $request->photoFile(), $request->altText(), Actor::staff($request->user()));

        return redirect()->route('admin.doctors.show', $doctor)->with('success', 'Foto dokter berhasil diperbarui.');
    }

    /**
     * Pilihan form: poliklinik dan spesialisasi aktif, ditambah yang kini dimiliki dokter ini.
     *
     * @return array{clinics: array<int, string>, specialties: Collection<int, Specialty>}
     */
    private function formOptions(?Doctor $doctor): array
    {
        $clinics = Clinic::query()
            ->where(function ($query) use ($doctor): void {
                $query->where('is_active', true);

                if ($doctor !== null) {
                    $query->orWhere('id', $doctor->clinic_id);
                }
            })
            ->ordered()
            ->pluck('name', 'id')
            ->all();

        $specialties = Specialty::query()
            ->where(function ($query) use ($doctor): void {
                $query->where('is_active', true);

                if ($doctor !== null) {
                    $query->orWhereIn('id', $doctor->specialties->pluck('id')->all());
                }
            })
            ->ordered()
            ->get();

        return ['clinics' => $clinics, 'specialties' => $specialties];
    }
}
