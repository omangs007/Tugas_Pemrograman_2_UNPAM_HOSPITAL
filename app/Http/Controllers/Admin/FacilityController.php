<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FacilityIndexRequest;
use App\Http\Requests\Admin\FacilityRequest;
use App\Models\Facility;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin #36 tab Fasilitas (F-ADM-09). Pola poliklinik fase 2: `cover` tunggal dan `gallery` banyak lewat MediaService.
 * Fasilitas tidak dirujuk tabel lain, jadi hapus selalu diizinkan dan media ikut terhapus (CRD-13, F4B-R20).
 */
class FacilityController extends Controller
{
    public function index(FacilityIndexRequest $request): View
    {
        $this->authorize('viewAny', Facility::class);

        $facilities = $request
            ->applyTo(Facility::query()->with('media'))
            ->paginate($request->perPage());

        return view('admin.facilities.index', ['facilities' => $facilities]);
    }

    public function create(): View
    {
        $this->authorize('create', Facility::class);

        return view('admin.facilities.create');
    }

    public function store(FacilityRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', Facility::class);

        DB::transaction(function () use ($request, $media): void {
            $facility = Facility::query()->create($request->facilityAttributes());
            $this->saveMedia($request, $media, $facility);
        });

        return redirect()->route('admin.facilities.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.facility')]));
    }

    public function edit(Facility $facility): View
    {
        $this->authorize('update', $facility);

        return view('admin.facilities.edit', ['facility' => $facility->load('media')]);
    }

    public function update(FacilityRequest $request, Facility $facility, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $facility);

        DB::transaction(function () use ($request, $media, $facility): void {
            $facility->update($request->facilityAttributes());
            $this->saveMedia($request, $media, $facility);
        });

        return redirect()->route('admin.facilities.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.facility')]));
    }

    /**
     * Media ikut terhapus lewat trait HasMedia; transaksi wajib agar berkas dihapus setelah commit.
     */
    public function destroy(Facility $facility): RedirectResponse
    {
        $this->authorize('delete', $facility);

        DB::transaction(fn (): ?bool => $facility->delete());

        return redirect()->route('admin.facilities.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.facility')]));
    }

    private function saveMedia(FacilityRequest $request, MediaService $media, Facility $facility): void
    {
        $user = $request->user();
        $cover = $request->coverFile();

        if ($cover !== null) {
            $media->replace($facility, 'cover', $cover, ['alt_text' => $request->coverAlt()], $user);
        } elseif ($request->has('cover_alt')) {
            // Tanpa berkas baru: alt yang diedit diterapkan ke cover yang ada (caption tidak diubah).
            $existing = $facility->media()->where('collection', 'cover')->first();

            if ($existing !== null && $existing->alt_text !== $request->coverAlt()) {
                $media->updateMeta($existing, $request->coverAlt(), $existing->caption);
            }
        }

        foreach ($request->galleryFiles() as $index => $file) {
            $media->store($file, 'gallery', $facility, ['alt_text' => $request->galleryAlt($index)], $user);
        }
    }
}
