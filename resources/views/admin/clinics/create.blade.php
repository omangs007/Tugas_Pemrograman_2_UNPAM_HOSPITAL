@php
    $breadcrumbItems = [
        ['label' => 'Poliklinik dan Spesialisasi', 'url' => route('admin.clinics.index')],
        ['label' => 'Poliklinik', 'url' => route('admin.clinics.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Poliklinik">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.clinics._form', [
        'clinic' => null,
        'specialties' => $specialties,
        'action' => route('admin.clinics.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
