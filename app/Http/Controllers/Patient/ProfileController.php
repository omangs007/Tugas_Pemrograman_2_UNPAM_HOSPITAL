<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\UpdatePatientProfileRequest;
use App\Models\User;
use App\Services\Patients\PatientRegistrationService;
use App\Support\Actor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profil dan akun pasien (#28, F5-R18): satu halaman dua formulir. Profil SELALU `$request->user()->patientProfile`
 * (tanpa id di URL/form, BR-150). Akun tanpa profil (hanya mungkin di tes/DB rusak): halaman 404, pembaruan 403
 * lewat `UpdatePatientProfileRequest::authorize()` (F5-R13).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patientProfile;

        abort_if($profile === null, 404);

        $this->authorize('view', $profile);

        return view('patient.profile.edit', [
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    public function update(UpdatePatientProfileRequest $request, PatientRegistrationService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patientProfile; // authorize() menjamin profil ada

        try {
            $service->updateProfile($profile, $request->validated(), Actor::patient($user));
        } catch (UniqueConstraintViolationException) {
            // Balapan: email diambil akun lain di antara validasi dan simpan. Transaksi service sudah dibatalkan.
            return redirect()->route('patient.profile.edit')
                ->withInput($request->only(['full_name', 'birth_date', 'gender', 'phone', 'email', 'address', 'remove_bpjs']))
                ->withErrors(['email' => 'Email ini sudah terdaftar. Gunakan email lain.']);
        }

        return redirect()->route('patient.profile.edit')->with('success', 'Profil berhasil diperbarui.');
    }
}
