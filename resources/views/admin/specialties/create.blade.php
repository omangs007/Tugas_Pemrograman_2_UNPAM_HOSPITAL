@php
    $breadcrumbItems = [
        [
            'label' => 'Poliklinik dan Spesialisasi',
            'url' => \Illuminate\Support\Facades\Route::has('admin.clinics.index') ? route('admin.clinics.index') : null,
        ],
        ['label' => 'Spesialisasi', 'url' => route('admin.specialties.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.specialties._form', [
        'specialty' => null,
        'action' => route('admin.specialties.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
