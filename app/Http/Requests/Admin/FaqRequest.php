<?php

namespace App\Http\Requests\Admin;

use App\Models\Faq;
use App\Rules\NoHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-35 (BR-127): satu kelas untuk tambah dan ubah. Pertanyaan dan jawaban adalah teks biasa (`NoHtml`);
 * jawaban dirender dengan `{{ }}` di halaman publik.
 */
class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $faq = $this->route('faq');

        if ($user === null) {
            return false;
        }

        return $faq instanceof Faq
            ? $user->can('update', $faq)
            : $user->can('create', Faq::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => ['bail', 'required', Rule::in(Faq::CATEGORIES)],
            'question' => ['bail', 'required', 'string', 'min:5', 'max:255', new NoHtml],
            'answer' => ['bail', 'required', 'string', 'min:5', 'max:2000', new NoHtml],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'is_published' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Kategori FAQ wajib dipilih.',
            'category.in' => 'Kategori FAQ tidak valid.',
            'question.required' => 'Pertanyaan wajib diisi.',
            'question.string' => 'Pertanyaan harus berupa teks.',
            'question.min' => 'Pertanyaan minimal 5 karakter.',
            'question.max' => 'Pertanyaan maksimal 255 karakter.',
            'question.no_html' => 'Pertanyaan tidak boleh mengandung tag HTML.',
            'answer.required' => 'Jawaban wajib diisi.',
            'answer.string' => 'Jawaban harus berupa teks.',
            'answer.min' => 'Jawaban minimal 5 karakter.',
            'answer.max' => 'Jawaban maksimal 2000 karakter.',
            'answer.no_html' => 'Jawaban tidak boleh mengandung tag HTML.',
            'sort_order.integer' => 'Urutan harus berupa angka bulat.',
            'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
            'is_published.required' => 'Status terbit tidak valid.',
            'is_published.boolean' => 'Status terbit tidak valid.',
        ];
    }

    /**
     * Atribut model hasil validasi.
     *
     * @return array{category: string, question: string, answer: string, sort_order: int, is_published: bool}
     */
    public function faqAttributes(): array
    {
        $data = $this->validated();

        return [
            'category' => (string) $data['category'],
            'question' => (string) $data['question'],
            'answer' => (string) $data['answer'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_published' => (bool) $data['is_published'],
        ];
    }
}
