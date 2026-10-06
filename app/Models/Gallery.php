<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Album galeri (09 §6.5). Foto ada di tabel `media` (koleksi `photos`, urut `sort_order`, `id`); sampul =
 * foto pertama. Hapus permanen: seluruh foto dan berkasnya ikut terhapus.
 */
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory, HasMedia, HasUniqueSlug;

    /** Batas foto per album (12 V-34). */
    public const MAX_PHOTOS = 100;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_published',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_published'), true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('id'));
    }

    /** Foto pertama album (sampul), atau null. Muat `media` pada daftar. */
    public function coverMedia(): ?Media
    {
        return $this->mediaIn('photos')->first();
    }

    /** Slug berasal dari judul (F4A-R7). */
    protected function slugSource(): string
    {
        return (string) $this->getAttribute('title');
    }

    /** Panjang kolom `galleries.slug` (09 §6.5). */
    protected function slugMaxLength(): int
    {
        return 170;
    }
}
