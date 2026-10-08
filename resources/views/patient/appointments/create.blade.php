<x-layouts.patient title="Pendaftaran Kunjungan">
    <x-slot:breadcrumb>
        <x-breadcrumb home="patient.dashboard" :items="[['label' => 'Pendaftaran Kunjungan']]" />
    </x-slot:breadcrumb>

    <x-wizard-steps :current="$step" :links="$links" />

    @if ($step === 1)
        @include('patient.appointments._step-clinic')
    @elseif ($step === 2)
        @include('patient.appointments._step-doctor')
    @elseif ($step === 3)
        @include('patient.appointments._step-session')
    @else
        @include('patient.appointments._step-data')
    @endif
</x-layouts.patient>
