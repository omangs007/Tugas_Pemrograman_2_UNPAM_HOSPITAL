<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArticleIndexRequest;
use App\Http\Requests\Admin\ArticleRequest;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman #39, tab Artikel (F-ADM-20). Hapus = soft delete: baris dan media dipertahankan, slug tidak dipakai ulang.
 * `author_user_id` diisi pengguna pembuat saat `store` dan tidak berubah saat `update` (F4A-R10).
 */
class ArticleController extends Controller
{
    public function index(ArticleIndexRequest $request): View
    {
        $this->authorize('viewAny', Article::class);

        $articles = $request
            ->applyTo(Article::query()->with(['category', 'author']))
            ->paginate($request->perPage());

        return view('admin.articles.index', [
            'articles' => $articles,
            'categories' => ArticleCategory::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Article::class);

        return view('admin.articles.create', ['categoryOptions' => $this->categoryOptions()]);
    }

    public function store(ArticleRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', Article::class);

        DB::transaction(function () use ($request, $media): void {
            $article = Article::query()->create($request->articleAttributes() + ['author_user_id' => $request->user()?->id]);
            $this->saveCover($request, $media, $article);
        });

        return redirect()->route('admin.articles.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.article')]));
    }

    public function edit(Article $article): View
    {
        $this->authorize('update', $article);

        return view('admin.articles.edit', [
            'article' => $article->load(['media', 'author']),
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    public function update(ArticleRequest $request, Article $article, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $article);

        DB::transaction(function () use ($request, $media, $article): void {
            $article->update($request->articleAttributes());
            $this->saveCover($request, $media, $article);
        });

        return redirect()->route('admin.articles.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.article')]));
    }

    /**
     * Soft delete: tidak ada UI pemulihan (pemulihan lewat tinker). Media cover dipertahankan.
     */
    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        DB::transaction(fn (): ?bool => $article->delete());

        return redirect()->route('admin.articles.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.article')]));
    }

    private function saveCover(ArticleRequest $request, MediaService $media, Article $article): void
    {
        $file = $request->coverFile();

        if ($file !== null) {
            $media->replace($article, 'cover', $file, ['alt_text' => $request->coverAlt()], $request->user());

            return;
        }

        if ($request->has('cover_alt')) {
            // Tanpa berkas baru: alt yang diedit diterapkan ke cover yang ada (caption tidak diubah).
            $existing = $article->media()->where('collection', 'cover')->first();

            if ($existing !== null && $existing->alt_text !== $request->coverAlt()) {
                $media->updateMeta($existing, $request->coverAlt(), $existing->caption);
            }
        }
    }

    /**
     * @return array<int, string> id => nama, urut `ordered()`
     */
    private function categoryOptions(): array
    {
        return ArticleCategory::query()->ordered()->get()->pluck('name', 'id')->all();
    }
}
