<x-layouts.admin title="Tambah Pasien">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Pasien', 'url' => route('admin.patients.index')],
            ['label' => 'Tambah Pasien'],
        ]" />
    </x-slot:breadcrumb>

    @include('admin.patients._form', [
        'patient' => null,
        'action' => route('admin.patients.store'),
        'submitLabel' => 'Simpan Profil',
        'cancelUrl' => $kembali !== null ? url($kembali) : route('admin.patients.index'),
        'kembali' => $kembali,
        'similar' => $similar,
        'pickUrls' => $pickUrls,
    ])
</x-layouts.admin>
