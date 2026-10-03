<?php

namespace App\Models;

use App\Enums\ActorType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Jejak audit append-only (09 §7.3). Ditulis hanya lewat AuditLogger; tidak dapat diubah/dihapus.
 */
class AuditLog extends Model
{
    /** Tabel append-only: tidak ada updated_at. */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_type',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit log tidak dapat diubah atau dihapus.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit log tidak dapat diubah atau dihapus.');
        });
    }

    /** Pelaku (NULL untuk pelaku sistem atau akun yang sudah dihapus). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Model yang diaudit (`auditable_id` NULL untuk aksi tanpa satu baris). */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Aksi berawalan tertentu, mis. `staff.` atau `auth.staff_login`. Awalan dicocokkan harfiah
     * (karakter LIKE `%`, `_`, `\` di-escape).
     */
    public function scopeForAction(Builder $query, string $prefix): Builder
    {
        return $query->where('action', 'like', addcslashes($prefix, '\\%_').'%');
    }

    /**
     * Rentang `created_at` inklusif. Batas bernilai teks `Y-m-d` (tanpa jam): `$from` = awal hari,
     * `$to` = akhir hari. NULL = tanpa batas.
     */
    public function scopeBetween(Builder $query, CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): Builder
    {
        if ($from !== null) {
            $query->where('created_at', '>=', Carbon::parse($from));
        }

        if ($to !== null) {
            $end = Carbon::parse($to);

            if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
                $end = $end->endOfDay();
            }

            $query->where('created_at', '<=', $end);
        }

        return $query;
    }
}
