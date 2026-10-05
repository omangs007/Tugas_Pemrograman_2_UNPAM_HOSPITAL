@php
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Pengecualian', 'url' => route('admin.exceptions.index')],
        ['label' => 'Ubah Pengecualian'],
    ];
@endphp
<x-layouts.admin title="Ubah Pengecualian">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.exceptions._form', [
        'exception' => $exception,
        'action' => route('admin.exceptions.update', $exception),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
