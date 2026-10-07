<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-38 (F4B-R24): tujuan status `read`, `replied`, atau `archived`. Pesan tidak dapat kembali ke `new`.
 */
class UpdateContactMessageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $message = $this->route('message');

        return $user !== null
            && $message instanceof ContactMessage
            && $user->can('updateStatus', $message);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['bail', 'required', Rule::in(['read', 'replied', 'archived'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status pesan wajib dipilih.',
            'status.in' => 'Status pesan tidak valid.',
        ];
    }

    public function statusValue(): ContactMessageStatus
    {
        return ContactMessageStatus::from((string) $this->validated('status'));
    }
}
