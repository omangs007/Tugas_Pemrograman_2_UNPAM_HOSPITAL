<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Dasbor sementara (F1-R4); widget dan grafik dibangun di fase 8. */
    public function index(Request $request): View
    {
        return view('admin.dashboard', ['user' => $request->user()]);
    }
}
