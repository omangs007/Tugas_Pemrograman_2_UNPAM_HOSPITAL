<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqIndexRequest;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin #39 tab FAQ (F-ADM-22). Urutan tampil = field `sort_order` (F4A-R22), tanpa route reorder.
 * Hapus permanen tanpa penolakan: FAQ tidak dirujuk tabel lain.
 */
class FaqController extends Controller
{
    public function index(FaqIndexRequest $request): View
    {
        $this->authorize('viewAny', Faq::class);

        $faqs = $request->applyTo(Faq::query())->paginate($request->perPage());

        return view('admin.faqs.index', ['faqs' => $faqs]);
    }

    public function create(): View
    {
        $this->authorize('create', Faq::class);

        return view('admin.faqs.create');
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        $this->authorize('create', Faq::class);

        DB::transaction(fn (): Faq => Faq::query()->create($request->faqAttributes()));

        return redirect()->route('admin.faqs.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.faq')]));
    }

    public function edit(Faq $faq): View
    {
        $this->authorize('update', $faq);

        return view('admin.faqs.edit', ['faq' => $faq]);
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $this->authorize('update', $faq);

        DB::transaction(fn (): bool => $faq->update($request->faqAttributes()));

        return redirect()->route('admin.faqs.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.faq')]));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->authorize('delete', $faq);

        DB::transaction(fn (): ?bool => $faq->delete());

        return redirect()->route('admin.faqs.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.faq')]));
    }
}
