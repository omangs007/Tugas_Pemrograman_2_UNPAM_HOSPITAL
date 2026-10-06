@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Banner', 'url' => route('admin.banners.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Banner">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.banners._form', [
        'banner' => null,
        'action' => route('admin.banners.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
