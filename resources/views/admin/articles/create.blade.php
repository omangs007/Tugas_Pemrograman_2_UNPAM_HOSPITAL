@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Artikel', 'url' => route('admin.articles.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Artikel">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.articles._form', [
        'article' => null,
        'categoryOptions' => $categoryOptions,
        'action' => route('admin.articles.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
