<x-layouts.admin :title="'Ubah '.$patient->full_name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Pasien', 'url' => route('admin.patients.index')],
            ['label' => $patient->full_name.' ('.($patient->patient_number ?? '-').')', 'url' => route('admin.patients.show', $patient)],
            ['label' => 'Ubah'],
        ]" />
    </x-slot:breadcrumb>

    @include('admin.patients._form', [
        'patient' => $patient,
        'action' => route('admin.patients.update', $patient),
        'submitLabel' => 'Simpan Perubahan',
        'cancelUrl' => route('admin.patients.show', $patient),
        'kembali' => null,
        'similar' => collect(),
        'pickUrls' => [],
    ])
</x-layouts.admin>
