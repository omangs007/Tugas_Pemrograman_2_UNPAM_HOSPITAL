@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Kategori Artikel', 'url' => route('admin.article-categories.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Kategori">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.article-categories._form', [
        'category' => null,
        'action' => route('admin.article-categories.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
