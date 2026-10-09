@php
    $code = $appointment->booking_code;
@endphp
<x-layouts.patient :title="'Bukti Pendaftaran '.$code">
    <x-slot:breadcrumb>
        <x-breadcrumb class="d-print-none" home="patient.dashboard" :items="[
            ['label' => 'Riwayat Pendaftaran', 'url' => route('patient.appointments.index')],
            ['label' => $code, 'url' => route('patient.appointments.show', $code)],
            ['label' => 'Bukti Pendaftaran'],
        ]" />
    </x-slot:breadcrumb>

    <div class="d-print-none d-flex flex-wrap gap-2 mb-3" data-proof-actions>
        <button type="button" class="btn btn-primary" data-print><i class="bi bi-printer" aria-hidden="true"></i> Cetak Bukti</button>
        <a class="btn btn-outline-primary" href="{{ route('patient.appointments.proof-pdf', $code) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Unduh PDF</a>
        <a class="btn btn-outline-secondary" href="{{ route('patient.appointments.show', $code) }}">Kembali ke Detail</a>
    </div>
    <noscript>
        <p class="d-print-none text-body-secondary">JavaScript nonaktif: gunakan menu cetak peramban (Ctrl+P) atau tombol Unduh PDF.</p>
    </noscript>

    @include('partials.appointment-proof')
</x-layouts.patient>
