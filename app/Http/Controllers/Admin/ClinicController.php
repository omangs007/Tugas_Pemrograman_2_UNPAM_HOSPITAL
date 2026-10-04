<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Models\Clinic;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', Clinic::class);

        $clinics = $request
            ->applyTo(Clinic::query()->with(['specialty', 'media'])->withCount('doctors'), ['name', 'code'])
            ->paginate($request->perPage());

        return view('admin.clinics.index', ['clinics' => $clinics]);
    }
}
