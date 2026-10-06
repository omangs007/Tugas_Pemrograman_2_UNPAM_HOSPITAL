<?php

namespace App\Http\Requests\Admin;

use App\Models\ArticleCategory;
use App\Rules\NoHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-33: satu kelas untuk tambah dan ubah (dibedakan dari parameter route `articleCategory`).
 * `slug` tidak ada di form: dibuat sistem saat `creating` dan tidak berubah (F4A-R7, BR-15).
 */
class ArticleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $category = $this->route('articleCategory');

        if ($user === null) {
            return false;
        }

        return $category instanceof ArticleCategory
            ? $user->can('update', $category)
            : $user->can('create', ArticleCategory::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('articleCategory');
        $category = $category instanceof ArticleCategory ? $category : null;

        return [
            'name' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('article_categories', 'name')->ignore($category),
            ],
            'description' => ['nullable', 'string', 'max:255', new NoHtml],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.string' => 'Nama kategori harus berupa teks.',
            'name.min' => 'Nama kategori minimal 3 karakter.',
            'name.max' => 'Nama kategori maksimal 100 karakter.',
            'name.unique' => 'Nama kategori sudah digunakan.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
            'description.no_html' => 'Deskripsi tidak boleh mengandung tag HTML.',
            'sort_order.integer' => 'Urutan harus berupa angka bulat.',
            'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
        ];
    }

    /**
     * Atribut model hasil validasi (hanya kunci yang boleh diisi form; `slug` tidak termasuk).
     *
     * @return array{name: string, description: ?string, sort_order: int}
     */
    public function categoryAttributes(): array
    {
        $data = $this->validated();
        $description = $data['description'] ?? null;

        return [
            'name' => trim((string) $data['name']),
            'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
