<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'model_type',
        'model_id',
        'collection',
        'disk',
        'directory',
        'file_name',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'thumb_path',
        'alt_text',
        'caption',
        'sort_order',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeInCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }

    /**
     * Baris tanpa pemilik (unggahan editor yang belum terikat atau sudah dilepas) yang terakhir berubah lebih dari
     * $hours jam lalu (08 §5.2 butir 2: umur dihitung dari `updated_at`).
     */
    public function scopeOrphaned(Builder $query, int $hours = 24): Builder
    {
        return $query->whereNull('model_id')->where('updated_at', '<', now()->subHours($hours));
    }

    public function isPublic(): bool
    {
        return $this->disk === 'public';
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->isPublic()
            ? Storage::disk('public')->url($this->directory.'/'.$this->file_name)
            : null);
    }

    protected function thumbUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->isPublic()) {
                return null;
            }

            return $this->thumb_path !== null
                ? Storage::disk('public')->url($this->thumb_path)
                : $this->url;
        });
    }

    protected function humanSize(): Attribute
    {
        return Attribute::get(function (): string {
            $bytes = (int) $this->size_bytes;

            return match (true) {
                $bytes < 1024 => $bytes.' B',
                $bytes < 1048576 => (int) round($bytes / 1024).' KB',
                default => number_format($bytes / 1048576, 1, ',', '.').' MB',
            };
        });
    }
}
