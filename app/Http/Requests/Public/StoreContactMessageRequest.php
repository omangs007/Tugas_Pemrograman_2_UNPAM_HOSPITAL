<?php

namespace App\Http\Requests\Public;

use App\Rules\IndonesianPhone;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-37 (F4B-R34, F4B-R35): form pesan kontak publik, tanpa login. Field jebakan `website` sengaja tidak ada di
 * `rules()`; controller memeriksanya lewat `isHoneypotFilled()` (F4B-R7). Field larik menghasilkan galat berbahasa
 * Indonesia (aturan `string` mendahului aturan lain), bukan 500.
 */
class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Memangkas spasi tepi pada field teks (nilai bukan string dibiarkan agar divalidasi sebagai galat). */
    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name', 'email', 'phone', 'subject', 'message'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed[$field] = trim($value);
            }
        }

        $this->merge($trimmed);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
            'phone' => ['nullable', new IndonesianPhone],
            'subject' => ['bail', 'required', 'string', 'min:5', 'max:150'],
            'message' => ['bail', 'required', 'string', 'min:10', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.min' => 'Nama minimal 3 karakter.',
            'name.max' => 'Nama maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'subject.required' => 'Subjek wajib diisi.',
            'subject.string' => 'Subjek harus berupa teks.',
            'subject.min' => 'Subjek minimal 5 karakter.',
            'subject.max' => 'Subjek maksimal 150 karakter.',
            'message.required' => 'Pesan wajib diisi.',
            'message.string' => 'Pesan harus berupa teks.',
            'message.min' => 'Pesan minimal 10 karakter.',
            'message.max' => 'Pesan maksimal 2000 karakter.',
            'consent.accepted' => 'Anda harus menyetujui pengolahan data untuk mengirim pesan.',
        ];
    }

    /**
     * Nama bidang untuk pesan aturan kustom (`PersonName` memakai `:Attribute`).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor telepon',
            'subject' => 'Subjek',
            'message' => 'Pesan',
        ];
    }

    /** Bidang jebakan `website` terisi (F4B-R7): nilai apa pun yang tidak kosong, termasuk larik. */
    public function isHoneypotFilled(): bool
    {
        return filled($this->input('website'));
    }

    /**
     * Atribut pesan hasil validasi; telepon disimpan dalam bentuk ternormalisasi atau NULL (F4B-R34).
     *
     * @return array{name: string, email: string, phone: ?string, subject: string, message: string}
     */
    public function messageAttributes(): array
    {
        $data = $this->validated();
        $phone = $data['phone'] ?? null;

        return [
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => is_string($phone) && $phone !== '' ? IndonesianPhone::normalize($phone) : null,
            'subject' => (string) $data['subject'],
            'message' => (string) $data['message'],
        ];
    }
}
