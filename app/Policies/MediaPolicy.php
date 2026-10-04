<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use App\Models\User;

/**
 * 04 §5.1 / F2-R16: izin ditentukan pemilik media.
 *
 * - Doctor => `doctors.manage`; Clinic dan Specialty => `clinics.manage`.
 * - `model_type` NULL: koleksi `logo` => `settings.manage`; koleksi `editor` => pengunggah sendiri atau
 *   salah satu dari `content.manage`/`clinics.manage`/`facilities.manage`; koleksi lain ditolak.
 * - `model_type` lain belum dipetakan sehingga ditolak (fase 4 menambah Facility, RoomCategory, dst.).
 *
 * Hanya akun staf. Super Admin lolos lewat Gate::before sebelum sampai ke sini.
 */
final class MediaPolicy
{
    /** Unggah gambar editor: pemegang izin kelompok konten. */
    public function upload(User $user): bool
    {
        return $user->isStaff() && $this->canUseEditor($user);
    }

    public function update(User $user, Media $media): bool
    {
        return $this->manages($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->manages($user, $media);
    }

    /**
     * Urutkan media satu pemilik. Media tanpa pemilik tidak punya urutan sehingga ditolak.
     */
    public function reorder(User $user, Media $anyMediaOfOwner): bool
    {
        return $anyMediaOfOwner->model_type !== null && $this->manages($user, $anyMediaOfOwner);
    }

    private function manages(User $user, Media $media): bool
    {
        if (! $user->isStaff()) {
            return false;
        }

        if ($media->model_type === null) {
            return match ($media->collection) {
                'logo' => $user->can('settings.manage'),
                'editor' => (int) $media->uploaded_by === $user->id || $this->canUseEditor($user),
                default => false,
            };
        }

        return match ($media->model_type) {
            Doctor::class => $user->can('doctors.manage'),
            Clinic::class, Specialty::class => $user->can('clinics.manage'),
            default => false,
        };
    }

    private function canUseEditor(User $user): bool
    {
        return $user->can('content.manage') || $user->can('clinics.manage') || $user->can('facilities.manage');
    }
}
