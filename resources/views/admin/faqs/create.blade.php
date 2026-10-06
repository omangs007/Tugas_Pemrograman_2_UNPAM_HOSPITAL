@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'FAQ', 'url' => route('admin.faqs.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah FAQ">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.faqs._form', [
        'faq' => null,
        'action' => route('admin.faqs.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
