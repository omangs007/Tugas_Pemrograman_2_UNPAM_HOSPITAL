<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\PracticeSession;
use App\Services\Booking\BookingCodeGenerator;
use Illuminate\Http\Request;

/**
 * Tujuan pengalihan setelah `BookingException` (F6-R10): tidak pernah `back()`, karena halaman tinjau adalah hasil
 * POST (bila di-GET menjadi 405). Penolakan pada `review`/`store` kembali ke langkah wizard yang dapat memperbaikinya;
 * penolakan pada `cancel` kembali ke detail pendaftaran; route lain kembali ke halaman sebelumnya.
 */
final class BookingRedirect
{
    public static function after(BookingException $e, Request $request): string
    {
        $routeName = $request->route()?->getName();

        if (in_array($routeName, ['patient.appointments.review', 'patient.appointments.store'], true)) {
            return self::forWizard($e, $request);
        }

        if ($routeName === 'patient.appointments.cancel') {
            return self::appointment(self::routeCode($request)) ?? SafeRedirect::back($request);
        }

        return self::forAdmin($routeName, $e, $request) ?? SafeRedirect::back($request);
    }

    /**
     * Pemetaan layar petugas (F7A-R20); route yang tidak dikenal mengembalikan null (kembali ke halaman sebelumnya).
     * Cabang `admin.appointments.store` (walk-in) dan `admin.queue.mark-no-show` (antrean) ditambah T9 dan T10.
     */
    private static function forAdmin(?string $routeName, BookingException $e, Request $request): ?string
    {
        return match ($routeName) {
            'admin.appointments.transition' => self::adminTransition($request),
            'admin.appointments.store' => self::adminWalkIn($e, $request),
            'admin.queue.mark-no-show' => self::adminQueue($e, $request),
            default => null,
        };
    }

    private static function forWizard(BookingException $e, Request $request): string
    {
        return match ($e->errorCode) {
            BookingException::DUPLICATE_BOOKING => self::appointment($e->context['appointment_code'] ?? null) ?? self::stepSession($request),
            BookingException::ACTIVE_LIMIT_REACHED => self::history(),
            BookingException::INVALID_PAYMENT, BookingException::SYSTEM_BUSY => self::stepData($request),
            // SESSION_UNAVAILABLE, OUTSIDE_WINDOW, CUTOFF_PASSED, QUOTA_FULL, OVERLAPPING_BOOKING: pilih sesi lain.
            default => self::stepSession($request),
        };
    }

    /** Langkah 4 (data pendaftaran) untuk sesi yang sama; tanpa sesi yang bisa dipakai, awal wizard. */
    private static function stepData(Request $request): string
    {
        $id = self::sessionId($request);

        return $id === null
            ? route('patient.appointments.create')
            : route('patient.appointments.create', ['sesi' => (string) $id]);
    }

    /** Langkah 3 (tanggal dan sesi) untuk dokter pemilik sesi; sesi tak ditemukan: awal wizard. */
    private static function stepSession(Request $request): string
    {
        $id = self::sessionId($request);
        $session = $id === null ? null : PracticeSession::query()->with(['clinic', 'doctor'])->find($id);

        if ($session === null) {
            return route('patient.appointments.create');
        }

        return route('patient.appointments.create', ['poli' => $session->clinic->slug, 'dokter' => $session->doctor->slug]);
    }

    private static function history(): string
    {
        return route('patient.appointments.index');
    }

    /** Detail pendaftaran berkode valid; `null` bila kode bukan string berpola UPH-XXXX-XXXX. */
    private static function appointment(mixed $code): ?string
    {
        if (! is_string($code) || ! BookingCodeGenerator::isValid($code)) {
            return null;
        }

        return route('patient.appointments.show', ['appointment' => $code]);
    }

    private static function routeCode(Request $request): ?string
    {
        $parameter = $request->route('appointment');

        if ($parameter instanceof Appointment) {
            return $parameter->booking_code;
        }

        return is_string($parameter) ? $parameter : null;
    }

    /**
     * Transisi status dari layar petugas (F7A-R19/R20): `kembali` yang sah (detail atau antrean), selain itu detail
     * pendaftaran; tanpa kode booking sah, daftar pendaftaran.
     */
    private static function adminTransition(Request $request): string
    {
        $code = self::routeCode($request);
        $fallback = is_string($code) && BookingCodeGenerator::isValid($code)
            ? route('admin.appointments.show', ['appointment' => $code])
            : route('admin.appointments.index');
        $back = $request->input('kembali');

        return AdminReturn::path(is_string($back) ? $back : null, $fallback);
    }

    /**
     * Form walk-in (F7A-R20): sesi-level kembali ke bagian 2 (`pasien`, `poli`, `dokter`, `tanggal`); pembayaran dan
     * sistem sibuk ke bagian 3 (ditambah `sesi`); profil nonaktif (kode SESSION_UNAVAILABLE tanpa `practice_session_id`
     * pada konteks) ke form tanpa pasien terpilih.
     */
    private static function adminWalkIn(BookingException $e, Request $request): string
    {
        if ($e->errorCode === BookingException::SESSION_UNAVAILABLE && ! isset($e->context['practice_session_id'])) {
            return route('admin.appointments.create');
        }

        $keepSession = in_array($e->errorCode, [BookingException::INVALID_PAYMENT, BookingException::SYSTEM_BUSY], true);

        return WalkInReturn::url($request->input('patient_profile_id'), $request->input('practice_session_id'), $keepSession);
    }

    /**
     * Tandai tidak hadir dari antrean (F7A-R20): kembali ke antrean sesi itu dengan `tanggal` (= `session_date`) dan
     * `sesi`. Sesi diambil dari parameter route `{session}` (model terikat atau id), lalu dari konteks galat; tanpa sesi
     * yang ditemukan, antrean polos. `kembali` tidak dipakai: aksi massal selalu kembali ke antrean sesi yang sama.
     */
    private static function adminQueue(BookingException $e, Request $request): string
    {
        $parameter = $request->route('session');
        $session = $parameter instanceof PracticeSession ? $parameter : null;

        if ($session === null) {
            $id = self::queueSessionId($parameter) ?? self::queueSessionId($e->context['practice_session_id'] ?? null);
            $session = $id === null ? null : PracticeSession::query()->find($id);
        }

        if ($session === null) {
            return route('admin.queue.index');
        }

        return route('admin.queue.index', ['tanggal' => $session->session_date->toDateString(), 'sesi' => $session->id]);
    }

    /** Id sesi hanya bila bilangan bulat positif yang wajar (larik, desimal, dan teks lain diabaikan). */
    private static function queueSessionId(mixed $value): ?int
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]{0,17}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    /** `practice_session_id` hanya dipakai bila bilangan bulat positif yang wajar (nilai larik/teks diabaikan). */
    private static function sessionId(Request $request): ?int
    {
        $value = $request->input('practice_session_id');

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]{0,17}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
