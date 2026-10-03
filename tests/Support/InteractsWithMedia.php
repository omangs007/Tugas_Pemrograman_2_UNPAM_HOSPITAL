<?php

namespace Tests\Support;

use App\Models\Media;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuat baris `media` langsung (tanpa MediaService) untuk tes model, prune, dan pengaturan.
 */
trait InteractsWithMedia
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function mediaAttributes(array $overrides = []): array
    {
        $uuid = (string) Str::uuid();

        return array_merge([
            'model_type' => null,
            'model_id' => null,
            'collection' => 'editor',
            'disk' => 'public',
            'directory' => 'editor/2026/10',
            'file_name' => $uuid.'.jpg',
            'original_name' => 'contoh.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 250880,
            'width' => 800,
            'height' => 600,
            'thumb_path' => 'editor/2026/10/thumbs/'.$uuid.'.jpg',
            'alt_text' => 'Gambar contoh',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMediaRow(array $overrides = [], ?CarbonInterface $createdAt = null): Media
    {
        $media = Media::query()->create($this->mediaAttributes($overrides));

        if ($createdAt !== null) {
            DB::table('media')->where('id', $media->id)->update(['created_at' => $createdAt]);
            $media->refresh();
        }

        return $media;
    }
}
