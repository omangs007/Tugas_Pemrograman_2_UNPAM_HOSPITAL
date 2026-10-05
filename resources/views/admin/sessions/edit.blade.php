@php
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Sesi per Tanggal', 'url' => route('admin.sessions.index')],
        ['label' => 'Ubah Sesi'],
    ];
@endphp
<x-layouts.admin title="Ubah Sesi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.sessions._form', [
        'session' => $session,
        'action' => route('admin.sessions.update', $session),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
