@php
    $rootUrl = \Illuminate\Support\Facades\Route::has('admin.room-categories.index') ? route('admin.room-categories.index') : route('admin.facilities.index');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => $rootUrl],
        ['label' => 'Fasilitas', 'url' => route('admin.facilities.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Fasilitas">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.facilities._form', [
        'facility' => null,
        'action' => route('admin.facilities.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
