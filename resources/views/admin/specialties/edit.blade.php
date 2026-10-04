@php
    $breadcrumbItems = [
        [
            'label' => 'Poliklinik dan Spesialisasi',
            'url' => \Illuminate\Support\Facades\Route::has('admin.clinics.index') ? route('admin.clinics.index') : null,
        ],
        ['label' => 'Spesialisasi', 'url' => route('admin.specialties.index')],
        ['label' => 'Ubah '.$specialty->name],
    ];
@endphp
<x-layouts.admin title="Ubah Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.specialties._form', [
        'specialty' => $specialty,
        'action' => route('admin.specialties.update', $specialty),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
