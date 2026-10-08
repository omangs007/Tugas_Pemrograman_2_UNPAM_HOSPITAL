<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\UpdatePasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ganti password pasien (#28, F5-R21). Hanya butuh `User` (akun tanpa profil tetap bisa mengganti password).
 * Urutan penting: regenerasi sesi DULU, lalu hapus baris `sessions` akun selain ID sesi baru (sesi ini tetap hidup).
 */
class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Cast `hashed` pada User meng-hash password; jangan memanggil Hash::make() lagi.
        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $number = $user->patientProfile?->patient_number ?? '-';

        $audit->log(
            'auth.password_changed',
            $user,
            null,
            null,
            "Pasien {$number} mengganti password akunnya.",
            Actor::patient($user),
        );

        return redirect()->route('patient.profile.edit')->with('success', 'Password berhasil diganti.');
    }
}
