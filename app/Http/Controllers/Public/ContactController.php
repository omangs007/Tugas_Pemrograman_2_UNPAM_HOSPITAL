<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Halaman #22 `contact` dan kiriman pesan `contact.store` (06 §17). Pesan disimpan tanpa IP mentah (`ip_hash` =
 * SHA-256 dari IP dan APP_KEY) dan tidak diaudit (F4B-R25). Honeypot terisi: sukses palsu tanpa menyimpan (F4B-R7).
 * Throttle `contact` (3 per 10 menit per IP) dipasang di route.
 */
class ContactController extends Controller
{
    public const SUCCESS_MESSAGE = 'Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.';

    public function show(): View
    {
        return view('public.contact', ['page' => Page::forKey('contact')]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        if (! $request->isHoneypotFilled()) {
            ContactMessage::query()->create($request->messageAttributes() + [
                'status' => ContactMessageStatus::New,
                'consent_at' => now(),
                'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
            ]);
        }

        return redirect()->route('contact')->with('success', self::SUCCESS_MESSAGE);
    }
}
