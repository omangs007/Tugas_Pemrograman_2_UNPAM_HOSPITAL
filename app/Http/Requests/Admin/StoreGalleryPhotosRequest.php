<?php

namespace App\Http\Requests\Admin;

use App\Models\Gallery;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * V-34 (bagian foto): `POST /admin/konten/galeri/{gallery}/foto`. Lima slot `photos[i]` dengan `alt_texts[i]` dan
 * `captions[i]`; maksimal 10 berkas per kirim, dan jumlah foto album ditambah yang baru maksimal
 * `Gallery::MAX_PHOTOS` (100).
 */
class StoreGalleryPhotosRequest extends FormRequest
{
    public const MAX_PER_UPLOAD = 10;

    public function authorize(): bool
    {
        $gallery = $this->route('gallery');

        return $gallery instanceof Gallery && $this->user()?->can('update', $gallery) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'photos' => ['bail', 'required', 'array', 'min:1', 'max:'.self::MAX_PER_UPLOAD],
            'photos.*' => ImageRules::for('photos'),
            'alt_texts' => ['nullable', 'array'],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:255'],
        ];

        foreach (array_keys($this->photoFiles()) as $index) {
            $rules['alt_texts.'.$index] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            ImageRules::messages('photos.*', 'photos'),
            [
                'photos.required' => 'Pilih minimal satu foto.',
                'photos.array' => 'Pilih minimal satu foto.',
                'photos.min' => 'Pilih minimal satu foto.',
                'photos.max' => 'Maksimal 10 foto per unggahan.',
                'alt_texts.array' => 'Teks alternatif setiap foto wajib diisi.',
                'alt_texts.*.required' => 'Teks alternatif setiap foto wajib diisi.',
                'alt_texts.*.string' => 'Teks alternatif setiap foto wajib diisi.',
                'alt_texts.*.max' => 'Teks alternatif maksimal 255 karakter.',
                'captions.array' => 'Keterangan foto tidak valid.',
                'captions.*.string' => 'Keterangan foto tidak valid.',
                'captions.*.max' => 'Keterangan foto maksimal 255 karakter.',
            ],
        );
    }

    /**
     * Batas 100 foto berlaku untuk jumlah yang sudah ada ditambah yang baru diunggah (V-34).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $incoming = count($this->photoFiles());

            if ($incoming === 0 || $validator->errors()->has('photos')) {
                return;
            }

            $gallery = $this->route('gallery');

            if (! $gallery instanceof Gallery) {
                return;
            }

            $existing = $gallery->media()->where('collection', 'photos')->count();

            if ($existing + $incoming > Gallery::MAX_PHOTOS) {
                $validator->errors()->add('photos', 'Album sudah mencapai batas 100 foto.');
            }
        }];
    }

    /**
     * @return array<int|string, UploadedFile>
     */
    public function photoFiles(): array
    {
        $files = $this->file('photos');

        return is_array($files) ? array_filter($files, fn ($file): bool => $file instanceof UploadedFile) : [];
    }

    public function altText(int|string $index): string
    {
        $value = $this->input('alt_texts.'.$index);

        return is_string($value) ? trim($value) : '';
    }

    public function caption(int|string $index): ?string
    {
        $value = $this->input('captions.'.$index);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
