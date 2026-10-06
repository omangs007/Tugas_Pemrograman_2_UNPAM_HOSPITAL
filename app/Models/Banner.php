<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Support\InternalLink;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Banner beranda (09 §6.4). Tayang bila `is_active` dan sekarang berada dalam [`starts_at`, `ends_at`]
 * (NULL = tanpa batas; batas inklusif). Hapus permanen; gambarnya (koleksi `image`) ikut terhapus.
 */
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, HasMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'button_label',
        'button_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Sedang tayang, urut `sort_order` lalu `id` (F4A-R25). */
    public function scopeCurrentlyActive(Builder $query): void
    {
        $now = now()->startOfSecond();

        $query
            ->where($this->qualifyColumn('is_active'), true)
            ->where(function (Builder $inner) use ($now): void {
                $inner->whereNull($this->qualifyColumn('starts_at'))->orWhere($this->qualifyColumn('starts_at'), '<=', $now);
            })
            ->where(function (Builder $inner) use ($now): void {
                $inner->whereNull($this->qualifyColumn('ends_at'))->orWhere($this->qualifyColumn('ends_at'), '>=', $now);
            })
            ->ordered();
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('id'));
    }

    /**
     * Tombol layak tampil (F4A-R15): label dan URL terisi, dan URL-nya `https://…` atau path internal yang
     * cocok dengan route terdaftar (`InternalLink::exists`).
     */
    public function hasUsableButton(): bool
    {
        $label = trim((string) $this->button_label);
        $url = trim((string) $this->button_url);

        if ($label === '' || $url === '') {
            return false;
        }

        if (str_starts_with($url, 'https://')) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return InternalLink::exists($url);
    }

    /**
     * Label periode untuk admin (F4A-R25), dihitung dengan waktu aplikasi: `Tayang`, `Terjadwal`, `Berakhir`,
     * atau `Nonaktif`.
     */
    public function scheduleLabel(): string
    {
        if (! $this->is_active) {
            return 'Nonaktif';
        }

        $now = now()->startOfSecond();

        if ($this->starts_at !== null && $this->starts_at->gt($now)) {
            return 'Terjadwal';
        }

        if ($this->ends_at !== null && $this->ends_at->lt($now)) {
            return 'Berakhir';
        }

        return 'Tayang';
    }

    /**
     * URL gambar banner (koleksi `image`), atau placeholder `banner.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('image', 'full', 'images/placeholders/banner.svg'));
    }
}
