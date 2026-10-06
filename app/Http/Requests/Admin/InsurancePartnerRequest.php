<?php

namespace App\Http\Requests\Admin;

use App\Models\InsurancePartner;
use App\Rules\ImageRules;
use App\Rules\NoHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-36 (BR-128, BR-129): satu kelas untuk tambah dan ubah. `slug` dibuat sistem (F4A-R7); logo bersifat tunggal
 * (`logo` dengan `logo_alt`).
 */
class InsurancePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $partner = $this->route('insurancePartner');

        if ($user === null) {
            return false;
        }

        return $partner instanceof InsurancePartner
            ? $user->can('update', $partner)
            : $user->can('create', InsurancePartner::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $partner = $this->route('insurancePartner');
        $partner = $partner instanceof InsurancePartner ? $partner : null;

        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('insurance_partners', 'name')->ignore($partner)],
            'type' => ['bail', 'required', Rule::in(InsurancePartner::TYPES)],
            'description' => ['bail', 'nullable', 'string', 'max:500', new NoHtml],
            'website_url' => ['nullable', 'string', 'url:https', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'logo' => ['nullable', ...ImageRules::for('logo')],
            'logo_alt' => ['required_with:logo', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            ImageRules::messages('logo', 'logo'),
            [
                'name.required' => 'Nama mitra wajib diisi.',
                'name.string' => 'Nama mitra harus berupa teks.',
                'name.min' => 'Nama mitra minimal 3 karakter.',
                'name.max' => 'Nama mitra maksimal 100 karakter.',
                'name.unique' => 'Nama mitra sudah digunakan.',
                'type.required' => 'Jenis mitra wajib dipilih.',
                'type.in' => 'Jenis mitra tidak valid.',
                'description.string' => 'Deskripsi harus berupa teks.',
                'description.max' => 'Deskripsi maksimal 500 karakter.',
                'description.no_html' => 'Deskripsi tidak boleh mengandung tag HTML.',
                'website_url.string' => 'Alamat situs harus berupa URL https yang valid.',
                'website_url.url' => 'Alamat situs harus berupa URL https yang valid.',
                'website_url.max' => 'Alamat situs maksimal 255 karakter.',
                'is_active.required' => 'Status aktif tidak valid.',
                'is_active.boolean' => 'Status aktif tidak valid.',
                'sort_order.integer' => 'Urutan harus berupa angka bulat.',
                'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
                'logo_alt.required_with' => 'Teks alternatif logo wajib diisi.',
                'logo_alt.string' => 'Teks alternatif logo harus berupa teks.',
                'logo_alt.max' => 'Teks alternatif logo maksimal 255 karakter.',
            ],
        );
    }

    /**
     * Alt logo wajib untuk koleksi publik (BR-132): bila logo sudah ada dan form mengirim alt kosong tanpa berkas
     * baru, tolak (alt lama tetap). Dengan berkas baru, `required_with:logo` sudah menjaganya.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $partner = $this->route('insurancePartner');

            if (! $partner instanceof InsurancePartner || $this->logoFile() !== null || ! $this->has('logo_alt') || $this->logoAlt() !== '') {
                return;
            }

            if ($partner->media()->where('collection', 'logo')->exists()) {
                $validator->errors()->add('logo_alt', 'Teks alternatif logo wajib diisi.');
            }
        }];
    }

    /**
     * Atribut model hasil validasi (tanpa `slug`).
     *
     * @return array{name: string, type: string, description: ?string, website_url: ?string, is_active: bool, sort_order: int}
     */
    public function insurancePartnerAttributes(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'type' => (string) $data['type'],
            'description' => $data['description'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    public function logoFile(): ?UploadedFile
    {
        $file = $this->file('logo');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function logoAlt(): string
    {
        return trim((string) $this->input('logo_alt'));
    }
}
