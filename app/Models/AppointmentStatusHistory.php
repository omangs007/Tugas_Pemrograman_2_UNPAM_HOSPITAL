<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Jejak perubahan status appointment (09 §4.2). Append-only: tidak ada `updated_at`, tidak dapat diubah
 * atau dihapus lewat model (hapus appointment oleh DB tetap meng-cascade).
 */
class AppointmentStatusHistory extends Model
{
    /** @use HasFactory<AppointmentStatusHistoryFactory> */
    use HasFactory;

    /** Tabel append-only: tidak ada updated_at. */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'appointment_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_user_id',
        'reason',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'actor_type' => ActorType::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Riwayat status appointment tidak dapat diubah atau dihapus.');
        });

        static::deleting(function (): void {
            throw new LogicException('Riwayat status appointment tidak dapat diubah atau dihapus.');
        });
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
