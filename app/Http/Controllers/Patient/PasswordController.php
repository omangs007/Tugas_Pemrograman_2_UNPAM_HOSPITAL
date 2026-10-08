<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\UpdatePasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Ganti password pasien (#28, F5-R21). Hanya butuh `User` (akun tanpa profil tetap bisa mengganti password).
 * Urutan penting: regenerasi sesi DULU, lalu satu transaksi (simpan password, hapus baris `sessions` akun selain ID sesi baru, cabut token reset, audit).
 */
class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Regenerasi dulu: getId() di bawah harus sudah memakai ID sesi baru.
        $request->session()->regenerate();
        $currentSessionId = $request->session()->getId();

        // Atomik: password, sesi lain, token reset yang masih berlaku, dan audit berhasil bersama atau batal bersama.
        DB::transaction(function () use ($user, $request, $audit, $currentSessionId): void {
            // Cast `hashed` pada User meng-hash password; jangan memanggil Hash::make() lagi.
            $user->forceFill([
                'password' => $request->validated('password'),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();

            Password::broker()->deleteToken($user);

            $number = $user->patientProfile?->patient_number ?? '-';

            $audit->log(
                'auth.password_changed',
                $user,
                null,
                null,
                "Pasien {$number} mengganti password akunnya.",
                Actor::patient($user),
            );
        });

        return redirect()->route('patient.profile.edit')->with('success', 'Password berhasil diganti.');
    }
}
