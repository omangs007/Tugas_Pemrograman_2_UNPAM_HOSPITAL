<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Route;

/**
 * Memasang route pengganti `patient.appointments.show` bila route sebenarnya belum ada (dibuat fase 6 Task 8),
 * supaya tombol "Lihat Pendaftaran" pada surel dan tautan notifikasi dapat dibangun di tes Task 3 dan 4
 * (pola fase 5: route `login` pengganti). Setelah route sebenarnya ada, pembantu ini tidak melakukan apa pun.
 * Route pengganti memakai pengikatan `booking_code` seperti route sebenarnya, jadi `route('...show', $appointment)`
 * menghasilkan kode booking pada URL.
 */
trait RegistersAppointmentRoutes
{
    protected function ensureAppointmentShowRoute(): void
    {
        if (Route::has('patient.appointments.show')) {
            return;
        }

        Route::middleware('web')
            ->get('/_uji/pendaftaran/{appointment:booking_code}', fn () => 'detail')
            ->name('patient.appointments.show');

        Route::getRoutes()->refreshNameLookups();
    }
}
