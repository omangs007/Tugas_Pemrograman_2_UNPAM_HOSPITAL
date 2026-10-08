<?php

namespace App\Http\Requests\Patient;

/**
 * V-11: POST `patient.appointments.store`. Data tinjauan dibawa sebagai field tersembunyi dan TIDAK dipercaya: aturan,
 * normalisasi, dan `precheck` identik dengan `ReviewAppointmentRequest`, divalidasi ulang sepenuhnya; sesi, jenis
 * pembayaran, mitra, dan nomor yang dipalsukan ditolak atau dipesan apa adanya menurut aturan F.5 (kolom sistem seperti
 * `status`, `queue_number`, `booking_code`, `patient_profile_id` tidak pernah dibaca dari input). Tidak ada field
 * `confirmed`: menekan "Kirim Pendaftaran" pada halaman tinjau adalah konfirmasinya.
 */
class StoreAppointmentRequest extends ReviewAppointmentRequest {}
