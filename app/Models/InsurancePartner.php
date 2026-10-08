<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\InsurancePartnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mitra asuransi fiktif berlabel "Contoh" (09 §6.7; tabelnya dibuat fase 3). Mitra yang dirujuk
 * `appointments.insurance_partner_id` tidak dapat dihapus (FK RESTRICT; pesan BR-180 lewat
 * `deletionBlockedReason()`). Sisi seberangnya, `Appointment::insurancePartner()`, ditambahkan fase 6.
 */
class InsurancePartner extends Model
{
    /** @use HasFactory<InsurancePartnerFactory> */
    use HasFactory, HasMedia, HasUniqueSlug;

    /** @var list<string> */
    public const TYPES = ['asuransi', 'perusahaan'];

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'asuransi' => 'Asuransi',
        'perusahaan' => 'Perusahaan',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'website_url',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'insurance_partner_id');
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
     * Alasan penghapusan ditolak (BR-180), atau null bila boleh dihapus: ditolak bila masih dirujuk appointment.
     */
    public function deletionBlockedReason(): ?string
    {
        $count = $this->appointments()->count();

        if ($count > 0) {
            return "Mitra asuransi masih dipakai oleh {$count} data. Nonaktifkan saja.";
        }

        return null;
    }

    /**
     * URL logo (koleksi `logo`), atau placeholder `logo.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('logo', 'full', 'images/placeholders/logo.svg'));
    }

    /**
     * Label jenis mitra (`TYPE_LABELS`), atau nilai mentahnya bila di luar daftar.
     *
     * @return Attribute<string, never>
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::TYPE_LABELS[$this->type] ?? (string) $this->type);
    }

    /** Panjang kolom `insurance_partners.slug` (09 §6.7). */
    protected function slugMaxLength(): int
    {
        return 170;
    }
}
