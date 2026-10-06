<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Models\ArticleCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter halaman #18 Daftar Artikel (05 hal. 18, F4A-R12): `kategori` (slug kategori yang ada) dan `q` (maksimal 100
 * karakter). Berdiri sendiri seperti DoctorIndexRequest (D-101): nilai yang tidak lolos aturan diabaikan tanpa pesan,
 * tidak pernah menjadi galat 500 atau pengalihan. `per_page` tidak dibuka di publik.
 */
final class ArticleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'kategori' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Tidak melempar: filter yang tidak valid diabaikan dan halaman tetap tampil (VAL-07).
     */
    protected function failedValidation(Validator $validator): void
    {
        //
    }

    /**
     * Hanya nilai yang lolos aturan. `kategori` yang lolos tetapi tidak ada di DB menjadi null (diabaikan).
     *
     * @return array{kategori: ?ArticleCategory, q: ?string}
     */
    public function filters(): array
    {
        $valid = $this->getValidatorInstance()->valid();

        $slug = is_string($valid['kategori'] ?? null) ? trim($valid['kategori']) : '';
        $term = is_string($valid['q'] ?? null) ? trim($valid['q']) : '';

        return [
            'kategori' => $slug === '' ? null : ArticleCategory::query()->where('slug', $slug)->first(),
            'q' => $term === '' ? null : $term,
        ];
    }
}
