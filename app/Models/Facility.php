<?php

namespace App\Models;

use App\Enums\FacilityCategory;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\SanitizesRichText;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fasilitas rumah sakit: medis, penunjang, dan umum (09 §5.2). Tidak dirujuk tabel lain, sehingga selalu dapat
 * dihapus permanen (media ikut dihapus). Slug dibuat dari `name` dan tidak berubah saat nama diedit (BR-117).
 * Daftar yang memakai `cover_url` wajib memuat `media` (mode ketat).
 */
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'short_description',
        'description',
        'is_active',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'category' => FacilityCategory::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('name'))->orderBy($this->qualifyColumn('id'));
    }

    public function scopeOfCategory(Builder $query, FacilityCategory $category): void
    {
        $query->where($this->qualifyColumn('category'), $category->value);
    }

    /**
     * Kolom rich text yang disanitasi saat disimpan (BR-122).
     *
     * @return list<string>
     */
    public function richTextAttributes(): array
    {
        return ['description'];
    }

    /**
     * URL cover (koleksi `cover`), atau placeholder `facility.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('cover', 'full', 'images/placeholders/facility.svg'));
    }

    /**
     * Judul SEO: `meta_title`, atau nama bila kosong (BR-123).
     *
     * @return Attribute<string, never>
     */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_title) ? (string) $this->meta_title : (string) $this->name);
    }

    /**
     * Deskripsi SEO: `meta_description`, atau `short_description` bila kosong (BR-123).
     *
     * @return Attribute<string, never>
     */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_description) ? (string) $this->meta_description : (string) $this->short_description);
    }

    /** Panjang kolom `facilities.slug` (09 §5.2, F4B-R4). */
    protected function slugMaxLength(): int
    {
        return 170;
    }
}
