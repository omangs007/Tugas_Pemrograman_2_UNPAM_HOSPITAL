<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArticleCategoryRequest;
use App\Http\Requests\ListFilterRequest;
use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman #39, tab Kategori Artikel (F-ADM-20). Hapus permanen: artikel di kategori itu menjadi tanpa kategori
 * lewat FK `nullOnDelete`, sehingga tidak ada aturan rujukan yang menolak hapus.
 */
class ArticleCategoryController extends Controller
{
    public function index(ListFilterRequest $request): View
    {
        $this->authorize('viewAny', ArticleCategory::class);

        $term = $request->filters()['q'] ?? null;

        $categories = ArticleCategory::query()
            ->withCount('articles')
            ->when($term !== null, fn (Builder $query): Builder => $query->where('name', 'like', $this->like($term)))
            ->ordered()
            ->paginate($request->perPage());

        return view('admin.article-categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        $this->authorize('create', ArticleCategory::class);

        return view('admin.article-categories.create');
    }

    public function store(ArticleCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ArticleCategory::class);

        DB::transaction(fn (): ArticleCategory => ArticleCategory::query()->create($request->categoryAttributes()));

        return redirect()->route('admin.article-categories.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.article_category')]));
    }

    public function edit(ArticleCategory $articleCategory): View
    {
        $this->authorize('update', $articleCategory);

        return view('admin.article-categories.edit', ['category' => $articleCategory]);
    }

    public function update(ArticleCategoryRequest $request, ArticleCategory $articleCategory): RedirectResponse
    {
        $this->authorize('update', $articleCategory);

        DB::transaction(fn (): bool => $articleCategory->update($request->categoryAttributes()));

        return redirect()->route('admin.article-categories.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.article_category')]));
    }

    public function destroy(ArticleCategory $articleCategory): RedirectResponse
    {
        $this->authorize('delete', $articleCategory);

        DB::transaction(fn (): ?bool => $articleCategory->delete());

        return redirect()->route('admin.article-categories.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.article_category')]));
    }

    /**
     * Pola LIKE dengan karakter `\`, `%`, dan `_` di-escape agar kata kunci diperlakukan sebagai teks biasa.
     */
    private function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
