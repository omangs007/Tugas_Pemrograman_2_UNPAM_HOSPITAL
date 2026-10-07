<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Concerns\SanitizesRichText;
use App\Models\Media;
use App\Models\User;
use App\Rules\ImageRules;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use finfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
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

    /**
     * Kolom rich text yang boleh memuat gambar editor (`tabel` => [kolom]). Dipakai `prune()` agar gambar
     * tanpa pemilik yang masih dirujuk tidak dihapus. Setiap tabel dengan kolom rich text baru WAJIB
     * ditambahkan di sini (fase 4b: `facilities` dan `room_categories`, kolom `description`).
     */
    public const EDITOR_REFERENCE_COLUMNS = [
        'clinics' => ['description'],
        'pages' => ['body'],
        'articles' => ['body'],
        'facilities' => ['description'],
        'room_categories' => ['description'],
    ];

    private const ORPHAN_ROW_HOURS = 24;

    private const ORPHAN_FILE_MINUTES = 60;

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
        DB::transaction(function () use ($owner): void {
            Media::query()
                ->where('model_type', $owner->getMorphClass())
                ->where('model_id', $owner->getKey())
                ->orderBy('id')
                ->get()
                ->each(fn (Media $media) => $this->delete($media));
        });
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
     * Ikat gambar editor ke pemilik (08 §5.2 butir 2). Memindai atribut rich text pemilik (HTML yang sudah
     * disanitasi) untuk path `/storage/editor/...`: baris `editor` tanpa pemilik yang dirujuk menjadi milik
     * pemilik ini (tidak merebut milik pemilik lain), dan baris `editor` milik pemilik ini yang tidak lagi
     * dirujuk dikembalikan ke NULL dengan `updated_at` diperbarui sehingga dibersihkan `media:prune` setelah 24 jam.
     */
    public function syncEditorImages(Model $owner): void
    {
        $this->assertOwnerPersisted($owner);

        if (! in_array(SanitizesRichText::class, class_uses_recursive($owner), true)) {
            return;
        }

        $names = [];

        foreach ($owner->richTextAttributes() as $attribute) {
            $value = $owner->getAttribute($attribute);

            if (is_string($value)) {
                $names = [...$names, ...$this->editorFileNames($value)];
            }
        }

        $names = array_values(array_unique($names));

        DB::transaction(function () use ($owner, $names): void {
            if ($names !== []) {
                Media::query()
                    ->where('collection', 'editor')
                    ->whereNull('model_type')
                    ->whereNull('model_id')
                    ->whereIn('file_name', $names)
                    ->update(['model_type' => $owner->getMorphClass(), 'model_id' => $owner->getKey(), 'updated_at' => now()]);
            }

            Media::query()
                ->where('collection', 'editor')
                ->where('model_type', $owner->getMorphClass())
                ->where('model_id', $owner->getKey())
                ->when($names !== [], fn (Builder $query): Builder => $query->whereNotIn('file_name', $names))
                ->update(['model_type' => null, 'model_id' => null, 'updated_at' => now()]);
        });
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

    /**
     * Bersihkan media yatim (F1-R12): (a) baris tanpa pemilik berumur > 24 jam kecuali logo situs,
     * (b) berkas di folder koleksi disk public yang tidak tercatat dan berumur > 1 jam,
     * (c) baris yang `model_type`/`model_id`-nya terisi tetapi baris pemiliknya sudah tidak ada (tanpa batas umur;
     * dihitung dalam `orphanRows`). Pemilik dari kelas yang tidak dikenal dibiarkan.
     * Pada $dryRun hanya menghitung dan menyusun daftar; eksekusi nyata menulis audit `media.pruned`.
     */
    public function prune(bool $dryRun = false): MediaPruneReport
    {
        $logoId = $this->logoMediaId();
        $unowned = Media::query()
            ->orphaned(self::ORPHAN_ROW_HOURS)
            ->when($logoId > 0, fn (Builder $query): Builder => $query->whereKeyNot($logoId))
            ->orderBy('id')
            ->get()
            ->reject(fn (Media $media): bool => $this->isReferencedInRichText($media))
            ->values();
        $ownerless = $this->ownerlessRows();
        $rows = $unowned->concat($ownerless);
        $files = $this->orphanFiles();

        $bytes = 0;
        $items = [];

        foreach ($unowned as $media) {
            $bytes += $media->size_bytes + $this->thumbnailSize($media);
            $items[] = "Baris media #{$media->id}: {$media->directory}/{$media->file_name} ({$media->human_size})";
        }

        foreach ($ownerless as $media) {
            $bytes += $media->size_bytes + $this->thumbnailSize($media);
            $items[] = "Baris media #{$media->id} (pemilik ".class_basename($media->model_type)." #{$media->model_id} sudah tidak ada): {$media->directory}/{$media->file_name} ({$media->human_size})";
        }

        foreach ($files as $file) {
            $bytes += $file['size'];
            $items[] = "Berkas yatim: {$file['path']} (".Number::fileSize($file['size']).')';
        }

        $report = new MediaPruneReport($rows->count(), count($files), $bytes, $dryRun, $items);

        if ($dryRun) {
            return $report;
        }

        foreach ($rows as $media) {
            $this->purge($media);
        }

        Storage::disk('public')->delete(array_column($files, 'path'));

        $this->audit->log(
            'media.pruned',
            new Media,
            null,
            ['orphan_rows' => $report->orphanRows, 'orphan_files' => $report->orphanFiles, 'bytes' => $report->bytes],
            "Pembersihan media: {$report->orphanRows} baris dan {$report->orphanFiles} berkas yatim dihapus.",
            Actor::system(),
        );

        return $report;
    }

    /**
     * @return list<string>
     */
    private function editorFileNames(string $html): array
    {
        preg_match_all('~/storage/editor/(?:[^"\'\s<>?#]*/)?([^"\'\s<>/?#]+)~', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Gambar editor tanpa pemilik yang nama berkasnya masih dirujuk kolom rich text mana pun
     * (EDITOR_REFERENCE_COLUMNS), mis. gambar yang disalin lintas poliklinik atau baris lama.
     */
    private function isReferencedInRichText(Media $media): bool
    {
        if ($media->collection !== 'editor') {
            return false;
        }

        $needle = '%/storage/editor/%'.addcslashes($media->file_name, '%_\\').'%';

        foreach (self::EDITOR_REFERENCE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::table($table)->where($column, 'like', $needle)->exists()) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * Berkas di folder koleksi disk public yang tidak tercatat di `media` (berkas utama maupun thumbnail),
     * bukan berkas tersembunyi, dan berumur lebih dari 1 jam (menghindari balapan dengan unggahan berjalan).
     *
     * @return list<array{path: string, size: int}>
     */
    private function orphanFiles(): array
    {
        $tracked = [];

        foreach (Media::query()->where('disk', 'public')->get(['directory', 'file_name', 'thumb_path']) as $media) {
            $tracked[$media->directory.'/'.$media->file_name] = true;

            if ($media->thumb_path !== null) {
                $tracked[$media->thumb_path] = true;
            }
        }

        $disk = Storage::disk('public');
        $threshold = now()->subMinutes(self::ORPHAN_FILE_MINUTES)->getTimestamp();
        $orphans = [];

        foreach (self::COLLECTIONS as $collection => $config) {
            if ($config['disk'] !== 'public') {
                continue;
            }

            foreach ($disk->allFiles($collection) as $path) {
                if (isset($tracked[$path]) || str_starts_with(basename($path), '.') || $disk->lastModified($path) >= $threshold) {
                    continue;
                }

                $orphans[] = ['path' => $path, 'size' => $disk->size($path)];
            }
        }

        return $orphans;
    }

    /**
     * Baris media yang pemiliknya (menurut `model_type` + `model_id`) sudah tidak ada. Hanya tipe pemilik
     * yang kelasnya dikenal dan berupa model Eloquent yang diperiksa; tipe lain dibiarkan (tidak dapat dipastikan).
     * Pemilik dengan soft delete dianggap masih ada selama barisnya ada.
     *
     * @return Collection<int, Media>
     */
    private function ownerlessRows(): Collection
    {
        $ids = [];
        $types = Media::query()->whereNotNull('model_type')->whereNotNull('model_id')->distinct()->pluck('model_type');

        foreach ($types as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $owner = new $class;

            $found = Media::query()
                ->where('model_type', $type)
                ->whereNotNull('model_id')
                ->whereNotExists(fn ($query) => $query
                    ->select(DB::raw('1'))
                    ->from($owner->getTable())
                    ->whereColumn($owner->getQualifiedKeyName(), 'media.model_id'))
                ->pluck('id')
                ->all();

            $ids = [...$ids, ...$found];
        }

        return $ids === [] ? new Collection : Media::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    private function logoMediaId(): int
    {
        return app(SettingsService::class)->int('site.logo_media_id');
    }

    private function thumbnailSize(Media $media): int
    {
        if ($media->thumb_path === null) {
            return 0;
        }

        $disk = Storage::disk($media->disk);

        return $disk->exists($media->thumb_path) ? $disk->size($media->thumb_path) : 0;
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
