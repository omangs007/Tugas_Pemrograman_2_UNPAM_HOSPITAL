<x-layouts.admin :title="'Ubah '.$doctor->display_name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Dokter', 'url' => route('admin.doctors.index')],
            ['label' => $doctor->display_name, 'url' => route('admin.doctors.show', $doctor)],
            ['label' => 'Ubah'],
        ]" />
    </x-slot:breadcrumb>

    @include('admin.doctors._form', [
        'doctor' => $doctor,
        'clinics' => $clinics,
        'specialties' => $specialties,
        'action' => route('admin.doctors.update', $doctor),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
