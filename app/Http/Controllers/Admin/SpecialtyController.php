<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Models\Specialty;
use Illuminate\View\View;

class SpecialtyController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', Specialty::class);

        $specialties = $request
            ->applyTo(Specialty::query()->withCount(['doctors', 'clinics']))
            ->paginate($request->perPage());

        return view('admin.specialties.index', ['specialties' => $specialties]);
    }
}
