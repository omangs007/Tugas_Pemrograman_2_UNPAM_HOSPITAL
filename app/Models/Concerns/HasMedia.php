<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Relasi media polimorfik (`media.model_type` + `media.model_id`, tanpa FK; 08 §polimorfik).
 * Saat pemilik dihapus, baris dan berkas media-nya ikut dihapus lewat MediaService::deleteFor().
 * `deleteQuietly()` dan `Model::query()->delete()` tidak memicu event `deleting`, sehingga media-nya tidak ikut
 * dibersihkan; baris yang tertinggal itu dibersihkan `media:prune` butir (c).
 *
 * @mixin Model
 */
trait HasMedia
{
    public static function bootHasMedia(): void
    {
        static::deleting(function (Model $model): void {
            // Soft delete mempertahankan media (09 §6.3); media dihapus hanya bila model tanpa soft delete
            // atau sedang force delete (F4A-R32).
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                app(MediaService::class)->deleteFor($model);
            }
        });
    }

    /**
     * Seluruh media pemilik, urut `sort_order` lalu `id`. Muat dengan `with('media')` pada daftar.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Media pertama koleksi, atau null. Bertumpu pada mediaIn(), jadi mengikuti aturan mode ketatnya.
     */
    public function firstMedia(string $collection): ?Media
    {
        return $this->mediaIn($collection)->first();
    }

    /**
     * Semua media satu koleksi dalam urutan relasi. Memakai relasi `media` lewat getRelationValue(): bila sudah
     * dimuat tanpa query; model yang diambil sendirian memuatnya satu kali lalu menyimpannya; model dari query
     * banyak baris tanpa `with('media')` melempar LazyLoadingViolationException di non-produksi (mode ketat),
     * sehingga eager load yang terlupa pada daftar tertangkap alih-alih menjadi N+1 senyap.
     *
     * @return Collection<int, Media>
     */
    public function mediaIn(string $collection): Collection
    {
        return $this->getRelationValue('media')->where('collection', $collection)->values();
    }

    /**
     * URL media pertama koleksi (`full` atau `thumb`), atau URL placeholder lokal bila tidak ada media
     * atau berkasnya tidak berada di disk publik. $placeholder adalah path di bawah `public/`.
     */
    public function mediaUrl(string $collection, string $variant = 'full', ?string $placeholder = null): string
    {
        if (! in_array($variant, ['full', 'thumb'], true)) {
            throw new InvalidArgumentException("Varian gambar tidak dikenal: {$variant}.");
        }

        $media = $this->firstMedia($collection);
        $url = $media === null ? null : ($variant === 'thumb' ? $media->thumb_url : $media->url);

        return $url !== null && $url !== '' ? $url : asset($placeholder ?? 'images/placeholders/gallery.svg');
    }

    /**
     * Penghapusan pemilik bersifat atomik: bila baris pemilik gagal dihapus (mis. FK RESTRICT),
     * penghapusan baris media di event `deleting` ikut di-rollback dan berkasnya tidak disentuh
     * (MediaService::delete menghapus berkas setelah commit).
     */
    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }
}
