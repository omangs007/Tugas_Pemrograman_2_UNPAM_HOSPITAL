<?php

namespace App\Http\Requests\Admin;

use App\Models\Doctor;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * V-20 (ganti foto): `photo` wajib (aturan koleksi `photo`) dan `alt_text` wajib (BR-132).
 */
class UploadDoctorPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('update', $doctor);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', ...ImageRules::for('photo')],
            'alt_text' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(ImageRules::messages('photo', 'photo'), [
            'photo.required' => 'Foto wajib dipilih.',
            'alt_text.required' => 'Teks alternatif foto wajib diisi.',
            'alt_text.string' => 'Teks alternatif foto harus berupa teks.',
            'alt_text.max' => 'Teks alternatif foto maksimal 255 karakter.',
        ]);
    }

    public function photoFile(): UploadedFile
    {
        $file = $this->file('photo');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('photoFile() hanya dipanggil setelah validasi lolos.');
        }

        return $file;
    }

    public function altText(): string
    {
        return trim((string) $this->validated('alt_text'));
    }
}
