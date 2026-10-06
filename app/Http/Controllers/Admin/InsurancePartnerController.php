<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InsurancePartnerRequest;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Models\InsurancePartner;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin #39 tab Mitra Asuransi (F-ADM-23). Mitra yang dirujuk appointment tidak dapat dihapus (BR-180, FK RESTRICT);
 * penolakan dilakukan di controller supaya berlaku juga untuk Super Admin (F4A-R21, pola D-44).
 */
class InsurancePartnerController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', InsurancePartner::class);

        $partners = $request
            ->applyTo(InsurancePartner::query()->with('media'), ['name'])
            ->paginate($request->perPage());

        return view('admin.insurance-partners.index', ['partners' => $partners]);
    }

    public function create(): View
    {
        $this->authorize('create', InsurancePartner::class);

        return view('admin.insurance-partners.create');
    }

    public function store(InsurancePartnerRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', InsurancePartner::class);

        DB::transaction(function () use ($request, $media): void {
            $partner = InsurancePartner::query()->create($request->insurancePartnerAttributes());
            $this->saveLogo($request, $media, $partner);
        });

        return redirect()->route('admin.insurance-partners.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.insurance_partner')]));
    }

    public function edit(InsurancePartner $insurancePartner): View
    {
        $this->authorize('update', $insurancePartner);

        return view('admin.insurance-partners.edit', ['partner' => $insurancePartner->load('media')]);
    }

    public function update(InsurancePartnerRequest $request, InsurancePartner $insurancePartner, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $insurancePartner);

        DB::transaction(function () use ($request, $media, $insurancePartner): void {
            $insurancePartner->update($request->insurancePartnerAttributes());
            $this->saveLogo($request, $media, $insurancePartner);
        });

        return redirect()->route('admin.insurance-partners.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.insurance_partner')]));
    }

    /**
     * Penolakan di controller (bukan Policy) supaya berlaku juga untuk Super Admin. Logo ikut terhapus lewat trait
     * HasMedia; transaksi wajib agar berkas dihapus setelah commit.
     */
    public function destroy(InsurancePartner $insurancePartner): RedirectResponse
    {
        $this->authorize('delete', $insurancePartner);

        $reason = $insurancePartner->deletionBlockedReason();

        if ($reason !== null) {
            return redirect()->route('admin.insurance-partners.index')
                ->with('error', __('messages.delete_blocked', ['reason' => $reason]));
        }

        DB::transaction(fn (): ?bool => $insurancePartner->delete());

        return redirect()->route('admin.insurance-partners.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.insurance_partner')]));
    }

    private function saveLogo(InsurancePartnerRequest $request, MediaService $media, InsurancePartner $partner): void
    {
        $user = $request->user();
        $logo = $request->logoFile();

        if ($logo !== null) {
            $media->replace($partner, 'logo', $logo, ['alt_text' => $request->logoAlt()], $user);

            return;
        }

        if ($request->has('logo_alt')) {
            // Tanpa berkas baru: alt yang diedit diterapkan ke logo yang ada (caption tidak diubah).
            $existing = $partner->media()->where('collection', 'logo')->first();

            if ($existing !== null && $existing->alt_text !== $request->logoAlt()) {
                $media->updateMeta($existing, $request->logoAlt(), $existing->caption);
            }
        }
    }
}
