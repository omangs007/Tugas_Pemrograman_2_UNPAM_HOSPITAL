<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryIndexRequest;
use App\Http\Requests\Admin\GalleryRequest;
use App\Http\Requests\Admin\StoreGalleryPhotosRequest;
use App\Models\Gallery;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin #39 tab Galeri (F-ADM-21). Album dibuat tanpa foto lalu dialihkan ke halaman ubah agar foto langsung
 * diunggah (F4A-R23, menyimpang dari D-47). Alt/keterangan, urutan, dan hapus foto memakai `admin.media.*`.
 */
class GalleryController extends Controller
{
    public function index(GalleryIndexRequest $request): View
    {
        $this->authorize('viewAny', Gallery::class);

        $galleries = $request
            ->applyTo(Gallery::query()
                ->with('media')
                ->withCount(['media as photos_count' => fn ($query) => $query->where('collection', 'photos')]))
            ->paginate($request->perPage());

        return view('admin.galleries.index', ['galleries' => $galleries]);
    }

    public function create(): View
    {
        $this->authorize('create', Gallery::class);

        return view('admin.galleries.create');
    }

    public function store(GalleryRequest $request): RedirectResponse
    {
        $this->authorize('create', Gallery::class);

        $gallery = DB::transaction(fn (): Gallery => Gallery::query()->create($request->galleryAttributes()));

        return redirect()->route('admin.galleries.edit', $gallery)
            ->with('success', __('messages.created', ['entity' => __('messages.entities.gallery')]));
    }

    public function edit(Gallery $gallery): View
    {
        $this->authorize('update', $gallery);

        return view('admin.galleries.edit', ['gallery' => $gallery->load('media')]);
    }

    public function update(GalleryRequest $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('update', $gallery);

        DB::transaction(fn (): bool => $gallery->update($request->galleryAttributes()));

        return redirect()->route('admin.galleries.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.gallery')]));
    }

    /**
     * Hapus permanen; seluruh foto dan berkasnya ikut terhapus lewat trait HasMedia (BR-133, CRD-13).
     * Transaksi wajib agar berkas baru dihapus setelah commit.
     */
    public function destroy(Gallery $gallery): RedirectResponse
    {
        $this->authorize('delete', $gallery);

        DB::transaction(fn (): ?bool => $gallery->delete());

        return redirect()->route('admin.galleries.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.gallery')]));
    }

    /**
     * Unggah sampai 10 foto sekali kirim; `sort_order` dilanjutkan oleh MediaService (maksimum koleksi + 1).
     */
    public function storePhotos(StoreGalleryPhotosRequest $request, Gallery $gallery, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $gallery);

        $user = $request->user();
        $files = $request->photoFiles();

        DB::transaction(function () use ($request, $media, $gallery, $user, $files): void {
            foreach ($files as $index => $file) {
                $media->store($file, 'photos', $gallery, [
                    'alt_text' => $request->altText($index),
                    'caption' => $request->caption($index),
                ], $user);
            }
        });

        return redirect()->route('admin.galleries.edit', $gallery)
            ->with('success', count($files).' foto berhasil ditambahkan ke album.');
    }
}
