<x-layouts.admin title="Pendaftaran Walk-in">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Pendaftaran', 'url' => route('admin.appointments.index')],
            ['label' => 'Pendaftaran Walk-in'],
        ]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ $backUrl }}" class="btn btn-outline-dark">Batal</a>
    </x-slot:actions>

    <x-form.errors-summary />

    @include('admin.appointments._walk-in-patient')
    @include('admin.appointments._walk-in-session')
    @include('admin.appointments._walk-in-payment')
</x-layouts.admin>
