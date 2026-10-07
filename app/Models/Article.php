<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\SanitizesRichText;
use App\Support\LikePattern;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Artikel kesehatan (09 §6.3). Tayang publik = `status = published` ∧ `published_at <= now()` ∧ belum dihapus
 * (F4A-R10); `published_at` di masa depan berarti terjadwal. Soft delete mempertahankan media dan slug.
 * Accessor `reading_time_minutes` dan `seo_*` membaca `body`/`excerpt`, jadi daftar yang memakainya tidak boleh
 * memilih kolom parsial.
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText, SoftDeletes;

    /**
     * `author_user_id` diisi controller (pengguna pembuat), tetapi tetap fillable agar factory/seeder
     * dapat mengaturnya; `deleted_at` tidak termasuk.
     *
     * @var list<string>
     */
    protected $fillable = [
        'article_category_id',
        'author_user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /** Tayang: terbit dan `published_at` sudah tercapai (inklusif). Artikel terhapus sudah disaring SoftDeletes. */
    public function scopePublished(Builder $query): void
    {
        $query
            ->where($this->qualifyColumn('status'), ArticleStatus::Published->value)
            ->where($this->qualifyColumn('published_at'), '<=', now()->startOfSecond());
    }

    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('published_at'))->orderByDesc($this->qualifyColumn('id'));
    }

    /**
     * Cari pada judul dan ringkasan. Kata kunci dipangkas; karakter `\`, `%`, `_` di-escape; kosong tanpa efek.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = LikePattern::contains($term);

        $query->where(function (Builder $inner) use ($like): void {
            $inner->where($this->qualifyColumn('title'), 'like', $like)
                ->orWhere($this->qualifyColumn('excerpt'), 'like', $like);
        });
    }

    /** Artikel pada kategori dengan slug tertentu (relasi `category` tidak perlu dimuat). */
    public function scopeInCategory(Builder $query, string $slug): void
    {
        $query->whereHas('category', fn (Builder $category) => $category->where('slug', $slug));
    }

    /** Dapat dilihat publik sekarang (F4A-R10). */
    public function isPubliclyVisible(): bool
    {
        return ! $this->trashed()
            && $this->status === ArticleStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now()->startOfSecond());
    }

    /** Terbit tetapi belum tayang: `published_at` di masa depan. Draf bukan terjadwal. */
    public function isScheduled(): bool
    {
        return $this->status === ArticleStatus::Published
            && $this->published_at !== null
            && $this->published_at->gt(now()->startOfSecond());
    }

    /**
     * URL cover (koleksi `cover`), atau placeholder `article.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('cover', 'full', 'images/placeholders/article.svg'));
    }

    /**
     * Perkiraan waktu baca: jumlah kata teks polos (tag dibuang, setiap tag menjadi pemisah) dibagi 200,
     * dibulatkan ke atas, minimal 1 menit.
     *
     * @return Attribute<int, never>
     */
    protected function readingTimeMinutes(): Attribute
    {
        return Attribute::get(function (): int {
            $spaced = preg_replace('~<[^>]*>~', ' ', (string) $this->body) ?? '';
            $text = trim(html_entity_decode($spaced, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $words = $text === '' ? [] : (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);

            return max(1, (int) ceil(count($words) / 200));
        });
    }

    /**
     * Judul SEO: `meta_title`, atau judul artikel bila kosong (F4A-R5).
     *
     * @return Attribute<string, never>
     */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_title) ? (string) $this->meta_title : (string) $this->title);
    }

    /**
     * Deskripsi SEO: `meta_description`, atau ringkasan bila kosong (F4A-R5).
     *
     * @return Attribute<string, never>
     */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_description) ? (string) $this->meta_description : (string) $this->excerpt);
    }

    /**
     * Kolom rich text yang disanitasi saat disimpan (F2-R14).
     *
     * @return list<string>
     */
    public function richTextAttributes(): array
    {
        return ['body'];
    }

    /** Slug berasal dari judul (F4A-R7). */
    protected function slugSource(): string
    {
        return (string) $this->getAttribute('title');
    }

    /** Panjang kolom `articles.slug` (09 §6.3). */
    protected function slugMaxLength(): int
    {
        return 220;
    }
}
