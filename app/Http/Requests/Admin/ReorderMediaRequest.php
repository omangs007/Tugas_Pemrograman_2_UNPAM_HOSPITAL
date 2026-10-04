<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Urutkan ulang gambar (12 V-42). Pemilik dan koleksi diturunkan dari id media, bukan dari input.
 */
class ReorderMediaRequest extends FormRequest
{
    /** @var Collection<int, Media>|null */
    private ?Collection $items = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $ids = $this->input('ids');

        if (! is_array($ids)) {
            return true; // gagal di validasi (422), bukan 403
        }

        $numeric = collect($ids)
            ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(100)
            ->values();

        if ($numeric->isEmpty()) {
            return true;
        }

        // Setiap media yang ada harus boleh diurutkan oleh pengguna; id yang tidak ada ditolak validasi (422).
        return Media::query()->whereIn('id', $numeric)->get()->every(fn (Media $media) => $user->can('reorder', $media));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', Rule::exists('media', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Daftar gambar wajib diisi.',
            'ids.array' => 'Daftar gambar wajib diisi.',
            'ids.min' => 'Daftar gambar wajib diisi.',
            'ids.max' => 'Maksimal 100 gambar dapat diurutkan sekaligus.',
            'ids.*.integer' => 'Urutan gambar tidak valid.',
            'ids.*.distinct' => 'Urutan gambar tidak valid.',
            'ids.*.exists' => 'Gambar tidak ditemukan.',
        ];
    }

    /**
     * Semua gambar harus berpemilik dan berasal dari pemilik serta koleksi yang sama.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = Media::query()
                ->with('model')
                ->whereIn('id', array_map('intval', (array) $this->input('ids')))
                ->get();
            $first = $items->first();

            $consistent = $first !== null
                && $first->model_type !== null
                && $first->model !== null
                && $items->every(fn (Media $media) => $media->model_type === $first->model_type
                    && (int) $media->model_id === (int) $first->model_id
                    && $media->collection === $first->collection);

            if (! $consistent) {
                $validator->errors()->add('ids', 'Urutan gambar tidak valid.');

                return;
            }

            $this->items = $items;
        }];
    }

    public function owner(): Model
    {
        return $this->resolved()->first()->model;
    }

    public function collection(): string
    {
        return $this->resolved()->first()->collection;
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map('intval', $this->validated('ids')));
    }

    /**
     * @return Collection<int, Media>
     */
    private function resolved(): Collection
    {
        return $this->items ?? throw new \LogicException('ReorderMediaRequest dipakai sebelum validasi lolos.');
    }
}
