<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PracticeSession;

/**
 * URL form walk-in `admin.appointments.create` untuk kembali ke langkah yang tepat (F7A-R20). Dipakai `BookingRedirect`
 * (galat dari `book()`) dan Form Request walk-in (galat validasi). Kunci query berurutan tetap: `pasien`, `poli`,
 * `dokter`, `tanggal`, `sesi`. `poli`/`dokter`/`tanggal` diturunkan dari sesi bila sesi ditemukan; `sesi` ikut hanya bila
 * `$keepSession` (galat bagian pembayaran). Id yang bukan bilangan bulat positif yang wajar diabaikan.
 */
final class WalkInReturn
{
    public static function url(mixed $profileId, mixed $sessionId, bool $keepSession): string
    {
        $profile = self::id($profileId);
        $sessionKey = self::id($sessionId);
        $session = $sessionKey === null ? null : PracticeSession::query()->with(['clinic', 'doctor'])->find($sessionKey);
        $query = [];

        if ($profile !== null) {
            $query['pasien'] = $profile;
        }

        if ($session !== null) {
            $query['poli'] = $session->clinic->slug;
            $query['dokter'] = $session->doctor->slug;
            $query['tanggal'] = $session->session_date->toDateString();

            if ($keepSession) {
                $query['sesi'] = $session->id;
            }
        }

        return route('admin.appointments.create', $query);
    }

    private static function id(mixed $value): ?int
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]{0,17}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
