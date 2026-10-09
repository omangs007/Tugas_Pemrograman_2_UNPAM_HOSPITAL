<?php

return [

    /*
    | Pesan galat domain booking (F6-R8). `errors` = BookingException (06 §3.F, final menurut 12 V-11, BR-45);
    | `transitions` = InvalidTransitionException (BR-58..BR-79); `no_show` = aksi Tandai tidak hadir (fase 7a);
    | `submit_in_progress` = BR-48.
    | Placeholder Laravel: :days, :minutes, :max, :doctor, :start, :end, :dari, :ke, :status, :phone, :date, :time,
    | dan :subjek/:Subjek ("Anda" untuk kanal online, "pasien ini" untuk loket; :Subjek di awal kalimat).
    */

    'errors' => [
        'SESSION_UNAVAILABLE' => 'Sesi praktik ini tidak tersedia untuk pendaftaran. Silakan pilih sesi lain.',
        'OUTSIDE_WINDOW' => 'Pendaftaran hanya dapat dilakukan untuk tanggal mulai hari ini sampai :days hari ke depan.',
        'CUTOFF_PASSED' => 'Pendaftaran online untuk sesi ini sudah ditutup (:minutes menit sebelum sesi dimulai). Silakan datang ke loket pendaftaran atau hubungi rumah sakit.',
        'CUTOFF_PASSED_COUNTER' => 'Sesi ini sudah berakhir.',
        'QUOTA_FULL' => 'Kuota sesi ini sudah penuh. Silakan pilih tanggal atau sesi lain.',
        'DUPLICATE_BOOKING' => ':Subjek sudah memiliki pendaftaran aktif pada sesi dokter ini.',
        'OVERLAPPING_BOOKING' => 'Jadwal ini bertabrakan dengan pendaftaran lain :subjek pada tanggal yang sama (:doctor, :start–:end).',
        'ACTIVE_LIMIT_REACHED' => ':Subjek sudah memiliki :max pendaftaran aktif yang akan datang (batas maksimum). Selesaikan atau batalkan salah satunya terlebih dahulu.',
        'INVALID_PAYMENT' => 'Data pembayaran tidak sesuai: nomor BPJS (13 digit) wajib untuk pembayaran BPJS dan mitra asuransi wajib untuk pembayaran asuransi.',
        'SYSTEM_BUSY' => 'Sistem sedang sibuk, silakan coba lagi beberapa saat.',
        'PROFILE_INACTIVE' => 'Profil pasien tidak aktif atau tidak ditemukan.',
    ],

    'transitions' => [
        'INVALID_TRANSITION' => "Perubahan status dari ':dari' ke ':ke' tidak diperbolehkan.",
        'CANCEL_CHECKED_IN' => 'Pendaftaran yang sudah check-in tidak dapat dibatalkan. Silakan hubungi petugas pendaftaran.',
        'ALREADY_FINAL' => 'Pendaftaran ini sudah berstatus :status dan tidak dapat diubah lagi.',
        'FORBIDDEN_TRANSITION' => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
        'STALE_STATUS' => 'Status pendaftaran sudah berubah. Muat ulang halaman untuk melihat status terbaru.',
        'CANCEL_CUTOFF_PASSED' => 'Batas pembatalan online telah lewat (maksimal :minutes menit sebelum sesi dimulai). Silakan hubungi rumah sakit di :phone atau melalui WhatsApp.',
        'CONFIRM_AFTER_END' => 'Sesi sudah berakhir sehingga pendaftaran tidak dapat dikonfirmasi.',
        'CHECKIN_NOT_TODAY' => 'Check-in hanya dapat dilakukan pada tanggal kunjungan (:date).',
        'CHECKIN_NOT_OPEN' => 'Check-in belum dibuka. Check-in dibuka pukul :time.',
        'CHECKIN_CLOSED' => 'Sesi sudah berakhir sehingga check-in tidak dapat dilakukan.',
        'CORRECTION_NOT_SAME_DAY' => 'Koreksi check-in hanya dapat dilakukan pada hari yang sama.',
        'SESSION_NOT_ENDED' => 'Tandai tidak hadir hanya dapat dilakukan setelah sesi berakhir (pukul :time).',
        'REASON_REQUIRED' => 'Alasan wajib diisi untuk pembatalan atau koreksi check-in.',
        'SESSION_UNDER_REVIEW' => 'Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak.',
    ],

    'no_show' => [
        'done' => ':count pendaftaran ditandai tidak hadir.',
        'nothing' => 'Tidak ada pendaftaran yang perlu ditandai tidak hadir pada sesi ini.',
        'confirm_required' => 'Centang konfirmasi untuk menandai seluruh pendaftaran yang belum hadir sebagai tidak hadir.',
    ],

    'submit_in_progress' => 'Permintaan Anda sedang diproses. Mohon tunggu sebentar dan jangan menekan tombol berulang kali.',

];
