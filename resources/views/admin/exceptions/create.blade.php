@php
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Pengecualian', 'url' => route('admin.exceptions.index')],
        ['label' => 'Tambah Pengecualian'],
    ];
@endphp
<x-layouts.admin title="Tambah Pengecualian">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.exceptions._form', [
        'exception' => null,
        'action' => route('admin.exceptions.store'),
        'submitLabel' => 'Simpan Pengecualian',
    ])
</x-layouts.admin>
