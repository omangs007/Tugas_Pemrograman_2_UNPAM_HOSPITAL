<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomCategory;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-28 + F4B-R3 (description max 30000), F4B-R4, F4B-R5 (tanpa input slug), F4B-R18 (kapasitas dan ketersediaan awal),
 * F4B-R19 (amenities satu per baris), F4B-R21 (cover dan galeri). Satu kelas untuk tambah dan ubah.
 * `beds_available` hanya divalidasi saat buat; saat ubah ketersediaan hanya lewat aksi `availability`.
 */
class RoomCategoryRequest extends FormRequest
{
    public const MAX_GALLERY = 20;

    /**
     * BR-115. Dipakai juga controller saat memeriksa ulang di bawah kunci baris.
     */
    public static function belowAvailableMessage(int $available): string
    {
        return "Kapasitas tidak boleh lebih kecil dari tempat tidur tersedia saat ini ({$available}). Perbarui ketersediaan terlebih dahulu.";
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $room = $this->route('roomCategory');

        if ($user === null) {
            return false;
        }

        return $room instanceof RoomCategory
            ? $user->can('update', $room)
            : $user->can('create', RoomCategory::class);
    }

    /**
     * F4B-R19: textarea `amenities` dipecah per baris, di-trim, dan baris kosong dibuang. Nilai yang sudah berupa larik
     * dibiarkan agar validasi per butir menolaknya.
     */
    protected function prepareForValidation(): void
    {
        $amenities = $this->input('amenities');

        if (! is_string($amenities)) {
            return;
        }

        $lines = preg_split('/\R/u', $amenities) ?: [];
        $items = array_values(array_filter(
            array_map('trim', $lines),
            fn (string $line): bool => $line !== '',
        ));

        $this->merge(['amenities' => $items]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $room = $this->route('roomCategory');
        $room = $room instanceof RoomCategory ? $room : null;

        $rules = [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('room_categories', 'name')->ignore($room)],
            'short_description' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:30000'],
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['string', 'min:2', 'max:80', 'distinct'],
            'bed_capacity' => ['bail', 'required', 'integer', 'between:1,500'],
            'sample_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'rate_unit' => ['bail', 'required', 'string', 'max:30'],
            'rate_note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'cover' => ['nullable', ...ImageRules::for('cover')],
            'cover_alt' => ['required_with:cover', 'nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array', 'max:'.self::MAX_GALLERY],
            'gallery.*' => ImageRules::for('gallery'),
        ];

        if ($room === null) {
            $rules['beds_available'] = ['bail', 'required', 'integer', 'min:0', 'lte:bed_capacity'];
        }

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
                'name.required' => 'Nama kategori kamar wajib diisi.',
                'name.string' => 'Nama kategori kamar harus berupa teks.',
                'name.min' => 'Nama kategori kamar minimal 3 karakter.',
                'name.max' => 'Nama kategori kamar maksimal 100 karakter.',
                'name.unique' => 'Nama kategori kamar sudah digunakan.',
                'short_description.required' => 'Deskripsi singkat wajib diisi.',
                'short_description.string' => 'Deskripsi singkat harus berupa teks.',
                'short_description.max' => 'Deskripsi singkat maksimal 255 karakter.',
                'description.string' => 'Deskripsi harus berupa teks.',
                'description.max' => 'Deskripsi maksimal 30.000 karakter.',
                'amenities.array' => 'Fasilitas kamar tidak valid.',
                'amenities.max' => 'Fasilitas kamar maksimal 30 butir.',
                'amenities.*.string' => 'Setiap butir fasilitas harus 2 sampai 80 karakter.',
                'amenities.*.min' => 'Setiap butir fasilitas harus 2 sampai 80 karakter.',
                'amenities.*.max' => 'Setiap butir fasilitas harus 2 sampai 80 karakter.',
                'amenities.*.distinct' => 'Butir fasilitas tidak boleh sama.',
                'bed_capacity.required' => 'Kapasitas tempat tidur wajib diisi.',
                'bed_capacity.integer' => 'Kapasitas tempat tidur harus berupa angka bulat.',
                'bed_capacity.between' => 'Kapasitas tempat tidur harus antara 1 dan 500.',
                'beds_available.required' => 'Jumlah tempat tidur tersedia wajib diisi.',
                'beds_available.integer' => 'Jumlah tempat tidur tersedia harus berupa angka bulat.',
                'beds_available.min' => 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.',
                'beds_available.lte' => 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas.',
                'sample_rate.numeric' => 'Tarif contoh harus berupa angka.',
                'sample_rate.min' => 'Tarif contoh tidak boleh negatif.',
                'sample_rate.max' => 'Tarif contoh terlalu besar.',
                'sample_rate.decimal' => 'Tarif contoh maksimal 2 angka desimal.',
                'rate_unit.required' => 'Satuan tarif wajib diisi.',
                'rate_unit.string' => 'Satuan tarif harus berupa teks.',
                'rate_unit.max' => 'Satuan tarif maksimal 30 karakter.',
                'rate_note.string' => 'Catatan tarif harus berupa teks.',
                'rate_note.max' => 'Catatan tarif maksimal 255 karakter.',
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
     * BR-115 (kapasitas tidak boleh di bawah ketersediaan saat ini), batas 20 gambar galeri (yang ada + baru),
     * dan alt cover yang kosong ditolak bila cover sudah ada (BR-132).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCapacityAgainstAvailability($validator);
            $this->validateExistingCoverAlt($validator);

            $incoming = count($this->galleryFiles());

            if ($incoming === 0 || $validator->errors()->has('gallery')) {
                return;
            }

            $room = $this->route('roomCategory');
            $existing = $room instanceof RoomCategory ? $room->media()->where('collection', 'gallery')->count() : 0;

            if ($existing + $incoming > self::MAX_GALLERY) {
                $validator->errors()->add('gallery', 'Galeri maksimal 20 gambar.');
            }
        });
    }

    private function validateCapacityAgainstAvailability(Validator $validator): void
    {
        $room = $this->route('roomCategory');

        if (! $room instanceof RoomCategory || $validator->errors()->has('bed_capacity')) {
            return;
        }

        $capacity = $this->input('bed_capacity');

        if (is_numeric($capacity) && (int) $capacity < $room->beds_available) {
            $validator->errors()->add('bed_capacity', self::belowAvailableMessage($room->beds_available));
        }
    }

    private function validateExistingCoverAlt(Validator $validator): void
    {
        $room = $this->route('roomCategory');

        if (! $room instanceof RoomCategory || $this->coverFile() !== null || ! $this->has('cover_alt') || $this->coverAlt() !== '') {
            return;
        }

        if ($room->media()->where('collection', 'cover')->exists()) {
            $validator->errors()->add('cover_alt', 'Teks alternatif gambar wajib diisi.');
        }
    }

    /**
     * Atribut model tanpa ketersediaan: `beds_available` dan `availability_updated_*` ditangani controller (F4B-R18).
     *
     * @return array<string, mixed>
     */
    public function roomCategoryAttributes(): array
    {
        $data = $this->validated();
        $amenities = $data['amenities'] ?? [];

        return [
            'name' => (string) $data['name'],
            'short_description' => (string) $data['short_description'],
            'description' => $data['description'] ?? null,
            'amenities' => $amenities === [] ? null : array_values($amenities),
            'bed_capacity' => (int) $data['bed_capacity'],
            'sample_rate' => $data['sample_rate'] ?? null,
            'rate_unit' => (string) $data['rate_unit'],
            'rate_note' => $data['rate_note'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
    }

    /**
     * Ketersediaan awal saat buat (aturan `beds_available` hanya ada pada permintaan buat).
     */
    public function initialBedsAvailable(): int
    {
        return (int) $this->validated('beds_available');
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
