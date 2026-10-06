@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Mitra Asuransi', 'url' => route('admin.insurance-partners.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Mitra">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.insurance-partners._form', [
        'partner' => null,
        'action' => route('admin.insurance-partners.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
