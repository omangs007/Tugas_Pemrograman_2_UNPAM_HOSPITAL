@php
    $code = $appointment->booking_code;
@endphp
<x-layouts.admin :title="'Bukti Pendaftaran '.$code">
    <x-slot:breadcrumb>
        <x-breadcrumb class="d-print-none" home="admin.dashboard" :items="[
            ['label' => 'Pendaftaran', 'url' => route('admin.appointments.index')],
            ['label' => $code, 'url' => route('admin.appointments.show', ['appointment' => $code])],
            ['label' => 'Bukti Pendaftaran'],
        ]" />
    </x-slot:breadcrumb>

    <div class="d-print-none d-flex flex-wrap gap-2 mb-3" data-proof-actions>
        <button type="button" class="btn btn-primary" data-print><i class="bi bi-printer" aria-hidden="true"></i> Cetak Bukti</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.appointments.show', ['appointment' => $code]) }}">Kembali ke Detail</a>
    </div>
    <noscript>
        <p class="d-print-none text-body-secondary">JavaScript nonaktif: gunakan menu cetak peramban (Ctrl+P).</p>
    </noscript>

    @include('partials.appointment-proof', ['headingLevel' => 2])
</x-layouts.admin>
