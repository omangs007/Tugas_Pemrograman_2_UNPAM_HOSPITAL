<x-layouts.patient title="Pendaftaran Kunjungan">
    <x-slot:breadcrumb>
        <x-breadcrumb home="patient.dashboard" :items="[['label' => 'Pendaftaran Kunjungan']]" />
    </x-slot:breadcrumb>

    <x-wizard-steps :current="5" :links="$links" />

    <h1 class="h3 mb-3" id="judul-langkah" tabindex="-1" data-wizard-heading>Langkah 5 - Periksa dan kirim</h1>

    <section class="card mb-4" aria-labelledby="judul-ringkasan">
        <div class="card-body">
            <h2 class="h5" id="judul-ringkasan">Ringkasan</h2>
            <dl class="row mb-0">
                <dt class="col-sm-3">Poliklinik</dt>
                <dd class="col-sm-9">{{ $clinic->name }}</dd>
                <dt class="col-sm-3">Dokter</dt>
                <dd class="col-sm-9">{{ $doctor->display_name }}</dd>
                <dt class="col-sm-3">Tanggal</dt>
                <dd class="col-sm-9"><time datetime="{{ $session->session_date->toDateString() }}">{{ $session->session_date->translatedFormat('l, j F Y') }}</time></dd>
                <dt class="col-sm-3">Jam sesi</dt>
                <dd class="col-sm-9">{{ $session->time_range }} (sisa kuota saat ini: {{ $item->remaining }} dari {{ $session->quota }})</dd>
                <dt class="col-sm-3">Pasien</dt>
                <dd class="col-sm-9">{{ $profile->full_name }} ({{ $profile->patient_number ?? '-' }})</dd>
                <dt class="col-sm-3">Pembayaran</dt>
                <dd class="col-sm-9">
                    {{ $data->paymentType->label() }}
                    @if ($data->paymentType === \App\Enums\PaymentType::Bpjs && $maskedReference)
                        - nomor {{ $maskedReference }}
                    @elseif ($data->paymentType === \App\Enums\PaymentType::Asuransi)
                        @if ($partner) - {{ $partner->name }} @endif
                        @if ($maskedReference) - polis {{ $maskedReference }} @endif
                    @endif
                </dd>
                <dt class="col-sm-3">Keluhan</dt>
                <dd class="col-sm-9 mb-0 text-break">{{ filled($data->complaint) ? $data->complaint : '-' }}</dd>
            </dl>
        </div>
    </section>

    <div class="alert alert-info" role="note">
        <p class="mb-1">Nomor antrean diberikan setelah pendaftaran berhasil dikirim.</p>
        <p class="mb-0">Pembatalan oleh pasien dapat dilakukan hingga {{ $cancelMinutes }} menit sebelum sesi.</p>
    </div>

    <form method="POST" action="{{ route('patient.appointments.store') }}" novalidate>
        @csrf
        @foreach ($hidden as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $links[4] }}">Ubah data</a>
            <x-loading-button>Kirim Pendaftaran</x-loading-button>
        </div>
    </form>
</x-layouts.patient>
