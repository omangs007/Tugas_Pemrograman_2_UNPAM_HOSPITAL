{{--
    Isi bukti pendaftaran (F6-R26, F7A-R22), dipakai sisi pasien dan sisi admin. Variabel: $appointment (relasi clinic,
    doctor, practiceSession, patientProfile, insurancePartner termuat), $qrDataUri, $siteName, $logoUrl, $checkinMinutes,
    $printedAt. Opsional: $headingLevel (1 = bawaan; 2 bila layout sudah memuat <h1> judul halaman).
--}}
@php
    $session = $appointment->practiceSession;
    $profile = $appointment->patientProfile;
    $partner = $appointment->insurancePartner;
    $mask = $appointment->masked_payment_reference;
    $payment = $appointment->payment_type->label();
    if ($partner !== null) {
        $payment .= ' - '.$partner->name;
    }
    if ($mask !== null) {
        $payment .= ' ('.$mask.')';
    }
    $code = $appointment->booking_code;
    $headingLevel = ($headingLevel ?? 1) === 2 ? 2 : 1;
@endphp
<article class="proof-sheet border rounded bg-white p-4" aria-labelledby="bukti-judul">
    <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-3">
        <img src="{{ $logoUrl }}" data-fallback="{{ asset('images/placeholders/logo.svg') }}" alt="" width="48" height="48">
        <div>
            <p class="h5 fw-bold text-primary mb-0">{{ $siteName }}</p>
            <h{{ $headingLevel }} class="h4 mb-0" id="bukti-judul">Bukti Pendaftaran Kunjungan</h{{ $headingLevel }}>
        </div>
    </div>

    @if ($appointment->status === \App\Enums\AppointmentStatus::Cancelled)
        <p class="fw-semibold border border-dark p-2 mb-3" role="note">Pendaftaran ini telah dibatalkan. Bukti ini tidak berlaku untuk check-in.</p>
    @endif

    <div class="row g-3 align-items-center mb-3">
        <div class="col-5 col-sm-4 text-center">
            <img class="proof-qr" src="{{ $qrDataUri }}" alt="Kode QR booking {{ $code }}" width="140" height="140">
        </div>
        <div class="col-7 col-sm-8">
            <p class="small text-body-secondary mb-0">Kode booking</p>
            <p class="proof-code display-6 fw-bold mb-2">{{ $code }}</p>
            <p class="small text-body-secondary mb-0">Nomor antrean</p>
            <p class="h3 fw-bold mb-2">{{ $appointment->queue_label }}</p>
            <p class="small text-body-secondary mb-0">Status</p>
            <p class="mb-0"><x-status-badge :status="$appointment->status" /></p>
        </div>
    </div>

    <dl class="row mb-3">
        <dt class="col-sm-4">Pasien</dt>
        <dd class="col-sm-8">{{ $profile->full_name }} ({{ $profile->patient_number ?? '-' }})</dd>
        <dt class="col-sm-4">Poliklinik</dt>
        <dd class="col-sm-8">{{ $appointment->clinic->name }}</dd>
        <dt class="col-sm-4">Dokter</dt>
        <dd class="col-sm-8">{{ $appointment->doctor->display_name }}</dd>
        <dt class="col-sm-4">Tanggal dan jam</dt>
        <dd class="col-sm-8">{{ $session->session_date->translatedFormat('l, j F Y') }}, {{ $session->time_range }}</dd>
        <dt class="col-sm-4">Pembayaran</dt>
        <dd class="col-sm-8">{{ $payment }}</dd>
        <dt class="col-sm-4">Kanal</dt>
        <dd class="col-sm-8">{{ $appointment->channel->label() }}</dd>
    </dl>

    @if ($appointment->status->isActive())
        <p class="mb-2">Tunjukkan kode ini di loket pendaftaran. Check-in dibuka {{ $checkinMinutes }} menit sebelum sesi dimulai dan ditutup saat sesi berakhir.</p>
    @endif
    <p class="small text-body-secondary mb-0">Dicetak pada {{ $printedAt->translatedFormat('j M Y H.i') }} WIB. Dokumen dari sistem prototipe (data contoh).</p>
</article>
