<?php

namespace App\Http\Requests;

use App\Support\SafeRedirect;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Basis permintaan GET-filter longgar (12 V-45): parameter yang tidak valid diabaikan,
 * tidak pernah menimbulkan galat 500 atau pengalihan galat validasi (00 I.5).
 */
class ListFilterRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    /** @var array<string, string>|null */
    private ?array $resolvedFilters = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', Rule::in(self::PER_PAGE_OPTIONS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'kembali' => ['nullable', 'string', 'max:2000'],
        ];

        $statuses = $this->statusValues();

        if ($statuses !== []) {
            $rules['status'] = ['nullable', Rule::in($statuses)];
        }

        $sortable = $this->sortable();

        if ($sortable !== []) {
            $rules['urut'] = ['nullable', Rule::in(array_merge(
                $sortable,
                array_map(fn (string $column): string => '-'.$column, $sortable),
            ))];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.string' => 'Kata kunci tidak valid.',
            'q.max' => 'Kata kunci maksimal 100 karakter.',
            'status.in' => 'Status tidak valid.',
            'kategori.string' => 'Pilihan filter tidak valid.',
            'kategori.max' => 'Pilihan filter tidak valid.',
            'urut.in' => 'Urutan tidak valid.',
            'per_page.in' => 'Jumlah baris per halaman tidak valid.',
            'page.integer' => 'Nomor halaman tidak valid.',
            'page.min' => 'Nomor halaman tidak valid.',
        ];
    }

    /**
     * Nilai `status` yang sah untuk entitas layar ini. Kosong = parameter `status` diabaikan.
     *
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return [];
    }

    /**
     * Kolom yang boleh diurutkan lewat `urut` (awalan "-" = menurun). Kosong = parameter `urut` diabaikan.
     *
     * @return list<string>
     */
    protected function sortable(): array
    {
        return [];
    }

    /**
     * Tidak melempar: parameter yang gagal hanya diabaikan dan pengguna diberi tahu lewat flash
     * `warning` pada request yang sama (tidak bertahan ke request berikutnya).
     */
    protected function failedValidation(Validator $validator): void
    {
        session()->now('warning', 'Filter tidak valid dan diabaikan.');
    }

    /**
     * Hanya parameter yang lolos aturan. Validator::valid() mengembalikan SELURUH input minus yang gagal,
     * sehingga hasilnya diiris ke kunci yang memang memiliki aturan.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        if ($this->resolvedFilters !== null) {
            return $this->resolvedFilters;
        }

        $valid = $this->getValidatorInstance()->valid();
        $filters = [];

        foreach (array_keys($this->rules()) as $key) {
            if (! array_key_exists($key, $valid)) {
                continue;
            }

            $value = $valid[$key];

            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $filters[$key] = (string) $value;
        }

        if (isset($filters['kembali'])) {
            $path = SafeRedirect::internalPath($filters['kembali']);

            if ($path === null) {
                unset($filters['kembali']);
            } else {
                $filters['kembali'] = $path;
            }
        }

        return $this->resolvedFilters = $filters;
    }

    public function perPage(int $default = 25): int
    {
        $value = $this->filters()['per_page'] ?? null;

        return $value === null ? $default : (int) $value;
    }
}
