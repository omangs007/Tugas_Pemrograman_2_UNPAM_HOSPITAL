<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use Illuminate\View\View;

/**
 * Halaman #21 `faq`: FAQ terbit dikelompokkan per kategori dalam urutan `Faq::CATEGORIES`; kategori tanpa FAQ terbit
 * dilewati (F4A-R22). Tanpa paginasi.
 */
class FaqController extends Controller
{
    public function index(): View
    {
        $byCategory = Faq::query()->published()->ordered()->get()->groupBy('category');

        $groups = collect(Faq::CATEGORIES)
            ->filter(fn (string $category): bool => $byCategory->has($category))
            ->mapWithKeys(fn (string $category): array => [$category => $byCategory->get($category)]);

        return view('public.faq', [
            'page' => Page::forKey('faq'),
            'groups' => $groups,
        ]);
    }
}
