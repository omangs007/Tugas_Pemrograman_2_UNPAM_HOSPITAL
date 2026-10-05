@php
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Sesi per Tanggal', 'url' => route('admin.sessions.index')],
        ['label' => 'Tambah Sesi'],
    ];
@endphp
<x-layouts.admin title="Tambah Sesi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.sessions._form', [
        'session' => null,
        'action' => route('admin.sessions.store'),
        'submitLabel' => 'Simpan Sesi',
    ])
</x-layouts.admin>
