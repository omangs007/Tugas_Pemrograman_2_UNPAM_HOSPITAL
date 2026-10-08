<?php

namespace Tests\Feature\Patient;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentScheduleChangedNotification;
use App\Notifications\AppointmentStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Dashboard pasien fase 6 (#27, F6-R28) dan `patient.notifications.read` (F6-R29): F-PAS-01 (kunjungan mendatang),
 * EMP-04, NTF-01 (pemberitahuan belum dibaca), PAS-08 (notifikasi milik sendiri), PAS-10 (banner jadwal berubah
 * menaut ke filter riwayat), SEK-12 dan SEK-15 (tanpa BPJS; teks ter-escape). Sekarang = Senin 2026-10-05 07.00.
 */
class PatientNotificationsTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00'); // Senin
    }

    // --- pembantu ----------------------------------------------------------------------------

    /** Isi `<section aria-labelledby="{id}">` (header dan sidebar juga memuat teks "Daftar Kunjungan"). */
    private function section(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('#<section[^>]*aria-labelledby="'.preg_quote($id, '#').'"[^>]*>(.*?)</section>#s', $html, $matches),
            "Bagian {$id} tidak ditemukan.",
        );

        return $matches[1];
    }

    /** @param  list<string>  $needles */
    private function assertInOrder(array $needles, string $haystack): void
    {
        $offset = -1;

        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle);

            $this->assertNotFalse($position, "{$needle} tidak ditemukan.");
            $this->assertGreaterThan($offset, $position, "{$needle} berada di urutan yang salah.");
            $offset = $position;
        }
    }

    /**
     * Pendaftaran milik `$patient` pada sesi baru; kuota sesi dijaga konsisten oleh `bookAppointment()`.
     *
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $appointment
     */
    private function book(User $patient, array $session = [], AppointmentStatus $status = AppointmentStatus::Confirmed, array $appointment = []): Appointment
    {
        $created = $this->bookAppointment(
            $this->createSession($session),
            $status,
            ['patient_profile_id' => $patient->patientProfile->id] + $appointment,
        );

        return $created->load(['clinic', 'doctor', 'practiceSession']);
    }

    /**
     * Notifikasi database langsung ke tabel (bentuk `data` = 11 §10.1).
     *
     * @param  array<string, mixed>  $data
     */
    private function notice(User $user, array $data = [], string $type = AppointmentStatusChangedNotification::class, ?string $readAt = null): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $data + [
                'appointment_id' => 1,
                'booking_code' => 'UPH-AAAA-BBBB',
                'title' => 'Status pendaftaran berubah',
                'body' => 'Status pendaftaran Anda kini Dikonfirmasi.',
            ],
            'read_at' => $readAt,
        ]);
    }

    private function readUrl(DatabaseNotification|string $notification): string
    {
        return route('patient.notifications.read', ['id' => $notification instanceof DatabaseNotification ? $notification->id : $notification]);
    }

    private function showUrl(string $bookingCode): string
    {
        return route('patient.appointments.show', ['appointment' => $bookingCode]);
    }

    private function queryCountFor(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('patient.dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // --- kunjungan mendatang (F-PAS-01) ---------------------------------------------------------

    public function test_the_upcoming_card_lists_only_own_active_appointments_on_sessions_that_have_not_ended(): void
    {
        $me = $this->createPatient();
        $other = $this->createPatient();

        $today = $this->book($me, ['session_date' => '2026-10-05', 'start_time' => '09:00:00', 'end_time' => '12:00:00'], AppointmentStatus::CheckedIn);
        $pending = $this->book($me, ['session_date' => '2026-10-06'], AppointmentStatus::Pending);
        $confirmed = $this->book($me, ['session_date' => '2026-10-08'], AppointmentStatus::Confirmed);
        $cancelled = $this->book($me, ['session_date' => '2026-10-07'], AppointmentStatus::Cancelled);
        $completed = $this->book($me, ['session_date' => '2026-10-09'], AppointmentStatus::Completed);
        $noShow = $this->book($me, ['session_date' => '2026-10-10'], AppointmentStatus::NoShow);
        $past = $this->book($me, ['session_date' => '2026-10-04'], AppointmentStatus::Confirmed);
        $others = $this->book($other, ['session_date' => '2026-10-06'], AppointmentStatus::Confirmed);

        $html = $this->actingAs($me)->get(route('patient.dashboard'))->assertOk()->getContent();
        $visits = $this->section($html, 'kunjungan-judul');

        $this->assertInOrder([$today->booking_code, $pending->booking_code, $confirmed->booking_code], $visits);

        foreach ([$cancelled, $completed, $noShow, $past, $others] as $hidden) {
            $this->assertStringNotContainsString($hidden->booking_code, $html, "{$hidden->booking_code} tidak boleh tampil di dashboard.");
        }

        $this->assertStringNotContainsString('Belum ada pendaftaran', $visits);
    }

    public function test_the_upcoming_card_shows_at_most_five_appointments_earliest_first(): void
    {
        $me = $this->createPatient();
        $codes = [];

        foreach ([12, 6, 10, 7, 9, 8, 11] as $day) {
            $codes[$day] = $this->book($me, ['session_date' => sprintf('2026-10-%02d', $day)])->booking_code;
        }

        $visits = $this->section($this->actingAs($me)->get(route('patient.dashboard'))->assertOk()->getContent(), 'kunjungan-judul');

        $this->assertInOrder([$codes[6], $codes[7], $codes[8], $codes[9], $codes[10]], $visits);
        $this->assertStringNotContainsString($codes[11], $visits);
        $this->assertStringNotContainsString($codes[12], $visits);
        $this->assertSame(5, substr_count($visits, 'class="card"'));
    }

    public function test_appointments_on_the_same_day_are_ordered_by_session_start_time(): void
    {
        $me = $this->createPatient();

        $afternoon = $this->book($me, ['session_date' => '2026-10-06', 'start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $morning = $this->book($me, ['session_date' => '2026-10-06', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $visits = $this->section($this->actingAs($me)->get(route('patient.dashboard'))->assertOk()->getContent(), 'kunjungan-judul');

        $this->assertInOrder([$morning->booking_code, $afternoon->booking_code], $visits);
    }

    public function test_a_session_that_ended_earlier_today_is_not_an_upcoming_visit(): void
    {
        $me = $this->createPatient();
        $ended = $this->book($me, ['session_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
        $later = $this->book($me, ['session_date' => '2026-10-05', 'start_time' => '14:00:00', 'end_time' => '16:00:00']);

        $this->travelTo('2026-10-05 13:00:00');

        $html = $this->actingAs($me)->get(route('patient.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString($ended->booking_code, $html);
        $this->assertStringContainsString($later->booking_code, $this->section($html, 'kunjungan-judul'));
    }

    public function test_each_visit_card_shows_code_queue_doctor_clinic_schedule_status_and_a_detail_link(): void
    {
        $me = $this->createPatient();
        $visit = $this->book($me, ['session_date' => '2026-10-06', 'start_time' => '09:00:00', 'end_time' => '12:00:00'], AppointmentStatus::Pending);

        $visits = $this->section($this->actingAs($me)->get(route('patient.dashboard'))->assertOk()->getContent(), 'kunjungan-judul');

        $this->assertStringContainsString('href="'.$this->showUrl($visit->booking_code).'"', $visits);
        $this->assertStringContainsString('aria-label="Detail pendaftaran '.$visit->booking_code.'"', $visits);
        $this->assertStringContainsString('Antrean: '.$visit->queue_label, $visits);
        $this->assertStringContainsString($visit->doctor->display_name, $visits);
        $this->assertStringContainsString($visit->clinic->name, $visits);
        $this->assertStringContainsString('<time datetime="2026-10-06">Selasa, 6 Oktober 2026</time>', $visits);
        $this->assertStringContainsString('09.00–12.00', $visits);
        $this->assertStringContainsString('Menunggu konfirmasi', $visits);
        $this->assertStringContainsString('href="'.route('patient.appointments.index').'"', $visits);
        $this->assertStringContainsString('Lihat semua riwayat', $visits);
    }

    /** F5-R13 dan EMP-04: tanpa kunjungan aktif mendatang kartu kosong dengan tombol "Daftar Kunjungan". */
    public function test_the_card_is_empty_when_only_inactive_or_past_appointments_exist(): void
    {
        $me = $this->createPatient();
        $this->book($me, ['session_date' => '2026-10-06'], AppointmentStatus::Cancelled);
        $this->book($me, ['session_date' => '2026-10-04'], AppointmentStatus::Completed);

        $response = $this->actingAs($me)->get(route('patient.dashboard'))->assertOk();
        $visits = $this->section($response->getContent(), 'kunjungan-judul');

        $this->assertStringContainsString('Belum ada pendaftaran', $visits);
        $this->assertStringContainsString('Pendaftaran kunjungan Anda akan tampil di sini.', $visits);
        $this->assertStringContainsString('<a class="btn btn-primary" href="'.route('patient.appointments.create').'">Daftar Kunjungan</a>', $visits);
        $this->assertStringNotContainsString('Lihat Jadwal Dokter', $visits);
        $this->assertStringNotContainsString('Lihat semua riwayat', $visits);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    /** Eager load: mode ketat melarang lazy loading; jumlah kueri tidak bertambah menurut jumlah kunjungan. */
    public function test_the_dashboard_does_not_grow_its_query_count_with_more_appointments(): void
    {
        $me = $this->createPatient();
        $this->book($me, ['session_date' => '2026-10-06']);

        $this->actingAs($me)->get(route('patient.dashboard'))->assertOk(); // pemanasan cache pengaturan

        $one = $this->queryCountFor($me);

        foreach ([7, 8, 9, 10] as $day) {
            $this->book($me, ['session_date' => sprintf('2026-10-%02d', $day)]);
        }

        $this->assertSame($one, $this->queryCountFor($me));
    }

    // --- banner jadwal berubah (PAS-10, sisi dashboard) -----------------------------------------

    public function test_the_schedule_banner_counts_only_own_active_upcoming_changes_and_links_to_the_history_filter(): void
    {
        $me = $this->createPatient();
        $other = $this->createPatient();
        $changedAt = '2026-10-05 06:00:00';

        $this->book($me, ['session_date' => '2026-10-06'], AppointmentStatus::Confirmed, ['schedule_changed_at' => $changedAt]);
        $this->book($me, ['session_date' => '2026-10-07'], AppointmentStatus::Pending, ['schedule_changed_at' => $changedAt]);
        $this->book($me, ['session_date' => '2026-10-08'], AppointmentStatus::Confirmed);
        $this->book($me, ['session_date' => '2026-10-09'], AppointmentStatus::Cancelled, ['schedule_changed_at' => $changedAt]);
        $this->book($me, ['session_date' => '2026-10-03'], AppointmentStatus::Confirmed, ['schedule_changed_at' => $changedAt]);
        $this->book($other, ['session_date' => '2026-10-06'], AppointmentStatus::Confirmed, ['schedule_changed_at' => $changedAt]);

        $response = $this->actingAs($me)->get(route('patient.dashboard'))->assertOk()
            ->assertSee('Jadwal 2 pendaftaran Anda berubah.')
            ->assertSee('href="'.route('patient.appointments.index', ['jadwal_berubah' => 1]).'"', false)
            ->assertSee('Lihat pendaftaran');

        $visits = $this->section($response->getContent(), 'kunjungan-judul');
        $this->assertSame(2, substr_count($visits, 'Jadwal berubah pada 5 Okt 2026.'));
        $this->assertStringContainsString('role="status"', $response->getContent());
    }

    public function test_there_is_no_schedule_banner_when_nothing_changed(): void
    {
        $me = $this->createPatient();
        $this->book($me, ['session_date' => '2026-10-06']);

        $this->actingAs($me)->get(route('patient.dashboard'))->assertOk()
            ->assertDontSee('pendaftaran Anda berubah')
            ->assertDontSee('jadwal_berubah=1', false)
            ->assertDontSee('Jadwal berubah pada');
    }

    // --- pemberitahuan belum dibaca (NTF-01) ---------------------------------------------------

    public function test_unread_notifications_are_listed_with_detail_and_mark_read_buttons(): void
    {
        $me = $this->createPatient();
        $other = $this->createPatient();

        $status = $this->notice($me, ['title' => 'Pendaftaran dikonfirmasi', 'body' => 'Status pendaftaran UPH-AAAA-BBBB kini Dikonfirmasi.']);
        $schedule = $this->notice($me, [
            'title' => 'Jadwal kunjungan berubah',
            'body' => 'Jam praktik kini 10.00–14.00 (sebelumnya 09.00–13.00).',
            'old_range' => '09.00–13.00',
            'new_range' => '10.00–14.00',
        ], AppointmentScheduleChangedNotification::class);
        $this->notice($me, ['title' => 'Sudah dibaca dulu'], AppointmentStatusChangedNotification::class, '2026-10-04 08:00:00');
        $this->notice($other, ['title' => 'Milik pasien lain']);

        $response = $this->actingAs($me)->get(route('patient.dashboard'))->assertOk();
        $notices = $this->section($response->getContent(), 'pemberitahuan-judul');

        $this->assertStringContainsString('Pemberitahuan', $notices);
        $this->assertStringContainsString('Pendaftaran dikonfirmasi', $notices);
        $this->assertStringContainsString('Status pendaftaran UPH-AAAA-BBBB kini Dikonfirmasi.', $notices);
        $this->assertStringContainsString('Jadwal kunjungan berubah', $notices);
        $this->assertStringContainsString('Jam praktik kini 10.00–14.00 (sebelumnya 09.00–13.00).', $notices);
        $this->assertStringNotContainsString('Sudah dibaca dulu', $response->getContent());
        $this->assertStringNotContainsString('Milik pasien lain', $response->getContent());

        // Jenis menentukan warna: jadwal berubah = peringatan, status = informasi.
        $this->assertSame(1, substr_count($notices, 'alert-warning'));
        $this->assertSame(1, substr_count($notices, 'alert-info'));

        foreach ([$status, $schedule] as $notification) {
            $this->assertStringContainsString('action="'.$this->readUrl($notification).'"', $notices);
        }

        $this->assertSame(2, substr_count($notices, 'name="tujuan" value="detail"'));
        $this->assertSame(2, substr_count($notices, 'name="tujuan" value="dashboard"'));
        $this->assertSame(2, substr_count($notices, '>Lihat detail<'));
        $this->assertSame(2, substr_count($notices, '>Tandai sudah dibaca<'));
        $this->assertSame(4, substr_count($notices, 'name="_token"'));
    }

    public function test_at_most_five_unread_notifications_are_shown(): void
    {
        $me = $this->createPatient();

        foreach (range(1, 7) as $number) {
            $this->notice($me, ['title' => "Pemberitahuan nomor {$number}"]);
        }

        $notices = $this->section($this->actingAs($me)->get(route('patient.dashboard'))->assertOk()->getContent(), 'pemberitahuan-judul');

        $this->assertSame(5, substr_count($notices, 'name="tujuan" value="dashboard"'));
    }

    public function test_the_notification_section_is_absent_when_every_notification_is_read(): void
    {
        $me = $this->createPatient();
        $this->notice($me, ['title' => 'Sudah dibaca'], AppointmentStatusChangedNotification::class, '2026-10-04 08:00:00');

        $this->actingAs($me)->get(route('patient.dashboard'))->assertOk()
            ->assertDontSee('pemberitahuan-judul', false)
            ->assertDontSee('Sudah dibaca');
    }

    /** SEK-15: judul dan isi notifikasi tampil sebagai teks. */
    public function test_notification_text_is_escaped(): void
    {
        $me = $this->createPatient();
        $this->notice($me, ['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(2)>']);

        $this->actingAs($me)->get(route('patient.dashboard'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(2)&gt;', false);
    }

    /** NTF-01 dan SEK-12: notifikasi nyata muncul di dashboard tanpa nomor BPJS, polis, atau keluhan. */
    public function test_a_real_status_notification_appears_on_the_dashboard_without_sensitive_data(): void
    {
        Mail::fake();

        $me = $this->createPatient();
        $appointment = $this->book($me, ['session_date' => '2026-10-06'], AppointmentStatus::Confirmed, [
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => '0009900000011',
            'payment_reference_last4' => '0011',
            'complaint' => 'Keluhan sangat rahasia',
        ]);

        $me->notify(new AppointmentStatusChangedNotification($appointment, AppointmentStatus::Pending, AppointmentStatus::Confirmed));

        $this->assertSame(1, $me->unreadNotifications()->count());

        $response = $this->actingAs($me)->get(route('patient.dashboard'))->assertOk();
        $html = $response->getContent();

        $notices = $this->section($html, 'pemberitahuan-judul');
        $notification = $me->unreadNotifications()->firstOrFail();

        $this->assertStringContainsString('Pemberitahuan', $notices);
        $this->assertStringContainsString('action="'.$this->readUrl($notification).'"', $notices);
        $this->assertSame($appointment->booking_code, $notification->data['booking_code']);
        $this->assertStringNotContainsString('0009900000011', $html);
        $this->assertStringNotContainsString('Keluhan sangat rahasia', $html);
        $this->assertStringNotContainsString('0009900000011', json_encode($me->notifications()->first()->data, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Keluhan sangat rahasia', json_encode($me->notifications()->first()->data, JSON_THROW_ON_ERROR));
    }

    // --- patient.notifications.read (F6-R29, PAS-08) ----------------------------------------------

    public function test_reading_an_own_notification_marks_it_read_and_goes_to_the_appointment_detail(): void
    {
        $me = $this->createPatient();
        $appointment = $this->book($me, ['session_date' => '2026-10-06']);
        $notification = $this->notice($me, ['booking_code' => $appointment->booking_code]);

        $this->actingAs($me)->post($this->readUrl($notification), ['tujuan' => 'detail'])
            ->assertRedirect($this->showUrl($appointment->booking_code));

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(0, $me->unreadNotifications()->count());
    }

    public function test_the_dashboard_destination_returns_to_the_dashboard(): void
    {
        $me = $this->createPatient();
        $appointment = $this->book($me, ['session_date' => '2026-10-06']);
        $notification = $this->notice($me, ['booking_code' => $appointment->booking_code]);

        $this->actingAs($me)->post($this->readUrl($notification), ['tujuan' => 'dashboard'])
            ->assertRedirect(route('patient.dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_without_a_destination_the_default_is_the_appointment_detail(): void
    {
        $me = $this->createPatient();
        $appointment = $this->book($me, ['session_date' => '2026-10-06']);
        $notification = $this->notice($me, ['booking_code' => $appointment->booking_code]);

        $this->actingAs($me)->post($this->readUrl($notification))
            ->assertRedirect($this->showUrl($appointment->booking_code));
    }

    /** VAL: `tujuan` berupa larik atau nilai liar tidak pernah 500; jatuh ke tujuan bawaan. */
    public function test_a_wild_destination_value_falls_back_to_the_default_without_an_error(): void
    {
        $me = $this->createPatient();
        $appointment = $this->book($me, ['session_date' => '2026-10-06']);

        foreach ([['dashboard'], 'http://jahat.example', str_repeat('x', 5000), ''] as $wild) {
            $notification = $this->notice($me, ['booking_code' => $appointment->booking_code]);

            $this->actingAs($me)->post($this->readUrl($notification), ['tujuan' => $wild])
                ->assertRedirect($this->showUrl($appointment->booking_code));
        }
    }

    /** Review Focus E.3: kode booking milik pasien lain di `data` tidak pernah menjadi tautan detail. */
    public function test_a_booking_code_of_someone_else_in_the_data_leads_to_the_history_not_the_detail(): void
    {
        $me = $this->createPatient();
        $other = $this->createPatient();
        $theirs = $this->book($other, ['session_date' => '2026-10-06']);
        $notification = $this->notice($me, ['booking_code' => $theirs->booking_code]);

        $this->actingAs($me)->post($this->readUrl($notification), ['tujuan' => 'detail'])
            ->assertRedirect(route('patient.appointments.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_notification_without_a_usable_booking_code_leads_to_the_history(): void
    {
        $me = $this->createPatient();

        foreach ([null, '', 123, ['UPH-AAAA-BBBB'], 'UPH-TIDAK-ADAA'] as $code) {
            $notification = $this->notice($me, ['booking_code' => $code]);

            $this->actingAs($me)->post($this->readUrl($notification), ['tujuan' => 'detail'])
                ->assertRedirect(route('patient.appointments.index'));
        }
    }

    /** PAS-08: notifikasi milik pasien lain 404 dan `read_at`-nya tidak berubah. */
    public function test_someone_elses_notification_answers_404_and_stays_unread(): void
    {
        $me = $this->createPatient();
        $other = $this->createPatient();
        $theirs = $this->notice($other);
        $mine = $this->notice($me);

        $this->actingAs($me)->post($this->readUrl($theirs), ['tujuan' => 'detail'])->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
        $this->assertNull($mine->fresh()->read_at);
    }

    /** @return array<string, array{string}> */
    public static function invalidIds(): array
    {
        return [
            'bukan UUID' => ['abc'],
            'angka' => ['1'],
            'UUID yang tidak ada' => ['0b8e6f0e-3c0d-4a55-8d5e-2f6b8f6f0a11'],
        ];
    }

    #[DataProvider('invalidIds')]
    public function test_an_unknown_or_malformed_id_answers_404(string $id): void
    {
        $this->actingAs($this->createPatient())->post('/pasien/notifikasi/'.$id.'/baca')->assertNotFound();
    }

    public function test_reading_twice_keeps_the_first_read_time_and_other_notifications_stay_unread(): void
    {
        $me = $this->createPatient();
        $first = $this->notice($me);
        $second = $this->notice($me);

        $this->actingAs($me)->post($this->readUrl($first), ['tujuan' => 'dashboard']);
        $firstReadAt = $first->fresh()->read_at->toDateTimeString();

        $this->travelTo('2026-10-05 09:00:00');
        $this->actingAs($me)->post($this->readUrl($first), ['tujuan' => 'dashboard'])->assertRedirect(route('patient.dashboard'));

        $this->assertSame('2026-10-05 07:00:00', $firstReadAt);
        $this->assertSame($firstReadAt, $first->fresh()->read_at->toDateTimeString());
        $this->assertNull($second->fresh()->read_at);
    }
}
