<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Atribut yang boleh diisi massal. `account_type`, `is_active`, `must_change_password`,
     * dan `last_login_at` sengaja tidak termasuk: diubah lewat forceFill() oleh service/controller login.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Label peran untuk UI ("Super Admin", "Admin Konten", "Petugas Pendaftaran"); kosong bila tanpa peran.
     * Memakai getRoleNames() (loadMissing), sehingga aman di mode ketat.
     *
     * @return Attribute<string, never>
     */
    protected function roleLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $role = $this->getRoleNames()->first();

            return $role === null ? '' : __('roles.'.$role);
        });
    }

    /** Hanya akun staf. */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Staff->value);
    }

    /** Hanya akun pasien. */
    public function scopePatients(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Patient->value);
    }

    /** Hanya akun aktif (boleh login). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Profil pasien milik akun ini (satu akun satu profil, K-07); NULL untuk staf. Daftar yang menampilkan
     * profil wajib eager-load relasi ini (mode ketat).
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function isStaff(): bool
    {
        return $this->account_type === AccountType::Staff;
    }

    public function isPatient(): bool
    {
        return $this->account_type === AccountType::Patient;
    }
}
