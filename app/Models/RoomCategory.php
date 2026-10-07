<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\SanitizesRichText;
use Database\Factories\RoomCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategori kamar rawat inap dengan model kapasitas agregat (09 §5.1, 00 E.4). Ketersediaan diperbarui manual oleh
 * staf: `availability_updated_at`/`availability_updated_by` hanya diubah aksi ketersediaan (Task 5), bukan edit
 * deskripsi, tarif, atau kapasitas (F4B-R18). Tidak dirujuk tabel lain, sehingga selalu dapat dihapus (F4B-R20).
 * Daftar yang memakai `cover_url` atau `availabilityUpdater` wajib memuat `media` dan relasinya (mode ketat).
 * Label tidak pernah memakai kata "real-time" (01, 02 F-NXT-08).
 */
class RoomCategory extends Model
{
    /** @use HasFactory<RoomCategoryFactory> */
    use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;

    /** Data ketersediaan lebih tua dari ini (jam) dianggap mungkin tidak terkini (BR-114, F4B-R10). */
    public const STALE_AFTER_HOURS = 24;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'amenities',
        'bed_capacity',
        'beds_available',
        'sample_rate',
        'rate_unit',
        'rate_note',
        'is_active',
        'sort_order',
        'availability_updated_at',
        'availability_updated_by',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'bed_capacity' => 'integer',
            'beds_available' => 'integer',
            'sample_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'availability_updated_at' => 'datetime',
        ];
    }

    /** Petugas yang terakhir memperbarui ketersediaan (NULL bila belum pernah atau akunnya dihapus). */
    public function availabilityUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'availability_updated_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('name'))->orderBy($this->qualifyColumn('id'));
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
     * URL cover (koleksi `cover`), atau placeholder `room.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('cover', 'full', 'images/placeholders/room.svg'));
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

    /**
     * "{beds_available} dari {bed_capacity} tempat tidur tersedia" (11 §5.5, F4B-R10).
     *
     * @return Attribute<string, never>
     */
    protected function occupancyLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->beds_available.' dari '.$this->bed_capacity.' tempat tidur tersedia');
    }

    /**
     * "Diperbarui manual oleh petugas pada {j M Y}, pukul {H.i} WIB", atau "Belum diperbarui" (F4B-R10, 02 F-NXT-08).
     *
     * @return Attribute<string, never>
     */
    protected function availabilityUpdatedLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $at = $this->availability_updated_at;

            if ($at === null) {
                return 'Belum diperbarui';
            }

            return 'Diperbarui manual oleh petugas pada '.$at->translatedFormat('j M Y').', pukul '.$at->format('H.i').' WIB';
        });
    }

    /**
     * Benar bila belum pernah diperbarui atau pembaruan terakhir lebih tua dari `STALE_AFTER_HOURS` jam (BR-114).
     * Tepat 24 jam belum dianggap kedaluwarsa.
     *
     * @return Attribute<bool, never>
     */
    protected function isAvailabilityStale(): Attribute
    {
        return Attribute::get(function (): bool {
            $at = $this->availability_updated_at;

            return $at === null || $at->lt(now()->subHours(self::STALE_AFTER_HOURS));
        });
    }

    /**
     * Penuh bila tidak ada tempat tidur tersedia (badge "Penuh", F4B-R9).
     *
     * @return Attribute<bool, never>
     */
    protected function isFull(): Attribute
    {
        return Attribute::get(fn (): bool => (int) $this->beds_available === 0);
    }

    /**
     * "Rp 400.000 per malam", atau "Hubungi kami" bila tarif contoh kosong (F4B-R12). Tanpa Number::currency agar
     * tidak ada spasi tak putus.
     *
     * @return Attribute<string, never>
     */
    protected function sampleRateLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->sample_rate === null) {
                return 'Hubungi kami';
            }

            return 'Rp '.number_format((float) $this->sample_rate, 0, ',', '.').' '.$this->rate_unit;
        });
    }

    /** Panjang kolom `room_categories.slug` (09 §5.1, F4B-R4). */
    protected function slugMaxLength(): int
    {
        return 120;
    }
}
