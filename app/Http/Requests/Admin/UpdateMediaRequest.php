<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ubah teks alternatif dan keterangan satu gambar (12 V-42). Izin ditentukan MediaPolicy menurut pemilik.
 */
class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('media');

        return $media instanceof Media && $this->user()?->can('update', $media) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alt_text' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alt_text.required' => 'Teks alternatif gambar wajib diisi.',
            'alt_text.string' => 'Teks alternatif gambar harus berupa teks.',
            'alt_text.max' => 'Teks alternatif gambar maksimal 255 karakter.',
            'caption.string' => 'Keterangan gambar harus berupa teks.',
            'caption.max' => 'Keterangan gambar maksimal 255 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['alt_text', 'caption'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }
}
