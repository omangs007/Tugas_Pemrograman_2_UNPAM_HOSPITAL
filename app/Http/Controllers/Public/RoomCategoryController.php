<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\RoomCategory;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Halaman publik kamar rawat inap (#11 `rooms.index`, #12-#15 `rooms.show`). Hanya kategori aktif; yang nonaktif
 * menjawab 404 untuk semua orang, termasuk staf (tanpa Policy, A-42). Satu route dan satu view melayani keempat
 * slug seed (F4B-R30). Tanpa paginasi: kategori biasanya tidak lebih dari sepuluh.
 */
class RoomCategoryController extends Controller
{
    public function index(): View
    {
        $rooms = RoomCategory::query()
            ->active()
            ->with(['media' => fn ($query) => $query->where('collection', 'cover')])
            ->ordered()
            ->get();

        return view('public.rooms.index', [
            'page' => Page::forKey('rooms-index'),
            'rooms' => $rooms,
        ]);
    }

    public function show(RoomCategory $roomCategory): View
    {
        // Tanpa Policy: nonaktif = 404 untuk semua orang, termasuk staf (A-42).
        abort_unless($roomCategory->is_active, 404);

        $roomCategory->load('media');

        return view('public.rooms.show', [
            'room' => $roomCategory,
            'gallery' => $roomCategory->mediaIn('gallery'),
            // Deskripsi SEO: `meta_description`, lalu `short_description`; dipotong 160 karakter (F4B-R30).
            'metaDescription' => Str::limit(trim((string) $roomCategory->seo_description), 160, ''),
        ]);
    }
}
