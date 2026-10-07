<?php

namespace App\Http\Requests\Admin;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-27 + F4B-R3 (description max 30000), F4B-R4 (panjang field), F4B-R5 (tanpa input slug), F4B-R21 (cover dan galeri).
 * Satu kelas untuk tambah dan ubah, dibedakan dari `$this->route('facility')`.
 */
class FacilityRequest extends FormRequest
{
    public const MAX_GALLERY = 20;

    public function authorize(): bool
    {
        $user = $this->user();
        $facility = $this->route('facility');

        if ($user === null) {
            return false;
        }

        return $facility instanceof Facility
            ? $user->can('update', $facility)
            : $user->can('create', Facility::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $facility = $this->route('facility');
        $facility = $facility instanceof Facility ? $facility : null;

        $rules = [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('facilities', 'name')->ignore($facility)],
            'category' => ['bail', 'required', Rule::enum(FacilityCategory::class)],
            'short_description' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:30000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'cover' => ['nullable', ...ImageRules::for('cover')],
            'cover_alt' => ['required_with:cover', 'nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array', 'max:'.self::MAX_GALLERY],
            'gallery.*' => ImageRules::for('gallery'),
        ];

        foreach (array_keys($this->galleryFiles()) as $index) {
            $rules['gallery_alt.'.$index] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            ImageRules::messages('cover', 'cover'),
            ImageRules::messages('gallery.*', 'gallery'),
            [
                'name.required' => 'Nama fasilitas wajib diisi.',
                'name.string' => 'Nama fasilitas harus berupa teks.',
                'name.min' => 'Nama fasilitas minimal 3 karakter.',
                'name.max' => 'Nama fasilitas maksimal 100 karakter.',
                'name.unique' => 'Nama fasilitas sudah digunakan.',
                'category.required' => 'Kategori fasilitas wajib dipilih.',
                'category.enum' => 'Kategori fasilitas tidak valid.',
                'short_description.required' => 'Deskripsi singkat wajib diisi.',
                'short_description.string' => 'Deskripsi singkat harus berupa teks.',
                'short_description.max' => 'Deskripsi singkat maksimal 255 karakter.',
                'description.string' => 'Deskripsi harus berupa teks.',
                'description.max' => 'Deskripsi maksimal 30.000 karakter.',
                'is_active.required' => 'Status aktif tidak valid.',
                'is_active.boolean' => 'Status aktif tidak valid.',
                'sort_order.integer' => 'Urutan harus berupa angka bulat.',
                'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
                'meta_title.string' => 'Meta title harus berupa teks.',
                'meta_title.max' => 'Meta title maksimal 70 karakter.',
                'meta_description.string' => 'Meta description harus berupa teks.',
                'meta_description.max' => 'Meta description maksimal 160 karakter.',
                'cover_alt.required_with' => 'Teks alternatif gambar wajib diisi.',
                'cover_alt.string' => 'Teks alternatif gambar harus berupa teks.',
                'cover_alt.max' => 'Teks alternatif gambar maksimal 255 karakter.',
                'gallery.array' => 'Galeri tidak valid.',
                'gallery.max' => 'Galeri maksimal 20 gambar.',
                'gallery_alt.*.required' => 'Teks alternatif gambar wajib diisi.',
                'gallery_alt.*.string' => 'Teks alternatif gambar harus berupa teks.',
                'gallery_alt.*.max' => 'Teks alternatif gambar maksimal 255 karakter.',
            ],
        );
    }

    /**
     * Batas 20 gambar berlaku untuk jumlah yang sudah ada ditambah yang baru diunggah (F4B-R21).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateExistingCoverAlt($validator);

            $incoming = count($this->galleryFiles());

            if ($incoming === 0 || $validator->errors()->has('gallery')) {
                return;
            }

            $facility = $this->route('facility');
            $existing = $facility instanceof Facility ? $facility->media()->where('collection', 'gallery')->count() : 0;

            if ($existing + $incoming > self::MAX_GALLERY) {
                $validator->errors()->add('gallery', 'Galeri maksimal 20 gambar.');
            }
        });
    }

    /**
     * Alt cover wajib untuk koleksi publik (BR-132): bila cover sudah ada dan form mengirim alt kosong tanpa berkas
     * baru, tolak (alt lama tetap). Dengan berkas baru, `required_with:cover` sudah menjaganya.
     */
    private function validateExistingCoverAlt(Validator $validator): void
    {
        $facility = $this->route('facility');

        if (! $facility instanceof Facility || $this->coverFile() !== null || ! $this->has('cover_alt') || $this->coverAlt() !== '') {
            return;
        }

        if ($facility->media()->where('collection', 'cover')->exists()) {
            $validator->errors()->add('cover_alt', 'Teks alternatif gambar wajib diisi.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function facilityAttributes(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'category' => (string) $data['category'],
            'short_description' => (string) $data['short_description'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
    }

    public function coverFile(): ?UploadedFile
    {
        $file = $this->file('cover');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function coverAlt(): string
    {
        return trim((string) $this->input('cover_alt'));
    }

    /**
     * @return array<int|string, UploadedFile>
     */
    public function galleryFiles(): array
    {
        $files = $this->file('gallery');

        return is_array($files) ? array_filter($files, fn ($file): bool => $file instanceof UploadedFile) : [];
    }

    public function galleryAlt(int|string $index): string
    {
        return trim((string) $this->input('gallery_alt.'.$index));
    }
}
