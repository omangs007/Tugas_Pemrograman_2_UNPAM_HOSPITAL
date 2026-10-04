<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\SpecialtyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    /** @use HasFactory<SpecialtyFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
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

    /**
     * Dokter yang memiliki spesialisasi ini; pivot membawa `is_primary` (tanpa model pivot, 11 §5.8).
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_specialties')->withPivot('is_primary');
    }

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }

    /** Panjang kolom `specialties.slug` (09 §3.1). */
    protected function slugMaxLength(): int
    {
        return 120;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Alasan penghapusan ditolak (F2-R18), atau null bila boleh dihapus. Dipanggil controller
     * sebelum delete(), sehingga berlaku juga untuk Super Admin; FK RESTRICT hanya jaring pengaman.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->doctors()->exists() || $this->clinics()->exists()) {
            return 'Spesialisasi ini masih dipakai oleh dokter atau poliklinik sehingga tidak dapat dihapus. Nonaktifkan saja.';
        }

        return null;
    }
}
