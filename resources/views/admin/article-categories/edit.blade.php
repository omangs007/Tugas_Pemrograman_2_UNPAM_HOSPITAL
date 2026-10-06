@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Kategori Artikel', 'url' => route('admin.article-categories.index')],
        ['label' => 'Ubah '.$category->name],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$category->name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.article-categories._form', [
        'category' => $category,
        'action' => route('admin.article-categories.update', $category),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
