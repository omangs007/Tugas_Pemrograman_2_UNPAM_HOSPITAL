<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Halaman publik galeri (#20 `galleries.index`, detail `galleries.show`). Hanya album terbit; album draf menjawab
 * 404 untuk semua orang, termasuk staf (tanpa Policy). Sampul album = foto pertama (`sort_order`, `id`) (F4A-R23).
 */
class GalleryController extends Controller
{
    /** Jumlah album per halaman (F4A-R13). */
    public const PER_PAGE = 12;

    public function index(): View
    {
        $galleries = Gallery::query()
            ->published()
            ->with(['media' => fn ($query) => $query->where('collection', 'photos')])
            ->ordered()
            ->paginate(self::PER_PAGE);

        $page = Page::forKey('galleries-index');
        $title = $page->seo_title;

        if ($galleries->currentPage() > 1) {
            $title .= ' — Halaman '.$galleries->currentPage();
        }

        return view('public.galleries.index', [
            'page' => $page,
            'title' => $title,
            'galleries' => $galleries,
        ]);
    }

    public function show(Gallery $gallery): View
    {
        // Tanpa Policy: album yang belum terbit = 404 untuk semua orang, termasuk staf.
        abort_unless($gallery->is_published, 404);

        $gallery->load(['media' => fn ($query) => $query->where('collection', 'photos')]);

        // Deskripsi SEO: deskripsi album (dipotong 160), lalu deskripsi halaman `galleries-index` (F4A-R5).
        $description = Str::limit(trim((string) $gallery->description), 160, '');

        return view('public.galleries.show', [
            'gallery' => $gallery,
            'photos' => $gallery->mediaIn('photos'),
            'title' => $gallery->title.' — Galeri',
            'metaDescription' => $description !== '' ? $description : Page::forKey('galleries-index')->seo_description,
        ]);
    }
}
