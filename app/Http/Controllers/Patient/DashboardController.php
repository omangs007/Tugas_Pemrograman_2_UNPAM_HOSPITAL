<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Dashboard pasien (F5-R22). Akun pasien tanpa profil (data rusak) menjawab 404 (F5-R13). */
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->loadMissing('patientProfile')->patientProfile;

        abort_if($profile === null, 404);

        return view('patient.dashboard', ['user' => $user, 'profile' => $profile]);
    }
}
