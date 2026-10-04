<?php

namespace App\Http\Requests\Admin;

use App\Models\Doctor;

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

    protected function currentDoctor(): ?Doctor
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor ? $doctor : null;
    }
}
