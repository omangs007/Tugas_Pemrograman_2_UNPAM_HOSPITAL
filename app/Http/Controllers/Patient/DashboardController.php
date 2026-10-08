<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PracticeSession;
use App\Notifications\AppointmentScheduleChangedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Kunjungan mendatang pada kartu dashboard (F6-R28). */
    public const UPCOMING_LIMIT = 5;

    /** Pemberitahuan belum dibaca pada dashboard (F6-R28). */
    public const NOTICE_LIMIT = 5;

    /**
     * Dashboard pasien (F5-R22, F6-R28): kunjungan mendatang milik sendiri (aktif pada sesi yang belum berakhir, urut
     * tanggal lalu jam mulai, maksimal lima, relasi di-eager-load), banner jadwal berubah, dan pemberitahuan belum
     * dibaca. Akun pasien tanpa profil (data rusak) menjawab 404 (F5-R13).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->loadMissing('patientProfile')->patientProfile;

        abort_if($profile === null, 404);

        $now = CarbonImmutable::now();

        $upcoming = Appointment::query()
            ->forPatient($profile)
            ->upcomingActive($now)
            ->with(['clinic', 'doctor', 'practiceSession'])
            ->orderBy('appointments.session_date')
            ->orderBy(PracticeSession::query()->select('start_time')->whereColumn('practice_sessions.id', 'appointments.practice_session_id'))
            ->orderBy('appointments.id')
            ->limit(self::UPCOMING_LIMIT)
            ->get();

        $changedCount = Appointment::query()
            ->forPatient($profile)
            ->upcomingActive($now)
            ->scheduleChanged()
            ->count();

        $notices = $user->unreadNotifications()
            ->limit(self::NOTICE_LIMIT)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $this->text($notification->data, 'title', 'Pemberitahuan'),
                'body' => $this->text($notification->data, 'body', ''),
                'is_schedule_change' => $notification->type === AppointmentScheduleChangedNotification::class,
            ])
            ->all();

        return view('patient.dashboard', [
            'user' => $user,
            'profile' => $profile,
            'upcoming' => $upcoming,
            'changedCount' => $changedCount,
            'notices' => $notices,
        ]);
    }

    /** Nilai teks `data[$key]` notifikasi, atau `$fallback` bila bukan string atau kosong. */
    private function text(mixed $data, string $key, string $fallback): string
    {
        $value = is_array($data) ? ($data[$key] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
