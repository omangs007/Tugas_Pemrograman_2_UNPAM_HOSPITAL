<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClinicRequest;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Models\Clinic;
use App\Models\Specialty;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', Clinic::class);

        $clinics = $request
            ->applyTo(Clinic::query()->with(['specialty', 'media'])->withCount('doctors'), ['name', 'code'])
            ->paginate($request->perPage());

        return view('admin.clinics.index', ['clinics' => $clinics]);
    }

    public function create(): View
    {
        $this->authorize('create', Clinic::class);

        return view('admin.clinics.create', ['specialties' => $this->specialtyOptions()]);
    }

    public function store(ClinicRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', Clinic::class);

        DB::transaction(function () use ($request, $media): void {
            $clinic = Clinic::query()->create($request->clinicAttributes());
            $this->saveMedia($request, $media, $clinic);
        });

        return redirect()->route('admin.clinics.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.clinic')]));
    }

    public function edit(Clinic $clinic): View
    {
        $this->authorize('update', $clinic);

        return view('admin.clinics.edit', [
            'clinic' => $clinic->load('media'),
            'specialties' => $this->specialtyOptions(),
        ]);
    }

    public function update(ClinicRequest $request, Clinic $clinic, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $clinic);

        DB::transaction(function () use ($request, $media, $clinic): void {
            $clinic->update($request->clinicAttributes());
            $this->saveMedia($request, $media, $clinic);
        });

        return redirect()->route('admin.clinics.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.clinic')]));
    }

    /**
     * Penolakan di controller (bukan Policy) supaya berlaku juga untuk Super Admin (F2-R18).
     * Media ikut terhapus lewat trait HasMedia; transaksi wajib agar berkas dihapus setelah commit.
     */
    public function destroy(Clinic $clinic): RedirectResponse
    {
        $this->authorize('delete', $clinic);

        $reason = $clinic->deletionBlockedReason();

        if ($reason !== null) {
            return redirect()->route('admin.clinics.index')->with('error', __('messages.delete_blocked', ['reason' => $reason]));
        }

        DB::transaction(fn (): ?bool => $clinic->delete());

        return redirect()->route('admin.clinics.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.clinic')]));
    }

    private function saveMedia(ClinicRequest $request, MediaService $media, Clinic $clinic): void
    {
        $user = $request->user();
        $cover = $request->coverFile();

        if ($cover !== null) {
            $media->replace($clinic, 'cover', $cover, ['alt_text' => $request->coverAlt()], $user);
        }

        foreach ($request->galleryFiles() as $index => $file) {
            $media->store($file, 'gallery', $clinic, ['alt_text' => $request->galleryAlt($index)], $user);
        }
    }

    /**
     * @return array<int, string>
     */
    private function specialtyOptions(): array
    {
        return Specialty::query()->ordered()->get()
            ->mapWithKeys(fn (Specialty $specialty): array => [
                $specialty->id => $specialty->name.($specialty->is_active ? '' : ' (nonaktif)'),
            ])
            ->all();
    }
}
