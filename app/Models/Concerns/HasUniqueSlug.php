<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Slug otomatis (F2-R5): `Str::slug` dari nama, sufiks `-2`, `-3`, ... bila bentrok, dipotong sesuai
 * panjang kolom, tidak pernah kosong, dan hanya dibuat saat model dibuat (tidak berubah saat nama diedit).
 * Indeks UNIQUE pada kolom `slug` tetap menjadi pengaman terakhir untuk dua simpan yang bersamaan.
 *
 * @mixin Model
 */
trait HasUniqueSlug
{
    /** Panjang maksimum kolom `slug` model ini (09 §3.1-3.3). */
    abstract protected function slugMaxLength(): int;

    public static function bootHasUniqueSlug(): void
    {
        static::creating(function (Model $model): void {
            $slug = $model->getAttributes()['slug'] ?? null;

            if ($slug === null || trim((string) $slug) === '') {
                $model->setAttribute('slug', static::uniqueSlug($model->slugSource()));
            }
        });
    }

    /** Teks sumber slug; secara baku kolom `name`. */
    protected function slugSource(): string
    {
        return (string) $this->getAttribute('name');
    }

    /**
     * Slug unik dari $source. $ignoreId mengecualikan baris itu dari pemeriksaan bentrok.
     */
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $max = (new static)->slugMaxLength();
        $base = self::trimSlug(Str::slug($source), $max);

        if ($base === '') {
            $base = self::trimSlug(Str::slug(class_basename(static::class)).'-'.Str::lower(Str::random(6)), $max);
        }

        $candidate = $base;

        for ($number = 2; static::slugTaken($candidate, $ignoreId); $number++) {
            $suffix = '-'.$number;
            $candidate = self::trimSlug($base, $max - strlen($suffix)).$suffix;
        }

        return $candidate;
    }

    private static function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $query = static::query();

        // Indeks UNIQUE `slug` mencakup baris soft delete: slug artikel terhapus tidak dipakai ulang (F4A-R7).
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        return $query
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /** Potong ke $max karakter tanpa tanda hubung di ujung (slug dari Str::slug hanya ASCII). */
    private static function trimSlug(string $slug, int $max): string
    {
        return rtrim(substr($slug, 0, max($max, 0)), '-');
    }
}
