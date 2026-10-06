<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-30 + BR-121: hanya judul, subjudul, isi (kunci ber-body), dan meta yang dapat diubah. `key`, `is_system`,
 * dan `updated_by` tidak ada di `rules()` sehingga tidak pernah masuk `validated()`. HTML `body` tidak dibersihkan
 * di sini: sanitasi dilakukan trait SanitizesRichText pada event `saving` (F2-R14, D-40).
 */
class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $page = $this->route('page');

        return $user !== null && $page instanceof Page && $user->can('update', $page);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $page = $this->route('page');
        $hasBody = $page instanceof Page && $page->hasBody();

        return [
            'title' => ['bail', 'required', 'string', 'min:3', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', Rule::prohibitedIf(! $hasBody), 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul wajib diisi.',
            'title.string' => 'Judul harus berupa teks.',
            'title.min' => 'Judul minimal 3 karakter.',
            'title.max' => 'Judul maksimal 150 karakter.',
            'subtitle.string' => 'Subjudul harus berupa teks.',
            'subtitle.max' => 'Subjudul maksimal 255 karakter.',
            'body.prohibited' => 'Halaman ini tidak memiliki isi yang dapat diedit.',
            'body.string' => 'Isi halaman harus berupa teks.',
            'body.max' => 'Isi halaman maksimal 100.000 karakter.',
            'meta_title.string' => 'Meta title harus berupa teks.',
            'meta_title.max' => 'Meta title maksimal 70 karakter.',
            'meta_description.string' => 'Meta description harus berupa teks.',
            'meta_description.max' => 'Meta description maksimal 160 karakter.',
        ];
    }

    /**
     * Atribut model hasil validasi. `body` hanya disertakan untuk halaman yang memiliki isi.
     *
     * @return array<string, mixed>
     */
    public function pageAttributes(): array
    {
        $data = $this->validated();
        $page = $this->route('page');

        $attributes = [
            'title' => trim((string) $data['title']),
            'subtitle' => $this->blankToNull($data['subtitle'] ?? null),
            'meta_title' => $this->blankToNull($data['meta_title'] ?? null),
            'meta_description' => $this->blankToNull($data['meta_description'] ?? null),
        ];

        if ($page instanceof Page && $page->hasBody()) {
            $attributes['body'] = $this->blankToNull($data['body'] ?? null);
        }

        return $attributes;
    }

    private function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
