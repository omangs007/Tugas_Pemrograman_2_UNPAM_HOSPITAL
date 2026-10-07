<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClinicType;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Rules\ImageRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-22 + F2-R13 (description max 30000), F2-R15 (nama field unggahan), F2-R23 (meta_title max 70).
 * Satu kelas untuk tambah dan ubah; `slug` dibuat sistem (F2-R5).
 */
class ClinicRequest extends FormRequest
{
    public const MAX_GALLERY = 20;

    public function authorize(): bool
    {
        $user = $this->user();
        $clinic = $this->route('clinic');

        if ($user === null) {
            return false;
        }

        return $clinic instanceof Clinic
            ? $user->can('update', $clinic)
            : $user->can('create', Clinic::class);
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => Str::upper(trim($code))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $clinic = $this->route('clinic');
        $clinic = $clinic instanceof Clinic ? $clinic : null;

        $rules = [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('clinics', 'name')->ignore($clinic)],
            'code' => [
                'bail',
                'required',
                'string',
                'regex:/^[A-Z]{2,5}$/',
                Rule::unique('clinics', 'code')->ignore($clinic),
                $this->codeLockRule($clinic),
            ],
            'type' => ['required', Rule::enum(ClinicType::class)],
            'is_bookable' => ['required', 'boolean', $this->supportNotBookableRule()],
            'specialty_id' => ['nullable', 'integer', Rule::exists('specialties', 'id')],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:30000'],
            'location' => ['required', 'string', 'max:100'],
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
                'name.required' => 'Nama poliklinik wajib diisi.',
                'name.string' => 'Nama poliklinik harus berupa teks.',
                'name.min' => 'Nama poliklinik minimal 3 karakter.',
                'name.max' => 'Nama poliklinik maksimal 100 karakter.',
                'name.unique' => 'Nama poliklinik sudah digunakan.',
                'code.required' => 'Kode poliklinik wajib diisi.',
                'code.string' => 'Kode poliklinik harus berupa teks.',
                'code.regex' => 'Kode poliklinik harus 2 sampai 5 huruf kapital.',
                'code.unique' => 'Kode poliklinik sudah digunakan.',
                'type.required' => 'Jenis layanan wajib dipilih.',
                'type.enum' => 'Jenis layanan tidak valid.',
                'is_bookable.required' => 'Status pendaftaran tidak valid.',
                'is_bookable.boolean' => 'Status pendaftaran tidak valid.',
                'specialty_id.integer' => 'Spesialisasi harus berupa angka bulat.',
                'specialty_id.exists' => 'Spesialisasi yang dipilih tidak tersedia.',
                'short_description.required' => 'Deskripsi singkat wajib diisi.',
                'short_description.string' => 'Deskripsi singkat harus berupa teks.',
                'short_description.max' => 'Deskripsi singkat maksimal 255 karakter.',
                'description.string' => 'Deskripsi harus berupa teks.',
                'description.max' => 'Deskripsi maksimal 30.000 karakter.',
                'location.required' => 'Lokasi wajib diisi.',
                'location.string' => 'Lokasi harus berupa teks.',
                'location.max' => 'Lokasi maksimal 100 karakter.',
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
     * Batas 20 foto berlaku untuk jumlah yang sudah ada ditambah yang baru diunggah.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateExistingCoverAlt($validator);

            $incoming = count($this->galleryFiles());

            if ($incoming === 0 || $validator->errors()->has('gallery')) {
                return;
            }

            $clinic = $this->route('clinic');
            $existing = $clinic instanceof Clinic ? $clinic->media()->where('collection', 'gallery')->count() : 0;

            if ($existing + $incoming > self::MAX_GALLERY) {
                $validator->errors()->add('gallery', 'Galeri maksimal 20 gambar.');
            }
        });
    }

    /**
     * Alt cover wajib untuk koleksi publik (V-20/V-22): bila cover sudah ada dan form mengirim alt kosong
     * tanpa berkas baru, tolak (alt lama tetap). Dengan berkas baru, `required_with:cover` sudah menjaganya.
     */
    private function validateExistingCoverAlt(Validator $validator): void
    {
        $clinic = $this->route('clinic');

        if (! $clinic instanceof Clinic || $this->coverFile() !== null || ! $this->has('cover_alt') || $this->coverAlt() !== '') {
            return;
        }

        if ($clinic->media()->where('collection', 'cover')->exists()) {
            $validator->errors()->add('cover_alt', 'Teks alternatif gambar wajib diisi.');
        }
    }

    /**
     * BR-13, I-31: true bila poliklinik sudah memiliki appointment (kode dipakai pada nomor antrean)
     * sehingga `code` tidak boleh berubah. Tes menimpanya lewat subkelas bila perlu.
     */
    protected function clinicHasAppointments(Clinic $clinic): bool
    {
        return Appointment::query()->where('clinic_id', $clinic->getKey())->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function clinicAttributes(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'code' => (string) $data['code'],
            'type' => (string) $data['type'],
            'is_bookable' => (bool) $data['is_bookable'],
            'specialty_id' => $data['specialty_id'] ?? null,
            'short_description' => (string) $data['short_description'],
            'description' => $data['description'] ?? null,
            'location' => (string) $data['location'],
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
        $alt = $this->input('cover_alt');

        return is_string($alt) ? trim($alt) : '';
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

    private function supportNotBookableRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->input('type') === ClinicType::Penunjang->value && filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $fail('Layanan penunjang tidak dapat dibuka untuk pendaftaran kunjungan.');
            }
        };
    }

    private function codeLockRule(?Clinic $clinic): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($clinic): void {
            if ($clinic !== null && $value !== $clinic->code && $this->clinicHasAppointments($clinic)) {
                $fail('Kode poliklinik tidak dapat diubah karena sudah dipakai pada nomor antrean.');
            }
        };
    }
}
