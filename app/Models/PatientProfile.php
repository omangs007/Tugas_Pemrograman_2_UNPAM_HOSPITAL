<?php

namespace App\Models;

use App\Enums\BookingChannel;
use App\Enums\Gender;
use Database\Factories\PatientProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Identitas pasien yang dirujuk booking (09 §2.2). Fase 3 hanya menyediakan skema, model, dan factory;
 * pembuatan profil (PatientNumberGenerator, registrasi, walk-in) dibangun di fase 5 (F3-R4).
 * NIK tidak dikumpulkan.
 */
class PatientProfile extends Model
{
    /** @use HasFactory<PatientProfileFactory> */
    use HasFactory;

    /**
     * `user_id`, `patient_number`, `bpjs_number_last4`, dan `created_by_user_id` sengaja tidak termasuk:
     * diisi service (fase 5) lewat forceFill().
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
