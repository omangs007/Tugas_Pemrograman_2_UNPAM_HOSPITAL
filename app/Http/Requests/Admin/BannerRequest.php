<?php

namespace App\Http\Requests\Admin;

use App\Models\Banner;
use App\Rules\ImageRules;
use App\Rules\InternalOrHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * V-31 + BR-124 + F4A-R26. Satu kelas untuk tambah dan ubah (dibedakan dari parameter route `banner`).
 * `image` wajib saat tambah; saat ubah opsional (alt tanpa berkas memperbarui gambar yang ada, D-57).
 * `starts_at`/`ends_at` dikirim sebagai `datetime-local` (`Y-m-d\TH:i`) dan ditafsirkan dalam zona aplikasi.
 */
class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $banner = $this->route('banner');

        if ($user === null) {
            return false;
        }

        return $banner instanceof Banner
            ? $user->can('update', $banner)
            : $user->can('create', Banner::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $banner = $this->route('banner');

        return [
            'title' => ['bail', 'required', 'string', 'min:3', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'button_label' => ['nullable', 'required_with:button_url', 'string', 'max:30'],
            'button_url' => ['nullable', 'required_with:button_label', 'string', 'max:255', new InternalOrHttpsUrl],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => $this->endsAtRules(),
            'image' => $banner instanceof Banner
                ? ['nullable', ...ImageRules::for('image')]
                : ['bail', 'required', ...ImageRules::for('image')],
            'image_alt' => ['required_with:image', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * `after:starts_at` hanya dipasang bila `starts_at` terisi dan dapat diparse; tanpa itu Laravel membandingkan
     * dengan teks harfiah "starts_at".
     *
     * @return array<int, string>
     */
    private function endsAtRules(): array
    {
        $rules = ['nullable', 'date'];
        $start = $this->input('starts_at');

        if (is_string($start) && trim($start) !== '' && strtotime($start) !== false) {
            $rules[] = 'after:starts_at';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            ImageRules::messages('image', 'image'),
            [
                'title.required' => 'Judul banner wajib diisi.',
                'title.string' => 'Judul banner harus berupa teks.',
                'title.min' => 'Judul banner minimal 3 karakter.',
                'title.max' => 'Judul banner maksimal 100 karakter.',
                'subtitle.string' => 'Subjudul banner harus berupa teks.',
                'subtitle.max' => 'Subjudul banner maksimal 200 karakter.',
                'button_label.required_with' => 'Label tombol wajib diisi bila tautan diisi.',
                'button_label.string' => 'Label tombol harus berupa teks.',
                'button_label.max' => 'Label tombol maksimal 30 karakter.',
                'button_url.required_with' => 'Tautan tombol wajib diisi bila label diisi.',
                'button_url.string' => 'Tautan tombol harus berupa teks.',
                'button_url.max' => 'Tautan tombol maksimal 255 karakter.',
                'sort_order.integer' => 'Urutan harus berupa angka bulat.',
                'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
                'is_active.required' => 'Status aktif tidak valid.',
                'is_active.boolean' => 'Status aktif tidak valid.',
                'starts_at.date' => 'Format waktu mulai tayang tidak valid.',
                'ends_at.date' => 'Format waktu selesai tayang tidak valid.',
                'ends_at.after' => 'Waktu selesai tayang harus setelah waktu mulai tayang.',
                'image.required' => 'Gambar banner wajib dipilih.',
                'image_alt.required_with' => 'Teks alternatif banner wajib diisi.',
                'image_alt.string' => 'Teks alternatif banner harus berupa teks.',
                'image_alt.max' => 'Teks alternatif banner maksimal 255 karakter.',
            ],
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateExistingImageAlt($validator);
        });
    }

    /**
     * Alt gambar wajib untuk koleksi publik (BR-132): bila banner sudah punya gambar dan form mengirim alt kosong
     * tanpa berkas baru, tolak (alt lama tetap). Dengan berkas baru, `required_with:image` sudah menjaganya.
     */
    private function validateExistingImageAlt(Validator $validator): void
    {
        $banner = $this->route('banner');

        if (! $banner instanceof Banner || $this->imageFile() !== null || ! $this->has('image_alt') || $this->imageAlt() !== '') {
            return;
        }

        if ($banner->media()->where('collection', 'image')->exists()) {
            $validator->errors()->add('image_alt', 'Teks alternatif banner wajib diisi.');
        }
    }

    /**
     * Atribut model hasil validasi (hanya kunci yang boleh diisi form).
     *
     * @return array<string, mixed>
     */
    public function bannerAttributes(): array
    {
        $data = $this->validated();

        return [
            'title' => trim((string) $data['title']),
            'subtitle' => $this->blankToNull($data['subtitle'] ?? null),
            'button_label' => $this->blankToNull($data['button_label'] ?? null),
            'button_url' => $this->blankToNull($data['button_url'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) $data['is_active'],
            'starts_at' => $this->blankToNull($data['starts_at'] ?? null),
            'ends_at' => $this->blankToNull($data['ends_at'] ?? null),
        ];
    }

    public function imageFile(): ?UploadedFile
    {
        $file = $this->file('image');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function imageAlt(): string
    {
        $alt = $this->input('image_alt');

        return is_string($alt) ? trim($alt) : '';
    }

    private function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
