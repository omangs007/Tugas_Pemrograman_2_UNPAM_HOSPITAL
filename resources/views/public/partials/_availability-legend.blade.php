@php
    // Keterangan status (07 W-04/W-05): teks, ikon, dan warna selalu bersama (00 D).
    $legend = [
        [\App\Enums\Availability::Available, 'Dapat didaftar online; sisa kuota ditampilkan.'],
        [\App\Enums\Availability::Full, 'Kuota sesi sudah habis.'],
        [\App\Enums\Availability::BookingClosed, 'Pendaftaran online sudah ditutup untuk sesi ini.'],
        [\App\Enums\Availability::Closed, 'Sesi ditutup oleh petugas.'],
        [\App\Enums\Availability::Leave, 'Dokter sedang cuti.'],
        [\App\Enums\Availability::Holiday, 'Libur rumah sakit atau libur nasional.'],
        [\App\Enums\Availability::Cancelled, 'Sesi dibatalkan.'],
        [\App\Enums\Availability::UnderReview, 'Jadwal sesi sedang diperbarui petugas.'],
        [\App\Enums\Availability::Past, 'Sesi sudah selesai.'],
    ];
@endphp
<section class="mt-4" aria-labelledby="legenda-status-judul" data-availability-legend>
    <h2 id="legenda-status-judul" class="h6">Keterangan status</h2>
    <ul class="list-unstyled availability-legend small mb-0">
        @foreach ($legend as [$case, $meaning])
            <li><x-availability-badge :availability="$case" /> <span class="text-body-secondary">{{ $meaning }}</span></li>
        @endforeach
    </ul>
</section>
