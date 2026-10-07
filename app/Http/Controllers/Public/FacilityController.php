<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\FacilityCategory;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Halaman publik fasilitas (#9 `facilities.index`, #10 `facilities.show`). Hanya fasilitas aktif; yang nonaktif
 * menjawab 404 untuk semua orang, termasuk staf (tanpa Policy, A-42). Daftar dikelompokkan per kategori dalam
 * urutan `FacilityCategory::cases()`; kategori tanpa fasilitas dilewati; tanpa paginasi (F4B-R29).
 */
class FacilityController extends Controller
{
    public function index(): View
    {
        $facilities = Facility::query()
            ->active()
            ->with(['media' => fn ($query) => $query->where('collection', 'cover')])
            ->ordered()
            ->get();

        /** @var Collection<int, array{0: FacilityCategory, 1: Collection<int, Facility>}> $groups */
        $groups = collect(FacilityCategory::cases())
            ->map(fn (FacilityCategory $category): array => [
                $category,
                $facilities->filter(fn (Facility $facility): bool => $facility->category === $category)->values(),
            ])
            ->filter(fn (array $group): bool => $group[1]->isNotEmpty())
            ->values();

        return view('public.facilities.index', [
            'page' => Page::forKey('facilities-index'),
            'groups' => $groups,
        ]);
    }

    public function show(Facility $facility): View
    {
        // Tanpa Policy: nonaktif = 404 untuk semua orang, termasuk staf (A-42).
        abort_unless($facility->is_active, 404);

        $facility->load('media');

        return view('public.facilities.show', [
            'facility' => $facility,
            'gallery' => $facility->mediaIn('gallery'),
            // Deskripsi SEO: `meta_description`, lalu `short_description`; dipotong 160 karakter (F4B-R29).
            'metaDescription' => Str::limit(trim((string) $facility->seo_description), 160, ''),
        ]);
    }
}
