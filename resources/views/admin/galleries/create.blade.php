@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Galeri', 'url' => route('admin.galleries.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Album">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.galleries._form', [
        'gallery' => null,
        'action' => route('admin.galleries.store'),
        'submitLabel' => 'Simpan dan Tambah Foto',
    ])
</x-layouts.admin>
