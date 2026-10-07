<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pesan dari formulir kontak publik (09 §6.8). Teks biasa yang disimpan apa adanya dan selalu dirender dengan
 * `{{ }}`. IP mentah tidak disimpan: `ip_hash` (tersembunyi dari serialisasi) berisi SHA-256 dari IP dan APP_KEY.
 * `handled_by` dan `handled_at` tidak fillable; diisi lewat forceFill() saat status berubah ke `replied`/`archived`.
 * Tidak diobservasi AuditObserver (F4B-R25) dan tidak ada route hapus (00 C).
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'consent_at',
        'ip_hash',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'ip_hash',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'consent_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    /** Staf yang membalas atau mengarsipkan pesan (NULL bila belum ditangani atau akunnya dihapus). */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeUnread(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), ContactMessageStatus::New->value);
    }
}
