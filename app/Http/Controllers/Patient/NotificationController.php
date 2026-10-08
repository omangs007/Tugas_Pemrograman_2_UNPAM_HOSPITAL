<?php

declare(strict_types=1);

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Menandai notifikasi pasien sebagai dibaca (F6-R29, PAS-08). Hanya notifikasi milik akun yang login: baris milik
 * pengguna lain tidak ditemukan sehingga menjawab 404. Tujuan: `tujuan=dashboard` kembali ke dashboard; selain itu
 * (termasuk nilai larik atau liar) ke detail pendaftaran bila `data.booking_code` milik profil pasien ini, dan ke
 * riwayat bila tidak.
 */
class NotificationController extends Controller
{
    public function read(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->whereKey($id)->firstOrFail();

        $notification->markAsRead();

        if ($request->input('tujuan') === 'dashboard') {
            return redirect()->route('patient.dashboard');
        }

        $profile = $user->loadMissing('patientProfile')->patientProfile;
        $code = is_array($notification->data) ? ($notification->data['booking_code'] ?? null) : null;

        if ($profile !== null && is_string($code) && $code !== '') {
            $owned = Appointment::query()->forPatient($profile)->where('booking_code', $code)->exists();

            if ($owned) {
                return redirect()->route('patient.appointments.show', ['appointment' => $code]);
            }
        }

        return redirect()->route('patient.appointments.index');
    }
}
