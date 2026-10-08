@php
    $profile = $appointment->patientProfile;
    $session = $appointment->practiceSession;
@endphp
<x-layouts.patient title="Detail Pendaftaran">
    <x-slot:breadcrumb>
        <x-breadcrumb home="patient.dashboard" :items="[
            ['label' => 'Riwayat Pendaftaran', 'url' => Route::has('patient.appointments.index') ? route('patient.appointments.index') : null],
            ['label' => $appointment->booking_code],
        ]" />
    </x-slot:breadcrumb>

    <h1 class="h3 mb-3">Detail Pendaftaran</h1>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <img src="{{ $qrDataUri }}" alt="Kode QR booking {{ $appointment->booking_code }}" width="160" height="160" class="img-fluid mb-2">
                    <p class="h4 font-monospace mb-1">{{ $appointment->booking_code }}</p>
                    <p class="mb-0">Antrean <strong>{{ $appointment->queue_label }}</strong></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            @if ($appointment->schedule_changed_at)
                <div class="alert alert-warning" role="status">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    Jadwal berubah pada {{ $appointment->schedule_changed_at->translatedFormat('j M Y') }}.
                </div>
            @endif

            <dl class="row mb-3">
                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8"><x-status-badge :status="$appointment->status" /></dd>
                <dt class="col-sm-4">Poliklinik</dt>
                <dd class="col-sm-8">{{ $appointment->clinic->name }}</dd>
                <dt class="col-sm-4">Dokter</dt>
                <dd class="col-sm-8">{{ $appointment->doctor->display_name }}</dd>
                <dt class="col-sm-4">Jadwal</dt>
                <dd class="col-sm-8"><time datetime="{{ $appointment->session_date->toDateString() }}">{{ $appointment->session_date->translatedFormat('l, j F Y') }}</time>, {{ $session->time_range }}</dd>
                <dt class="col-sm-4">Pasien</dt>
                <dd class="col-sm-8">{{ $profile->full_name }} ({{ $profile->patient_number ?? '-' }})</dd>
                <dt class="col-sm-4">Pembayaran</dt>
                <dd class="col-sm-8">
                    {{ $appointment->payment_type->label() }}
                    @if ($appointment->insurancePartner) - {{ $appointment->insurancePartner->name }} @endif
                    @if ($appointment->masked_payment_reference) ({{ $appointment->masked_payment_reference }}) @endif
                </dd>
                <dt class="col-sm-4">Kanal</dt>
                <dd class="col-sm-8">{{ $appointment->channel->label() }}</dd>
                @if (filled($appointment->complaint))
                    <dt class="col-sm-4">Keluhan</dt>
                    <dd class="col-sm-8 text-break">{{ $appointment->complaint }}</dd>
                @endif
            </dl>

            <p class="text-body-secondary">Tunjukkan kode ini di loket. Check-in dibuka {{ $checkinMinutes }} menit sebelum sesi dimulai.</p>

            <h2 class="h5 mt-4" id="judul-riwayat">Riwayat Status</h2>
            <ol class="list-unstyled" aria-labelledby="judul-riwayat">
                @foreach ($appointment->statusHistories as $history)
                    <li class="mb-2">
                        <time datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->translatedFormat('j M Y, H.i') }}</time>
                        <x-status-badge :status="$history->to_status" class="ms-1" />
                        <span class="text-body-secondary">(oleh {{ $history->actor_type->label() }})</span>
                        @if (filled($history->reason))
                            <span class="d-block small text-body-secondary">{{ $history->reason }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>

            <div class="d-flex flex-wrap gap-2 mt-4" data-appointment-actions>
                {{-- [[AKSI-DETAIL-BERIKUT]] --}}
                <a class="btn btn-outline-secondary" href="{{ route('patient.dashboard') }}">Ke Dashboard</a>
            </div>
        </div>
    </div>
</x-layouts.patient>
