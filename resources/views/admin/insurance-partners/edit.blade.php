@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Mitra Asuransi', 'url' => route('admin.insurance-partners.index')],
        ['label' => 'Ubah '.$partner->name],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$partner->name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.insurance-partners._form', [
        'partner' => $partner,
        'action' => route('admin.insurance-partners.update', $partner),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
