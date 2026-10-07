@php
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => route('admin.room-categories.index')],
        ['label' => 'Kamar dan Kapasitas', 'url' => route('admin.room-categories.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Kategori Kamar">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.room-categories._form', [
        'room' => null,
        'action' => route('admin.room-categories.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
