@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Artikel', 'url' => route('admin.articles.index')],
        ['label' => 'Ubah '.$article->title],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$article->title">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.articles._form', [
        'article' => $article,
        'categoryOptions' => $categoryOptions,
        'action' => route('admin.articles.update', $article),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
