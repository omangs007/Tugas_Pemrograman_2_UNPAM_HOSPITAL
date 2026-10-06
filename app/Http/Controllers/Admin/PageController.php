<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman #39, tab Halaman (F4A-R24). Hanya `index`, `edit`, `update`: 15 halaman sistem tidak dapat
 * ditambah atau dihapus (BR-121). Daftar tetap 15 baris tanpa paginasi dan tanpa filter (F4A-R13).
 */
class PageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Page::class);

        $order = array_flip(Page::KEYS);
        $pages = Page::query()
            ->with('updater')
            ->get()
            ->sortBy(fn (Page $page): int => $order[$page->key] ?? PHP_INT_MAX)
            ->values();

        return view('admin.pages.index', ['pages' => $pages]);
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $page);

        return view('admin.pages.edit', ['page' => $page]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        DB::transaction(function () use ($request, $page): void {
            $page->update($request->pageAttributes() + ['updated_by' => $request->user()?->id]);
        });

        return redirect()->route('admin.pages.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.page')]));
    }
}
