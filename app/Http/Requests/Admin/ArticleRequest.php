<?php

namespace App\Http\Requests\Admin;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Rules\ImageRules;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-32 + F4A-R10 + F4A-R11. Satu kelas untuk tambah dan ubah (dibedakan dari parameter route `article`).
 * `slug` dan `author_user_id` tidak ada di `rules()`: slug dibuat sistem (F4A-R7) dan penulis diisi controller.
 * HTML `body` dibersihkan oleh trait SanitizesRichText pada event `saving`; di sini hanya ditolak bila kosong
 * setelah sanitasi, karena `articles.body` NOT NULL (D-58 tidak dapat berlaku).
 */
class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $article = $this->route('article');

        if ($user === null) {
            return false;
        }

        return $article instanceof Article
            ? $user->can('update', $article)
            : $user->can('create', Article::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', 'min:5', 'max:150'],
            'article_category_id' => ['nullable', 'integer', Rule::exists('article_categories', 'id')],
            'excerpt' => ['bail', 'required', 'string', 'max:300'],
            'body' => ['bail', 'required', 'string', 'min:50', 'max:200000'],
            'status' => ['bail', 'required', Rule::enum(ArticleStatus::class)],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'cover' => ['nullable', ...ImageRules::for('cover')],
            'cover_alt' => ['required_with:cover', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            ImageRules::messages('cover', 'cover'),
            [
                'title.required' => 'Judul artikel wajib diisi.',
                'title.string' => 'Judul artikel harus berupa teks.',
                'title.min' => 'Judul artikel minimal 5 karakter.',
                'title.max' => 'Judul artikel maksimal 150 karakter.',
                'article_category_id.integer' => 'Kategori artikel tidak valid.',
                'article_category_id.exists' => 'Kategori artikel yang dipilih tidak tersedia.',
                'excerpt.required' => 'Ringkasan artikel wajib diisi.',
                'excerpt.string' => 'Ringkasan artikel harus berupa teks.',
                'excerpt.max' => 'Ringkasan artikel maksimal 300 karakter.',
                'body.required' => 'Isi artikel wajib diisi.',
                'body.string' => 'Isi artikel harus berupa teks.',
                'body.min' => 'Isi artikel minimal 50 karakter.',
                'body.max' => 'Isi artikel maksimal 200.000 karakter.',
                'status.required' => 'Status artikel wajib dipilih.',
                'status.enum' => 'Status artikel tidak valid.',
                'published_at.date' => 'Format tanggal terbit tidak valid.',
                'meta_title.string' => 'Meta title harus berupa teks.',
                'meta_title.max' => 'Meta title maksimal 70 karakter.',
                'meta_description.string' => 'Meta description harus berupa teks.',
                'meta_description.max' => 'Meta description maksimal 160 karakter.',
                'cover_alt.required_with' => 'Teks alternatif gambar sampul wajib diisi.',
                'cover_alt.string' => 'Teks alternatif gambar sampul harus berupa teks.',
                'cover_alt.max' => 'Teks alternatif gambar sampul maksimal 255 karakter.',
            ],
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateBodyIsNotBlankAfterSanitizing($validator);
            $this->validateExistingCoverAlt($validator);
        });
    }

    /**
     * F4A-R11: isi yang lolos `min:50` tetapi kosong secara visual setelah sanitasi (mis. hanya `&nbsp;` atau
     * hanya `<script>`) ditolak dengan pesan yang sama dengan isi kosong.
     */
    private function validateBodyIsNotBlankAfterSanitizing(Validator $validator): void
    {
        if ($validator->errors()->has('body')) {
            return;
        }

        $body = $this->input('body');

        if (! is_string($body)) {
            return;
        }

        if (Article::isBlankRichText(app(HtmlSanitizer::class)->clean($body))) {
            $validator->errors()->add('body', 'Isi artikel wajib diisi.');
        }
    }

    /**
     * Alt cover wajib untuk koleksi publik (BR-132): bila artikel sudah punya cover dan form mengirim alt kosong
     * tanpa berkas baru, tolak (alt lama tetap). Dengan berkas baru, `required_with:cover` sudah menjaganya.
     */
    private function validateExistingCoverAlt(Validator $validator): void
    {
        $article = $this->route('article');

        if (! $article instanceof Article || $this->coverFile() !== null || ! $this->has('cover_alt') || $this->coverAlt() !== '') {
            return;
        }

        if ($article->media()->where('collection', 'cover')->exists()) {
            $validator->errors()->add('cover_alt', 'Teks alternatif gambar sampul wajib diisi.');
        }
    }

    /**
     * Atribut model hasil validasi (hanya kunci yang boleh diisi form; `slug` dan `author_user_id` tidak termasuk).
     * `published_at` (F4A-R10):
     *   - terbit tanpa tanggal → `now()`;
     *   - tanggal yang sama menitnya dengan nilai tersimpan → tidak ditulis ulang (detik tidak hilang);
     *   - draf tanpa tanggal → kolom tidak disentuh (kembali ke draf tidak mengosongkan `published_at`).
     *
     * @return array<string, mixed>
     */
    public function articleAttributes(): array
    {
        $data = $this->validated();
        $article = $this->route('article');
        $status = ArticleStatus::from((string) $data['status']);
        $publishedAt = $this->blankToNull($data['published_at'] ?? null);

        $attributes = [
            'article_category_id' => filled($data['article_category_id'] ?? null) ? (int) $data['article_category_id'] : null,
            'title' => trim((string) $data['title']),
            'excerpt' => trim((string) $data['excerpt']),
            'body' => (string) $data['body'],
            'status' => $status,
            'meta_title' => $this->blankToNull($data['meta_title'] ?? null),
            'meta_description' => $this->blankToNull($data['meta_description'] ?? null),
        ];

        if ($publishedAt === null && $status === ArticleStatus::Published) {
            $attributes['published_at'] = now();
        } elseif ($publishedAt !== null && ! $this->sameMinuteAsStored($article, $publishedAt)) {
            $attributes['published_at'] = $publishedAt;
        }

        return $attributes;
    }

    public function coverFile(): ?UploadedFile
    {
        $file = $this->file('cover');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function coverAlt(): string
    {
        $alt = $this->input('cover_alt');

        return is_string($alt) ? trim($alt) : '';
    }

    private function sameMinuteAsStored(mixed $article, string $submitted): bool
    {
        return $article instanceof Article
            && $article->published_at !== null
            && $article->published_at->format('Y-m-d\TH:i') === $submitted;
    }

    private function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
