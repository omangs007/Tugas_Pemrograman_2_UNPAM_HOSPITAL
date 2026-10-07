<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactMessageIndexRequest;
use App\Http\Requests\Admin\UpdateContactMessageStatusRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin kotak pesan kontak (F-ADM-24). Hanya `index`, `show`, dan `status`: pesan berisi data pribadi pengunjung dan tidak
 * pernah dihapus (00 C). `ContactMessage` tidak diobservasi `AuditObserver`; perubahan status dicatat di sini sebagai
 * `contact_message.status_changed` tanpa nama, email, telepon, subjek, atau isi pesan (F4B-R24, F4B-R25).
 */
class ContactMessageController extends Controller
{
    public function index(ContactMessageIndexRequest $request): View
    {
        $this->authorize('viewAny', ContactMessage::class);

        $contactMessages = $request
            ->applyTo(ContactMessage::query())
            ->paginate($request->perPage());

        return view('admin.messages.index', ['contactMessages' => $contactMessages]);
    }

    /**
     * Membuka pesan berstatus `new` mengubahnya menjadi `read` satu kali, tanpa mengisi `handled_*`.
     */
    public function show(Request $request, ContactMessage $message, AuditLogger $audit): View
    {
        $this->authorize('view', $message);

        if ($message->status === ContactMessageStatus::New) {
            $this->transition($message, ContactMessageStatus::Read, $request->user(), $audit);
            $message->refresh();
        }

        return view('admin.messages.show', ['contactMessage' => $message->load('handler')]);
    }

    public function status(UpdateContactMessageStatusRequest $request, ContactMessage $message, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('updateStatus', $message);

        $this->transition($message, $request->statusValue(), $request->user(), $audit);

        return redirect()->route('admin.messages.show', $message)
            ->with('success', 'Status pesan berhasil diperbarui.');
    }

    /**
     * Status dibaca ulang di bawah kunci baris: dua permintaan serentak tidak menulis audit ganda, dan status yang
     * sama dengan yang tersimpan tidak mengubah apa pun. Target `replied` atau `archived` mengisi `handled_*`;
     * kembali ke `read` membiarkannya.
     */
    private function transition(ContactMessage $message, ContactMessageStatus $to, User $user, AuditLogger $audit): void
    {
        DB::transaction(function () use ($message, $to, $user, $audit): void {
            $locked = ContactMessage::query()->lockForUpdate()->findOrFail($message->getKey());
            $from = $locked->status;

            if ($from === $to) {
                return;
            }

            $locked->status = $to;

            if ($to === ContactMessageStatus::Replied || $to === ContactMessageStatus::Archived) {
                $locked->forceFill(['handled_by' => $user->id, 'handled_at' => now()]);
            }

            $locked->save();

            $audit->log(
                'contact_message.status_changed',
                $locked,
                ['status' => $from->value],
                ['status' => $to->value],
                "Status pesan kontak #{$locked->id} diubah dari {$from->label()} menjadi {$to->label()}.",
                Actor::staff($user),
            );
        });
    }
}
