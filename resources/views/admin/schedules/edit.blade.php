@php
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Templat Mingguan', 'url' => route('admin.schedules.index')],
        ['label' => 'Ubah Templat'],
    ];
@endphp
<x-layouts.admin title="Ubah Templat Jadwal">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.schedules._form', [
        'schedule' => $schedule,
        'action' => route('admin.schedules.update', $schedule),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
