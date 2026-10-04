<?php

namespace App\Models;

use App\Enums\ClinicType;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\SanitizesRichText;
use Database\Factories\ClinicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Clinic extends Model
{
    /** @use HasFactory<ClinicFactory> */
    use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'type',
        'is_bookable',
        'specialty_id',
        'short_description',
        'description',
        'location',
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
            'type' => ClinicType::class,
            'is_bookable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Kode antrean selalu disimpan huruf kapital tanpa spasi tepi (awalan nomor antrean, mis. ANK).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value === null ? null : Str::upper(trim($value)));
    }

    /**
     * Judul SEO: `meta_title`, atau nama bila kosong (09 §3.2).
     *
     * @return Attribute<string, never>
     */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_title) ? (string) $this->meta_title : (string) $this->name);
    }

    /**
     * Deskripsi SEO: `meta_description`, atau `short_description` bila kosong (09 §3.2).
     *
     * @return Attribute<string, never>
     */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_description) ? (string) $this->meta_description : (string) $this->short_description);
    }

    /**
     * URL cover (koleksi `cover`), atau placeholder `clinic.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('cover', 'full', 'images/placeholders/clinic.svg'));
    }

    /** Panjang kolom `clinics.slug` (09 §3.2). */
    protected function slugMaxLength(): int
    {
        return 140;
    }

    /**
     * Kolom rich text yang disanitasi saat disimpan (F2-R14).
     *
     * @return list<string>
     */
    public function richTextAttributes(): array
    {
        return ['description'];
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Aktif dan boleh dipakai untuk pendaftaran kunjungan. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_bookable', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Alasan penghapusan ditolak (F2-R18), atau null bila boleh dihapus. Fase 3 menambah jadwal,
     * sesi, dan appointment pada pemeriksaan ini.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->doctors()->exists()) {
            return 'Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja.';
        }

        return null;
    }
}
