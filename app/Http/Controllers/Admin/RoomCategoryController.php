<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomCategoryRequest;
use App\Http\Requests\Admin\UpdateRoomAvailabilityRequest;
use App\Models\RoomCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin #36 tab Kamar dan Kapasitas (F-ADM-10, F-ADM-11). Struktur kategori (`facilities.manage`) dan ketersediaan
 * manual (`rooms.update-availability`) dipisah: ketersediaan hanya berubah lewat `availability()`, yang menulis
 * audit `room.availability_updated` sendiri. Kategori kamar tidak dirujuk tabel lain, jadi hapus selalu boleh (F4B-R20).
 */
class RoomCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', RoomCategory::class);

        // Tanpa paginasi dan filter (F4B-R16); eager load wajib karena mode ketat.
        $rooms = RoomCategory::query()
            ->with(['availabilityUpdater:id,name', 'media'])
            ->ordered()
            ->get();

        return view('admin.room-categories.index', ['rooms' => $rooms]);
    }

    public function create(): View
    {
        $this->authorize('create', RoomCategory::class);

        return view('admin.room-categories.create');
    }

    public function store(RoomCategoryRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', RoomCategory::class);

        DB::transaction(function () use ($request, $media): void {
            $room = RoomCategory::query()->create($request->roomCategoryAttributes() + [
                'beds_available' => $request->initialBedsAvailable(),
                'availability_updated_at' => now(),
                'availability_updated_by' => $request->user()?->id,
            ]);
            $this->saveMedia($request, $media, $room);
        });

        return redirect()->route('admin.room-categories.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.room_category')]));
    }

    public function edit(RoomCategory $roomCategory): View
    {
        $this->authorize('update', $roomCategory);

        return view('admin.room-categories.edit', ['room' => $roomCategory->load('media')]);
    }

    /**
     * Mengubah kapasitas tidak mengubah `availability_updated_*` (F4B-R18). Baris dikunci dan kapasitas diperiksa
     * ulang terhadap ketersediaan terbaru supaya CHECK database tidak pernah memicu 500.
     */
    public function update(RoomCategoryRequest $request, RoomCategory $roomCategory, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $roomCategory);

        DB::transaction(function () use ($request, $media, $roomCategory): void {
            $room = RoomCategory::query()->lockForUpdate()->findOrFail($roomCategory->getKey());
            $attributes = $request->roomCategoryAttributes();

            if ($attributes['bed_capacity'] < $room->beds_available) {
                throw ValidationException::withMessages([
                    'bed_capacity' => RoomCategoryRequest::belowAvailableMessage($room->beds_available),
                ]);
            }

            $room->update($attributes);
            $this->saveMedia($request, $media, $room);
        });

        return redirect()->route('admin.room-categories.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.room_category')]));
    }

    /**
     * Media ikut terhapus lewat trait HasMedia; transaksi wajib agar berkas dihapus setelah commit.
     */
    public function destroy(RoomCategory $roomCategory): RedirectResponse
    {
        $this->authorize('delete', $roomCategory);

        DB::transaction(fn (): ?bool => $roomCategory->delete());

        return redirect()->route('admin.room-categories.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.room_category')]));
    }

    /**
     * F4B-R17: transaksi dengan kunci baris, `saveQuietly()` agar observer tidak menulis `room_category.updated`, dan
     * satu audit eksplisit. Nilai yang sama dengan yang tersimpan tetap memperbarui waktu (konfirmasi petugas).
     */
    public function availability(UpdateRoomAvailabilityRequest $request, RoomCategory $roomCategory, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('updateAvailability', $roomCategory);

        $user = $request->user();
        $new = $request->bedsAvailable();
        $name = $roomCategory->name;

        DB::transaction(function () use ($roomCategory, $audit, $user, $new, &$name): void {
            $room = RoomCategory::query()->lockForUpdate()->findOrFail($roomCategory->getKey());

            if ($new > $room->bed_capacity) {
                throw ValidationException::withMessages([
                    'beds_available' => UpdateRoomAvailabilityRequest::exceedsCapacityMessage($room->bed_capacity),
                ]);
            }

            $old = $room->beds_available;
            $name = $room->name;

            $room->beds_available = $new;
            $room->availability_updated_at = now();
            $room->availability_updated_by = $user->id;
            $room->saveQuietly();

            $audit->log(
                'room.availability_updated',
                $room,
                ['beds_available' => $old],
                ['beds_available' => $new],
                "Ketersediaan {$room->name} diperbarui dari {$old} menjadi {$new} tempat tidur.",
                Actor::staff($user),
            );
        });

        return redirect()->route('admin.room-categories.index')
            ->with('success', "Ketersediaan {$name} berhasil diperbarui.");
    }

    private function saveMedia(RoomCategoryRequest $request, MediaService $media, RoomCategory $room): void
    {
        $user = $request->user();
        $cover = $request->coverFile();

        if ($cover !== null) {
            $media->replace($room, 'cover', $cover, ['alt_text' => $request->coverAlt()], $user);
        } elseif ($request->has('cover_alt')) {
            // Tanpa berkas baru: alt yang diedit diterapkan ke cover yang ada (caption tidak diubah).
            $existing = $room->media()->where('collection', 'cover')->first();

            if ($existing !== null && $existing->alt_text !== $request->coverAlt()) {
                $media->updateMeta($existing, $request->coverAlt(), $existing->caption);
            }
        }

        foreach ($request->galleryFiles() as $index => $file) {
            $media->store($file, 'gallery', $room, ['alt_text' => $request->galleryAlt($index)], $user);
        }
    }
}
