@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Banner', 'url' => route('admin.banners.index')],
        ['label' => 'Ubah '.$banner->title],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$banner->title">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.banners._form', [
        'banner' => $banner,
        'action' => route('admin.banners.update', $banner),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
