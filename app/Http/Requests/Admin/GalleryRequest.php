<?php

namespace App\Http\Requests\Admin;

use App\Models\Gallery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-34 (bagian album): satu kelas untuk tambah dan ubah. `slug` dibuat sistem (F4A-R7); foto diunggah lewat
 * StoreGalleryPhotosRequest setelah album ada (F4A-R23).
 */
class GalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $gallery = $this->route('gallery');

        if ($user === null) {
            return false;
        }

        return $gallery instanceof Gallery
            ? $user->can('update', $gallery)
            : $user->can('create', Gallery::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_published' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul album wajib diisi.',
            'title.string' => 'Judul album harus berupa teks.',
            'title.min' => 'Judul album minimal 3 karakter.',
            'title.max' => 'Judul album maksimal 100 karakter.',
            'description.string' => 'Deskripsi album harus berupa teks.',
            'description.max' => 'Deskripsi album maksimal 500 karakter.',
            'is_published.required' => 'Status terbit tidak valid.',
            'is_published.boolean' => 'Status terbit tidak valid.',
            'sort_order.integer' => 'Urutan harus berupa angka bulat.',
            'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
        ];
    }

    /**
     * Atribut model hasil validasi (tanpa `slug`).
     *
     * @return array{title: string, description: ?string, is_published: bool, sort_order: int}
     */
    public function galleryAttributes(): array
    {
        $data = $this->validated();

        return [
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'is_published' => (bool) $data['is_published'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
