<x-layouts.admin title="Tambah Dokter">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Dokter', 'url' => route('admin.doctors.index')],
            ['label' => 'Tambah Dokter'],
        ]" />
    </x-slot:breadcrumb>

    @include('admin.doctors._form', [
        'doctor' => null,
        'clinics' => $clinics,
        'specialties' => $specialties,
        'action' => route('admin.doctors.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
