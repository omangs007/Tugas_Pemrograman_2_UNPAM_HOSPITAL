@php
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Templat Mingguan', 'url' => route('admin.schedules.index')],
        ['label' => 'Tambah Templat'],
    ];
@endphp
<x-layouts.admin title="Tambah Templat Jadwal">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.schedules._form', [
        'schedule' => null,
        'action' => route('admin.schedules.store'),
        'submitLabel' => 'Simpan Templat',
    ])
</x-layouts.admin>
