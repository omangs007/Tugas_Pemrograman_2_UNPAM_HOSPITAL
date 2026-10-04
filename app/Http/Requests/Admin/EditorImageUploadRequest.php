<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggah gambar sisipan editor rich text (12 V-42, F2-R15): field `file` + `alt_text`.
 */
class EditorImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('upload', Media::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', ...ImageRules::for('editor')],
            'alt_text' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(ImageRules::messages('file', 'editor'), [
            'file.required' => 'File gambar wajib dipilih.',
            'alt_text.required' => 'Teks alternatif gambar wajib diisi.',
            'alt_text.string' => 'Teks alternatif gambar harus berupa teks.',
            'alt_text.max' => 'Teks alternatif gambar maksimal 255 karakter.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $alt = $this->input('alt_text');

        if (is_string($alt)) {
            $this->merge(['alt_text' => trim($alt)]);
        }
    }
}
