@php
    $profile = $appointment->patientProfile;
    $session = $appointment->practiceSession;
    $code = $appointment->booking_code;
    $payment = $appointment->payment_type->label();
    if ($appointment->insurancePartner !== null) {
        $payment .= ' - '.$appointment->insurancePartner->name;
    }
    if ($appointment->masked_payment_reference !== null) {
        $payment .= ' ('.$appointment->masked_payment_reference.')';
    }
@endphp
<x-layouts.admin :title="'Pendaftaran '.$code">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Pendaftaran', 'url' => route('admin.appointments.index')],
            ['label' => $code],
        ]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ $backUrl }}" class="btn btn-outline-dark">Kembali</a>
        @can('view', $appointment)
            <a href="{{ route('admin.appointments.proof', ['appointment' => $code]) }}" class="btn btn-primary">
                <i class="bi bi-printer" aria-hidden="true"></i> Cetak Bukti
            </a>
        @endcan
    </x-slot:actions>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card mb-4" aria-labelledby="judul-data">
                <div class="card-body">
                    <h2 class="h5" id="judul-data">Data Pendaftaran</h2>

                    @if ($appointment->schedule_changed_at)
                        <div class="alert alert-warning" role="status">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            Jadwal berubah pada {{ $appointment->schedule_changed_at->translatedFormat('j M Y') }}.
                        </div>
                    @endif

                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><x-status-badge :status="$appointment->status" /></dd>
                        <dt class="col-sm-4">Kode booking</dt>
                        <dd class="col-sm-8"><span class="font-monospace">{{ $code }}</span></dd>
                        <dt class="col-sm-4">Nomor antrean</dt>
                        <dd class="col-sm-8">{{ $appointment->queue_label }}</dd>
                        <dt class="col-sm-4">Poliklinik</dt>
                        <dd class="col-sm-8">{{ $appointment->clinic->name }}</dd>
                        <dt class="col-sm-4">Dokter</dt>
                        <dd class="col-sm-8">{{ $appointment->doctor->display_name }}</dd>
                        <dt class="col-sm-4">Jadwal</dt>
                        <dd class="col-sm-8"><time datetime="{{ $appointment->session_date->toDateString() }}">{{ $appointment->session_date->translatedFormat('l, j F Y') }}</time>, {{ $session->time_range }}</dd>
                        <dt class="col-sm-4">Pembayaran</dt>
                        <dd class="col-sm-8">{{ $payment }}</dd>
                        <dt class="col-sm-4">Kanal</dt>
                        <dd class="col-sm-8">
                            <x-status-badge :status="$appointment->channel" />
                            @if ($appointment->creator)
                                <span class="small text-body-secondary">dibuat oleh {{ $appointment->creator->name }}</span>
                            @endif
                        </dd>
                        @if (filled($appointment->complaint))
                            <dt class="col-sm-4">Keluhan</dt>
                            <dd class="col-sm-8 text-break">{{ $appointment->complaint }}</dd>
                        @endif
                    </dl>
                </div>
            </section>

            @can('transition', $appointment)
                <section class="card mb-4" aria-labelledby="judul-aksi">
                    <div class="card-body">
                        <h2 class="h5" id="judul-aksi">Aksi</h2>
                        @include('admin.appointments._actions', ['appointment' => $appointment, 'checks' => $checks, 'context' => 'detail', 'returnTo' => $returnTo])
                    </div>
                </section>
            @endcan
        </div>

        <div class="col-lg-4">
            <section class="card mb-4" aria-labelledby="judul-pasien">
                <div class="card-body">
                    <h2 class="h5" id="judul-pasien">Pasien</h2>
                    <dl class="mb-0">
                        <dt>Nama</dt>
                        <dd>
                            @can('view', $profile)
                                <a href="{{ route('admin.patients.show', $profile) }}">{{ $profile->full_name }}</a>
                            @else
                                {{ $profile->full_name }}
                            @endcan
                        </dd>
                        <dt>Nomor pasien</dt>
                        <dd>{{ $profile->patient_number ?? '-' }}</dd>
                        @can('view', $profile)
                            <dt>Telepon</dt>
                            <dd>{{ $profile->phone }}</dd>
                            @if ($profile->masked_bpjs)
                                <dt>Nomor BPJS tersimpan</dt>
                                <dd>{{ $profile->masked_bpjs }}</dd>
                            @endif
                        @endcan
                    </dl>
                    @if ($profile->user_id === null)
                        <p class="small text-body-secondary mt-3 mb-0">Tanpa akun online: tidak menerima pemberitahuan. Hubungi pasien lewat telepon yang tercatat.</p>
                    @endif
                </div>
            </section>

            <section class="card" aria-labelledby="judul-riwayat">
                <div class="card-body">
                    <h2 class="h5" id="judul-riwayat">Riwayat Status</h2>
                    <ol class="list-unstyled mb-0" aria-labelledby="judul-riwayat">
                        @foreach ($appointment->statusHistories as $history)
                            <li class="mb-2" data-history-item>
                                <time datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->translatedFormat('j M Y, H.i') }}</time>
                                <x-status-badge :status="$history->to_status" class="ms-1" />
                                <span class="text-body-secondary">(oleh {{ $history->actor_type->label() }}@if ($history->actor) {{ $history->actor->name }}@endif)</span>
                                @if (filled($history->reason))
                                    <span class="d-block small text-body-secondary">{{ $history->reason }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        </div>
    </div>
</x-layouts.admin>
