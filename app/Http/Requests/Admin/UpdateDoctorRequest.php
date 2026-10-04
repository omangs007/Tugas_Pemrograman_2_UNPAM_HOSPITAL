<?php

namespace App\Http\Requests\Admin;

use App\Models\Doctor;
use Illuminate\Validation\Validator;

/**
 * V-19 untuk ubah: aturan sama dengan tambah; poliklinik dan spesialisasi dokter ini boleh dipertahankan
 * walau kini nonaktif. `slug` dan `status` tetap tidak dapat diubah dari form.
 */
class UpdateDoctorRequest extends StoreDoctorRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('update', $doctor);
    }

    /**
     * Alt foto wajib (V-19): bila foto sudah ada dan form mengirim alt kosong tanpa berkas baru, tolak (alt lama tetap).
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $doctor = $this->currentDoctor();

            if ($doctor === null || $this->photoUpload() !== null || ! $this->has('photo_alt') || $this->photoAlt() !== null) {
                return;
            }

            if ($doctor->media()->where('collection', 'photo')->exists()) {
                $validator->errors()->add('photo_alt', 'Teks alternatif foto wajib diisi.');
            }
        });
    }

    protected function currentDoctor(): ?Doctor
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor ? $doctor : null;
    }
}
