<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Models\Doctor;
use App\Rules\ImageRules;
use App\Rules\NoHtml;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * V-19 (F2-R6, F2-R15, F2-R23). `slug` dan `status` tidak ada di form: slug dibuat sistem dan status
 * baru selalu `active` (diubah lewat admin.doctors.status).
 */
class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Doctor::class);
    }

    /** Dokter yang sedang diubah; null pada tambah. */
    protected function currentDoctor(): ?Doctor
    {
        return null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $doctor = $this->currentDoctor();
        $keepClinicIds = $doctor === null ? [] : [(int) $doctor->clinic_id];
        $keepSpecialtyIds = $doctor === null ? [] : array_map('intval', $doctor->specialties()->pluck('specialties.id')->all());
        $chosen = array_map('strval', array_values(array_filter((array) $this->input('specialty_ids', []), 'is_scalar')));

        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'title_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[\p{L}.\s]+$/u'],
            'title_suffix' => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}0-9.,\s-]+$/u'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'clinic_id' => ['required', 'integer', $this->activeOrKept('clinics', $keepClinicIds)],
            'specialty_ids' => ['required', 'array', 'min:1', 'max:5'],
            'specialty_ids.*' => ['integer', 'distinct', $this->activeOrKept('specialties', $keepSpecialtyIds)],
            'primary_specialty_id' => ['required', 'integer', Rule::in($chosen)],
            'short_bio' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:5000', new NoHtml],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'photo' => ['nullable', ...ImageRules::for('photo')],
            'photo_alt' => ['required_with:photo', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(ImageRules::messages('photo', 'photo'), [
            'name.required' => 'Nama dokter wajib diisi.',
            'name.string' => 'Nama dokter harus berupa teks.',
            'name.min' => 'Nama dokter minimal 3 karakter.',
            'name.max' => 'Nama dokter maksimal 100 karakter.',
            'name.person_name' => 'Nama dokter hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            'title_prefix.string' => 'Awalan gelar harus berupa teks.',
            'title_prefix.max' => 'Awalan gelar maksimal 20 karakter.',
            'title_prefix.regex' => 'Awalan gelar hanya boleh berisi huruf, titik, dan spasi.',
            'title_suffix.string' => 'Akhiran gelar harus berupa teks.',
            'title_suffix.max' => 'Akhiran gelar maksimal 50 karakter.',
            'title_suffix.regex' => 'Akhiran gelar hanya boleh berisi huruf, angka, titik, koma, spasi, dan tanda hubung.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.enum' => 'Jenis kelamin tidak valid.',
            'clinic_id.required' => 'Poliklinik utama wajib dipilih.',
            'clinic_id.integer' => 'Poliklinik utama harus berupa angka bulat.',
            'clinic_id.exists' => 'Poliklinik yang dipilih tidak tersedia.',
            'specialty_ids.required' => 'Pilih minimal satu spesialisasi.',
            'specialty_ids.array' => 'Spesialisasi tidak valid.',
            'specialty_ids.min' => 'Pilih minimal satu spesialisasi.',
            'specialty_ids.max' => 'Spesialisasi maksimal 5.',
            'specialty_ids.*.integer' => 'Spesialisasi tidak valid.',
            'specialty_ids.*.distinct' => 'Spesialisasi tidak boleh dipilih dua kali.',
            'specialty_ids.*.exists' => 'Spesialisasi yang dipilih tidak tersedia.',
            'primary_specialty_id.required' => 'Spesialisasi utama wajib dipilih.',
            'primary_specialty_id.integer' => 'Spesialisasi utama harus berupa angka bulat.',
            'primary_specialty_id.in' => 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.',
            'short_bio.string' => 'Profil singkat harus berupa teks.',
            'short_bio.max' => 'Profil singkat maksimal 500 karakter.',
            'bio.string' => 'Profil lengkap harus berupa teks.',
            'bio.max' => 'Profil lengkap maksimal 5000 karakter.',
            'bio.no_html' => 'Profil lengkap tidak boleh mengandung tag HTML.',
            'sort_order.integer' => 'Urutan harus berupa angka bulat.',
            'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
            'meta_title.string' => 'Meta title harus berupa teks.',
            'meta_title.max' => 'Meta title maksimal 70 karakter.',
            'meta_description.string' => 'Meta description harus berupa teks.',
            'meta_description.max' => 'Meta description maksimal 160 karakter.',
            'photo_alt.required_with' => 'Teks alternatif foto wajib diisi.',
            'photo_alt.string' => 'Teks alternatif foto harus berupa teks.',
            'photo_alt.max' => 'Teks alternatif foto maksimal 255 karakter.',
        ]);
    }

    /**
     * Galat per-elemen `specialty_ids.N` dipusatkan ke `specialty_ids` (tanpa duplikat) agar tautan ringkasan
     * galat menuju `#specialty_ids` (fieldset) dan galat kelompok tampil sekali.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();

            foreach ($errors->getMessages() as $key => $messages) {
                if (! str_starts_with($key, 'specialty_ids.')) {
                    continue;
                }

                $errors->forget($key);

                foreach ($messages as $message) {
                    $errors->add('specialty_ids', $message);
                }
            }
        });
    }

    /**
     * Data untuk DoctorService: seluruh hasil validasi kecuali berkas foto dan teks alternatifnya.
     *
     * @return array<string, mixed>
     */
    public function doctorData(): array
    {
        $data = Arr::except($this->validated(), ['photo', 'photo_alt']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    public function photoUpload(): ?UploadedFile
    {
        $file = $this->file('photo');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function photoAlt(): ?string
    {
        $alt = $this->input('photo_alt');

        return is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
    }

    /**
     * Hanya baris aktif, ditambah baris yang sudah dimiliki dokter ini (agar data lama yang kini
     * nonaktif tidak membuat form tidak dapat disimpan).
     *
     * @param  list<int>  $keepIds
     */
    private function activeOrKept(string $table, array $keepIds): Exists
    {
        return Rule::exists($table, 'id')->where(function ($query) use ($keepIds): void {
            $query->where(function ($inner) use ($keepIds): void {
                $inner->where('is_active', true);

                if ($keepIds !== []) {
                    $inner->orWhereIn('id', $keepIds);
                }
            });
        });
    }
}
