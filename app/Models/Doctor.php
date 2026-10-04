<?php

namespace App\Models;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory;

    /**
     * `archived_at` sengaja tidak termasuk: diisi `DoctorService` (Task 8) lewat forceFill().
     *
     * @var list<string>
     */
    protected $fillable = [
        'clinic_id',
        'name',
        'slug',
        'title_prefix',
        'title_suffix',
        'gender',
        'short_bio',
        'bio',
        'status',
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
            'gender' => Gender::class,
            'status' => DoctorStatus::class,
            'archived_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Nama tampilan "{title_prefix} {name}, {title_suffix}"; bagian kosong dibuang tanpa spasi
     * atau koma yatim (mis. "dr. Rani Oktaviani" tanpa sufiks, "Rani Oktaviani" tanpa gelar).
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            $base = trim(trim((string) $this->title_prefix).' '.trim((string) $this->name));
            $suffix = trim((string) $this->title_suffix);

            return $suffix === '' ? $base : $base.', '.$suffix;
        });
    }

    /**
     * Spesialisasi utama (F2-R24): pivot `is_primary` dari relasi `specialties`, dengan cadangan
     * spesialisasi pertama. Relasi `specialties` wajib sudah dimuat (mode ketat melarang lazy loading).
     * Tanpa cache objek: hasilnya mengikuti relasi yang dimuat ulang.
     *
     * @return Attribute<Specialty|null, never>
     */
    protected function primarySpecialty(): Attribute
    {
        return Attribute::get(function (): ?Specialty {
            $specialties = $this->specialties;

            return $specialties->first(fn (Specialty $specialty): bool => (bool) $specialty->pivot->is_primary)
                ?? $specialties->first();
        })->withoutObjectCaching();
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialties')->withPivot('is_primary');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', DoctorStatus::Active->value);
    }

    /** Tampil di publik: dokter aktif pada poliklinik yang aktif (F.2/F.3, F2-R28). */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', DoctorStatus::Active->value)
            ->whereHas('clinic', fn (Builder $clinic): Builder => $clinic->where('is_active', true));
    }

    /** Cari nama dokter atau nama spesialisasi (LIKE; karakter % dan _ pada input diperlakukan literal). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('doctors.name', 'like', $like)
                ->orWhereHas('specialties', fn (Builder $specialty): Builder => $specialty->where('specialties.name', 'like', $like));
        });
    }

    /**
     * Saring menurut spesialisasi mana pun (utama atau tambahan). Bilangan bulat dibaca sebagai id;
     * string selalu dibaca sebagai slug (parameter kueri `spesialisasi` berisi slug).
     */
    public function scopeInSpecialty(Builder $query, int|string $idOrSlug): Builder
    {
        return $query->whereHas('specialties', function (Builder $specialty) use ($idOrSlug): void {
            is_int($idOrSlug)
                ? $specialty->where('specialties.id', $idOrSlug)
                : $specialty->where('specialties.slug', $idOrSlug);
        });
    }

    public function scopeInClinic(Builder $query, int $id): Builder
    {
        return $query->where('doctors.clinic_id', $id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('doctors.sort_order')->orderBy('doctors.name');
    }
}
