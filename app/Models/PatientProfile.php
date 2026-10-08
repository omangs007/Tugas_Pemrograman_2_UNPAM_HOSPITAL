<?php

namespace App\Models;

use App\Enums\BookingChannel;
use App\Enums\Gender;
use Database\Factories\PatientProfileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Identitas pasien yang dirujuk booking (09 §2.2). Pembuatan profil dilakukan PatientRegistrationService
 * (registrasi online, fase 5) dan alur walk-in (fase 7); nomor pasien dibuat PatientNumberGenerator.
 * NIK tidak dikumpulkan.
 */
class PatientProfile extends Model
{
    /** @use HasFactory<PatientProfileFactory> */
    use HasFactory;

    /** Penutup tampilan nomor BPJS: sembilan titik diikuti empat karakter terakhir (04 V2, 11 §5.1). */
    private const BPJS_MASK = '•••••••••';

    /**
     * `user_id`, `patient_number`, `bpjs_number_last4`, dan `created_by_user_id` sengaja tidak termasuk:
     * diisi service lewat forceFill() (`bpjs_number_last4` diisi hook `saving`).
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'birth_date',
        'gender',
        'phone',
        'email',
        'address',
        'bpjs_number',
        'registration_channel',
        'consent_at',
        'consent_version',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'bpjs_number',
    ];

    /**
     * Setiap kali `bpjs_number` berubah (diisi, diganti, atau dikosongkan), `bpjs_number_last4` dihitung
     * ulang dari nilai terdekripsi: empat karakter terakhir, atau NULL bila nomor kosong (F5-R11).
     * Hook berjalan setelah seluruh atribut terisi, sehingga nilai last4 yang dipaksakan lewat forceFill()
     * bersamaan dengan perubahan nomor tidak berlaku. Hanya berlaku lewat model (bukan insert massal).
     */
    protected static function booted(): void
    {
        static::saving(function (PatientProfile $profile): void {
            if (! $profile->isDirty('bpjs_number')) {
                return;
            }

            $number = $profile->bpjs_number;

            $profile->bpjs_number_last4 = is_string($number) && $number !== ''
                ? mb_substr($number, -4)
                : null;
        });
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'bpjs_number' => 'encrypted',
            'registration_channel' => BookingChannel::class,
            'consent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nomor BPJS tersamar untuk UI ("•••••••••1234"); NULL bila tidak ada nomor tersimpan. Nomor lengkap
     * tidak pernah dirender. Tidak ikut `toArray()` (tanpa `$appends`).
     *
     * @return Attribute<string|null, never>
     */
    protected function maskedBpjs(): Attribute
    {
        return Attribute::get(function (): ?string {
            $last4 = $this->bpjs_number_last4;

            return $last4 === null || $last4 === '' ? null : self::BPJS_MASK.$last4;
        });
    }

    /** Akun login pemilik profil; NULL untuk pasien walk-in tanpa akun. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Petugas pembuat profil walk-in; NULL untuk registrasi online. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
