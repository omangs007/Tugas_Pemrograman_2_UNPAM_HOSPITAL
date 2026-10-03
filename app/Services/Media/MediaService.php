<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use App\Rules\ImageRules;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use finfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Decoders\BinaryImageDecoder;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaService
{
    /**
     * Koleksi baku 00 E.6; dimensi minimum 00 K; maksimum 6000 x 6000 untuk semua.
     */
    public const COLLECTIONS = [
        'photo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [300, 300]],
        'cover' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [800, 450]],
        'gallery' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
        'image' => ['single' => true, 'disk' => 'public', 'max_kb' => 4096, 'min' => [1200, 400]],
        'photos' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
        'logo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [120, 40]],
        'editor' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [200, 100]],
    ];

    public const MAX_DIMENSION = 6000;

    public const RESIZE_WIDTH = 1600;

    public const THUMB_WIDTH = 400;

    public const JPEG_QUALITY = 82;

    public const WEBP_QUALITY = 82;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Simpan satu gambar: dekode, perkecil (lebar maks 1600), encode ulang sesuai MIME nyata,
     * simpan dengan nama UUID beserta thumbnail 400 px, catat baris `media` dan audit.
     *
     * @param  array<string, mixed>  $meta  `alt_text` (wajib), `caption` (opsional)
     */
    public function store(UploadedFile $file, string $collection, ?Model $owner = null, array $meta = [], ?User $uploader = null): Media
    {
        $config = $this->configFor($collection);

        return $this->createMedia($file, $collection, $config, $owner, $meta, $uploader, function (Media $media) use ($uploader): void {
            $this->audit->log(
                'media.uploaded',
                $media,
                null,
                ['media_id' => $media->id, 'collection' => $media->collection, 'file_name' => $media->file_name],
                "Gambar diunggah ke koleksi {$media->collection}.",
                $uploader !== null ? Actor::staff($uploader) : null,
            );
        });
    }

    /**
     * Ganti media koleksi tunggal: berkas + baris baru disimpan dulu, media lama dihapus
     * SETELAH transaksi commit (DB::afterCommit). Bila transaksi pemanggil di-rollback, media
     * lama tetap utuh (berkas baru yang sempat tertulis menjadi yatim dan dibersihkan media:prune).
     *
     * @param  array<string, mixed>  $meta
     */
    public function replace(Model $owner, string $collection, UploadedFile $file, array $meta = [], ?User $uploader = null): Media
    {
        $config = $this->configFor($collection);

        if (! $config['single']) {
            throw new InvalidArgumentException("Koleksi {$collection} bukan koleksi tunggal dan tidak dapat diganti.");
        }

        $this->assertOwnerPersisted($owner);

        $olds = $this->ownedBy($owner, $collection)->get();

        $media = $this->createMedia($file, $collection, $config, $owner, $meta, $uploader, function (Media $new) use ($olds, $uploader): void {
            $previous = $olds->first();

            $this->audit->log(
                'media.replaced',
                $new,
                $previous === null ? null : ['media_id' => $previous->id, 'file_name' => $previous->file_name],
                ['media_id' => $new->id, 'file_name' => $new->file_name],
                "Gambar koleksi {$new->collection} diganti.",
                $uploader !== null ? Actor::staff($uploader) : null,
            );
        });

        DB::afterCommit(function () use ($olds): void {
            foreach ($olds as $old) {
                $this->purge($old);
            }
        });

        return $media;
    }

    /**
     * Hapus baris media; berkas asli dan thumbnail dihapus setelah transaksi commit.
     */
    public function delete(Media $media): void
    {
        $disk = $media->disk;
        $paths = $this->pathsOf($media);

        DB::transaction(function () use ($media, $disk, $paths): void {
            $this->audit->log(
                'media.deleted',
                $media,
                ['media_id' => $media->id, 'collection' => $media->collection, 'file_name' => $media->file_name],
                null,
                "Gambar dihapus dari koleksi {$media->collection}.",
            );

            $media->delete();

            DB::afterCommit(fn () => Storage::disk($disk)->delete($paths));
        });
    }

    /**
     * Hapus seluruh media milik satu pemilik (dipanggil saat model pemilik dihapus).
     */
    public function deleteFor(Model $owner): void
    {
        Media::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->orderBy('id')
            ->get()
            ->each(fn (Media $media) => $this->delete($media));
    }

    /**
     * Ubah teks alternatif (wajib, maks 255) dan keterangan (opsional, maks 255). Berkas tidak disentuh.
     */
    public function updateMeta(Media $media, ?string $altText, ?string $caption): Media
    {
        $altText = trim((string) $altText);
        $caption = $this->nullableTrim($caption);
        $errors = $this->metaErrors($altText, $caption);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $media->fill(['alt_text' => $altText, 'caption' => $caption])->save();

        return $media;
    }

    /**
     * Atur urutan: sort_order = posisi 1-based sesuai $orderedIds. Semua id harus milik pemilik
     * dan koleksi yang sama dan tidak boleh ganda.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorder(Model $owner, string $collection, array $orderedIds): void
    {
        $this->configFor($collection);
        $this->assertOwnerPersisted($owner);

        $ids = array_map('intval', array_values($orderedIds));
        $owned = $ids === [] ? [] : $this->ownedBy($owner, $collection)->whereIn('id', $ids)->pluck('id')->all();

        if ($ids === [] || count($ids) !== count(array_unique($ids)) || count($owned) !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Urutan gambar tidak valid.']);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                Media::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    private function createMedia(UploadedFile $file, string $collection, array $config, ?Model $owner, array $meta, ?User $uploader, callable $audit): Media
    {
        // $audit dipanggil di dalam transaksi tepat setelah baris media dibuat.
        $altText = trim((string) ($meta['alt_text'] ?? ''));
        $caption = $this->nullableTrim($meta['caption'] ?? null);
        $this->assertOwnerPersisted($owner);
        $this->assertAcceptable($file, $collection, $config, $altText, $caption);

        $binary = (string) file_get_contents((string) $file->getRealPath());
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        try {
            $image = ImageManager::gd()->read($binary, BinaryImageDecoder::class);
            $image->scaleDown(width: self::RESIZE_WIDTH);
            $thumbnail = clone $image;
            $thumbnail->scaleDown(width: self::THUMB_WIDTH);
            $encoded = $this->encode($image, $mime);
            $thumbnailEncoded = $this->encode($thumbnail, $mime);
        } catch (ImageException) {
            throw ValidationException::withMessages(['file' => ImageRules::INVALID_IMAGE]);
        }

        $width = $image->width();
        $height = $image->height();

        $extension = self::EXTENSIONS[$mime];
        $name = Str::uuid()->toString().'.'.$extension;
        $directory = $collection.'/'.now()->format('Y/m');
        $thumbPath = $directory.'/thumbs/'.$name;
        $disk = Storage::disk($config['disk']);
        $written = [];

        try {
            foreach ([$directory.'/'.$name => $encoded, $thumbPath => $thumbnailEncoded] as $path => $content) {
                if (! $disk->put($path, $content->toString())) {
                    throw new RuntimeException("Gagal menulis berkas gambar: {$path}");
                }

                $written[] = $path;
            }

            return DB::transaction(function () use ($file, $collection, $config, $owner, $uploader, $audit, $altText, $caption, $directory, $name, $extension, $mime, $encoded, $width, $height, $thumbPath): Media {
                $media = Media::query()->create([
                    'model_type' => $owner?->getMorphClass(),
                    'model_id' => $owner?->getKey(),
                    'collection' => $collection,
                    'disk' => $config['disk'],
                    'directory' => $directory,
                    'file_name' => $name,
                    'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName()),
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'size_bytes' => $encoded->size(),
                    'width' => $width,
                    'height' => $height,
                    'thumb_path' => $thumbPath,
                    'alt_text' => $altText,
                    'caption' => $caption,
                    'sort_order' => $this->nextSortOrder($owner, $collection),
                    'uploaded_by' => $uploader?->getKey(),
                ]);

                $audit($media);

                return $media;
            });
        } catch (Throwable $e) {
            $disk->delete($written);

            throw $e;
        }
    }

    private function assertOwnerPersisted(?Model $owner): void
    {
        if ($owner !== null && ! $owner->exists) {
            throw new InvalidArgumentException('Pemilik media harus sudah tersimpan.');
        }
    }

    /**
     * Pertahanan berlapis di luar Form Request (12 §3.2, §5.6): teks alternatif, MIME nyata,
     * ukuran, dan dimensi. Semua galat dikumpulkan lalu dilempar sekali sebagai ValidationException.
     *
     * @param  array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}  $config
     */
    private function assertAcceptable(UploadedFile $file, string $collection, array $config, string $altText, ?string $caption): void
    {
        $errors = [];
        $fileError = $this->fileError($file, $collection, $config);

        if ($fileError !== null) {
            $errors['file'] = [$fileError];
        }

        $errors = array_merge($errors, $this->metaErrors($altText, $caption));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function metaErrors(string $altText, ?string $caption): array
    {
        $errors = [];

        if ($altText === '') {
            $errors['alt_text'] = ['Teks alternatif gambar wajib diisi.'];
        } elseif (mb_strlen($altText) > 255) {
            $errors['alt_text'] = ['Teks alternatif gambar maksimal 255 karakter.'];
        }

        if ($caption !== null && mb_strlen($caption) > 255) {
            $errors['caption'] = ['Keterangan gambar maksimal 255 karakter.'];
        }

        return $errors;
    }

    /**
     * MIME dari isi berkas (finfo), ukuran, lalu dimensi lewat getimagesize() sebelum dekode.
     *
     * @param  array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}  $config
     */
    private function fileError(UploadedFile $file, string $collection, array $config): ?string
    {
        $messages = ImageRules::messages('file', $collection);

        if (! $file->isValid()) {
            return $messages['file.uploaded'];
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return ImageRules::INVALID_IMAGE;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($mime === false || ! isset(self::EXTENSIONS[$mime])) {
            return $messages['file.mimetypes'];
        }

        if ((int) filesize($path) > $config['max_kb'] * 1024) {
            return $messages['file.max'];
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return ImageRules::INVALID_IMAGE;
        }

        [$width, $height] = $info;

        if ($width < $config['min'][0] || $height < $config['min'][1] || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            return $messages['file.dimensions'];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function pathsOf(Media $media): array
    {
        return array_values(array_filter([
            $media->directory.'/'.$media->file_name,
            $media->thumb_path,
        ]));
    }

    /**
     * Hapus baris lalu berkas tanpa audit (jalur internal: audit sudah dicatat pemanggil).
     */
    private function purge(Media $media): void
    {
        $paths = $this->pathsOf($media);
        $disk = $media->disk;

        $media->delete();
        Storage::disk($disk)->delete($paths);
    }

    /**
     * @return Builder<Media>
     */
    private function ownedBy(Model $owner, string $collection): Builder
    {
        return Media::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->where('collection', $collection)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function encode(ImageInterface $image, string $mime): EncodedImageInterface
    {
        return match ($mime) {
            'image/jpeg' => $image->toJpeg(quality: self::JPEG_QUALITY),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: self::WEBP_QUALITY),
            default => throw new InvalidArgumentException("Tipe gambar tidak didukung: {$mime}."),
        };
    }

    /**
     * Nama asli hanya metadata: basename, huruf/angka/spasi/._()- saja, maks 150 karakter.
     */
    private function sanitizeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N} ._()\-]/u', '', $name) ?? '';
        $name = preg_replace('/\.{2,}/', '.', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim(mb_substr(trim($name, ' .'), 0, 150));

        return $name === '' ? 'gambar' : $name;
    }

    private function nextSortOrder(?Model $owner, string $collection): int
    {
        $query = Media::query()->where('collection', $collection);

        if ($owner === null) {
            $query->whereNull('model_type')->whereNull('model_id');
        } else {
            $query->where('model_type', $owner->getMorphClass())->where('model_id', $owner->getKey());
        }

        return ((int) $query->max('sort_order')) + 1;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}
     */
    private function configFor(string $collection): array
    {
        return self::COLLECTIONS[$collection]
            ?? throw new InvalidArgumentException("Koleksi media tidak dikenal: {$collection}.");
    }
}
