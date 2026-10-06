<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ArticleIndexRequest;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Halaman publik artikel (#18 `articles.index`, #19 `articles.show`). Hanya artikel yang tayang (terbit, `published_at`
 * <= sekarang, belum dihapus; F4A-R10). Tanpa Policy: yang tidak tayang menjawab 404 untuk semua orang, termasuk staf.
 */
class ArticleController extends Controller
{
    /** Jumlah kartu per halaman (F4A-R13). */
    public const PER_PAGE = 9;

    /** Jumlah artikel terkait pada halaman detail. */
    public const RELATED = 3;

    public function index(ArticleIndexRequest $request): View
    {
        $filters = $request->filters();
        $category = $filters['kategori'];
        $term = $filters['q'];

        $articles = Article::query()
            ->published()
            ->with(['category', 'media' => fn ($query) => $query->where('collection', 'cover')])
            ->when($category !== null, fn (Builder $query) => $query->inCategory($category->slug))
            ->search($term)
            ->newestFirst()
            ->paginate(self::PER_PAGE)
            ->appends(array_filter(['kategori' => $category?->slug, 'q' => $term], fn (?string $value): bool => $value !== null));

        $page = Page::forKey('articles-index');

        // Judul: judul halaman, ditambah nama kategori dan nomor halaman (>= 2) (F4A-R5, 05 §7).
        $title = $page->seo_title;

        if ($category !== null) {
            $title .= ' — '.$category->name;
        }

        if ($articles->currentPage() > 1) {
            $title .= ' — Halaman '.$articles->currentPage();
        }

        $categories = ArticleCategory::query()->ordered()->get(['id', 'name', 'slug']);

        return view('public.articles.index', [
            'page' => $page,
            'title' => $title,
            'articles' => $articles,
            'category' => $category,
            'term' => $term,
            'categoryOptions' => ['' => 'Semua kategori'] + $categories->pluck('name', 'slug')->all(),
        ]);
    }

    public function show(Article $article): View
    {
        // Tanpa Policy: draf, terjadwal, atau terhapus (binding SoftDeletes) = 404 untuk semua orang, termasuk staf.
        abort_unless($article->isPubliclyVisible(), 404);

        $article->load([
            'category',
            'author:id,name',
            'media' => fn ($query) => $query->where('collection', 'cover'),
        ]);

        /** @var Collection<int, Article> $related */
        $related = $article->article_category_id === null
            ? collect()
            : Article::query()
                ->published()
                ->where('article_category_id', $article->article_category_id)
                ->whereKeyNot($article->getKey())
                ->with(['category', 'media' => fn ($query) => $query->where('collection', 'cover')])
                ->newestFirst()
                ->limit(self::RELATED)
                ->get();

        return view('public.articles.show', [
            'article' => $article,
            'related' => $related,
        ]);
    }
}
