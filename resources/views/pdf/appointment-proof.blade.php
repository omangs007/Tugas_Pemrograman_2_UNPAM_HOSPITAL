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
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Pendaftaran {{ $code }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        .header td { vertical-align: middle; padding-bottom: 6px; border-bottom: 1px solid #000; }
        .site { font-size: 12pt; font-weight: bold; }
        .title { font-size: 10pt; margin-top: 2px; }
        .notice { border: 1px solid #000; padding: 5px; margin-top: 8px; font-weight: bold; }
        .box td { vertical-align: middle; padding: 10px 0; }
        .label { font-size: 7pt; color: #444; }
        .code { font-size: 15pt; font-weight: bold; white-space: nowrap; margin-bottom: 6px; }
        .queue { font-size: 14pt; font-weight: bold; margin-bottom: 6px; }
        .details td { padding: 3px 0; vertical-align: top; }
        .details td.key { width: 32%; color: #444; }
        .instruction { margin-top: 10px; }
        .footer { margin-top: 12px; font-size: 7pt; color: #444; border-top: 1px solid #000; padding-top: 4px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if ($logoDataUri)
                <td style="width: 30%;"><img src="{{ $logoDataUri }}" alt="" style="width: 100px;"></td>
            @endif
            <td>
                <div class="site">{{ $siteName }}</div>
                <div class="title">Bukti Pendaftaran Kunjungan</div>
            </td>
        </tr>
    </table>

    @if ($appointment->status === \App\Enums\AppointmentStatus::Cancelled)
        <div class="notice">Pendaftaran ini telah dibatalkan. Bukti ini tidak berlaku untuk check-in.</div>
    @endif

    <table class="box">
        <tr>
            <td style="width: 34%; text-align: center;">
                <img src="{{ $qrPngDataUri }}" alt="Kode QR booking {{ $code }}" style="width: 110px; height: 110px;">
            </td>
            <td>
                <div class="label">Kode booking</div>
                <div class="code">{{ $code }}</div>
                <div class="label">Nomor antrean</div>
                <div class="queue">{{ $appointment->queue_label }}</div>
                <div class="label">Status</div>
                <div>{{ $appointment->status->label() }}</div>
            </td>
        </tr>
    </table>

    <table class="details">
        <tr><td class="key">Pasien</td><td>{{ $profile->full_name }} ({{ $profile->patient_number ?? '-' }})</td></tr>
        <tr><td class="key">Poliklinik</td><td>{{ $appointment->clinic->name }}</td></tr>
        <tr><td class="key">Dokter</td><td>{{ $appointment->doctor->display_name }}</td></tr>
        <tr><td class="key">Tanggal dan jam</td><td>{{ $session->session_date->translatedFormat('l, j F Y') }}, {{ $session->time_range }}</td></tr>
        <tr><td class="key">Pembayaran</td><td>{{ $payment }}</td></tr>
        <tr><td class="key">Kanal</td><td>{{ $appointment->channel->label() }}</td></tr>
    </table>

    @if ($appointment->status->isActive())
        <div class="instruction">Tunjukkan kode ini di loket pendaftaran. Check-in dibuka {{ $checkinMinutes }} menit sebelum sesi dimulai dan ditutup saat sesi berakhir.</div>
    @endif

    <div class="footer">Dicetak pada {{ $printedAt->translatedFormat('j M Y H.i') }} WIB. Dokumen dari sistem prototipe (data contoh).</div>
</body>
</html>
