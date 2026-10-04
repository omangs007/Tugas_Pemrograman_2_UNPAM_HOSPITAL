<?php

namespace App\Http\Requests\Admin;

use App\Models\Specialty;
use App\Rules\NoHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-21: satu kelas untuk tambah dan ubah (dibedakan dari parameter route `specialty`).
 * `slug` tidak ada di form: dibuat sistem saat `creating` dan tidak berubah (F2-R5, BR-15).
 */
class SpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $specialty = $this->route('specialty');

        if ($user === null) {
            return false;
        }

        return $specialty instanceof Specialty
            ? $user->can('update', $specialty)
            : $user->can('create', Specialty::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('specialties', 'name')->ignore($this->route('specialty')),
            ],
            'description' => ['nullable', 'string', 'max:1000', new NoHtml],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama spesialisasi wajib diisi.',
            'name.string' => 'Nama spesialisasi harus berupa teks.',
            'name.min' => 'Nama spesialisasi minimal 3 karakter.',
            'name.max' => 'Nama spesialisasi maksimal 100 karakter.',
            'name.unique' => 'Nama spesialisasi sudah digunakan.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
            'description.no_html' => 'Deskripsi tidak boleh mengandung tag HTML.',
            'is_active.required' => 'Status aktif tidak valid.',
            'is_active.boolean' => 'Status aktif tidak valid.',
            'sort_order.integer' => 'Urutan harus berupa angka bulat.',
            'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
        ];
    }

    /**
     * Atribut model hasil validasi (hanya kunci yang boleh diisi form; `slug` tidak termasuk).
     *
     * @return array{name: string, description: ?string, is_active: bool, sort_order: int}
     */
    public function attributesForModel(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
