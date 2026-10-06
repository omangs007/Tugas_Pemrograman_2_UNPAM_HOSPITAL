<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerIndexRequest;
use App\Http\Requests\Admin\BannerRequest;
use App\Models\Banner;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman #39, tab Banner (F-ADM-19). Hapus permanen: baris `media` dan berkasnya ikut dibersihkan oleh
 * trait HasMedia; transaksi wajib agar berkas dihapus setelah commit.
 */
class BannerController extends Controller
{
    public function index(BannerIndexRequest $request): View
    {
        $this->authorize('viewAny', Banner::class);

        $banners = $request
            ->applyTo(Banner::query()->with('media'))
            ->paginate($request->perPage());

        return view('admin.banners.index', ['banners' => $banners]);
    }

    public function create(): View
    {
        $this->authorize('create', Banner::class);

        return view('admin.banners.create');
    }

    public function store(BannerRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', Banner::class);

        DB::transaction(function () use ($request, $media): void {
            $banner = Banner::query()->create($request->bannerAttributes());
            $this->saveImage($request, $media, $banner);
        });

        return redirect()->route('admin.banners.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.banner')]));
    }

    public function edit(Banner $banner): View
    {
        $this->authorize('update', $banner);

        return view('admin.banners.edit', ['banner' => $banner->load('media')]);
    }

    public function update(BannerRequest $request, Banner $banner, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $banner);

        DB::transaction(function () use ($request, $media, $banner): void {
            $banner->update($request->bannerAttributes());
            $this->saveImage($request, $media, $banner);
        });

        return redirect()->route('admin.banners.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.banner')]));
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->authorize('delete', $banner);

        DB::transaction(fn (): ?bool => $banner->delete());

        return redirect()->route('admin.banners.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.banner')]));
    }

    private function saveImage(BannerRequest $request, MediaService $media, Banner $banner): void
    {
        $file = $request->imageFile();

        if ($file !== null) {
            $media->replace($banner, 'image', $file, ['alt_text' => $request->imageAlt()], $request->user());

            return;
        }

        if ($request->has('image_alt')) {
            // Tanpa berkas baru: alt yang diedit diterapkan ke gambar yang ada (caption tidak diubah).
            $existing = $banner->media()->where('collection', 'image')->first();

            if ($existing !== null && $existing->alt_text !== $request->imageAlt()) {
                $media->updateMeta($existing, $request->imageAlt(), $existing->caption);
            }
        }
    }
}
