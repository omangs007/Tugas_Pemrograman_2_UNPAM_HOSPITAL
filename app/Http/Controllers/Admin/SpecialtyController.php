<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Http\Requests\Admin\SpecialtyRequest;
use App\Models\Specialty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

    public function create(): View
    {
        $this->authorize('create', Specialty::class);

        return view('admin.specialties.create');
    }

    public function store(SpecialtyRequest $request): RedirectResponse
    {
        $this->authorize('create', Specialty::class);

        DB::transaction(fn (): Specialty => Specialty::query()->create($request->attributesForModel()));

        return redirect()->route('admin.specialties.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.specialty')]));
    }

    public function edit(Specialty $specialty): View
    {
        $this->authorize('update', $specialty);

        return view('admin.specialties.edit', ['specialty' => $specialty]);
    }

    public function update(SpecialtyRequest $request, Specialty $specialty): RedirectResponse
    {
        $this->authorize('update', $specialty);

        DB::transaction(fn (): bool => $specialty->update($request->attributesForModel()));

        return redirect()->route('admin.specialties.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.specialty')]));
    }

    /**
     * Penolakan di controller (bukan Policy) supaya berlaku juga untuk Super Admin (F2-R18).
     */
    public function destroy(Specialty $specialty): RedirectResponse
    {
        $this->authorize('delete', $specialty);

        $reason = $specialty->deletionBlockedReason();

        if ($reason !== null) {
            return redirect()->route('admin.specialties.index')->with('error', __('messages.delete_blocked', ['reason' => $reason]));
        }

        DB::transaction(fn (): ?bool => $specialty->delete());

        return redirect()->route('admin.specialties.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.specialty')]));
    }
}
