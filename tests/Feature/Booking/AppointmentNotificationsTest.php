<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\AppointmentScheduleChangedNotification;
use App\Notifications\AppointmentStatusChangedNotification;
use App\Services\Booking\AppointmentNotifier;
use Database\Seeders\SettingsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F6-R14, F-SYS-19, F-PAS-10. NTF-01 (satu notifikasi `database`, tanpa data sensitif), NTF-02 (surel `confirmed` dan
 * `cancelled`), NTF-03 (profil walk-in tanpa akun), NTF-07 (kanal per status menurut K-13 = b, termasuk
 * `AppointmentScheduleChangedNotification`), Review Focus 5 (tanpa BPJS/polis/keluhan/alamat/telepon), dan
 * pola fase 5 (kegagalan kirim dilaporkan tanpa melempar; kirim sesudah commit; tidak terkirim bila rollback).
 */
class AppointmentNotificationsTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-08 09:30:00');
        app()->setLocale('id');
        $this->seed(SettingsSeeder::class);
    }

    // ------------------------------------------------------------------ pembantu

    private function notifier(): AppointmentNotifier
    {
        return $this->app->make(AppointmentNotifier::class);
    }

    /**
     * Appointment pada sesi besok 08.00-12.00 (kuota 5) milik profil `$owner`, dengan relasi yang dibutuhkan
     * notifikasi sudah dimuat.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function appointmentFor(User|PatientProfile $owner, AppointmentStatus $status = AppointmentStatus::Pending, array $overrides = []): Appointment
    {
        $profile = $owner instanceof User ? $owner->patientProfile : $owner;
        $session = $this->createSession(['doctor_schedule_id' => null, 'quota' => 5]);

        $appointment = $this->bookAppointment($session, $status, ['patient_profile_id' => $profile->id] + $overrides);

        return $this->loaded($appointment);
    }

    private function loaded(Appointment $appointment): Appointment
    {
        return Appointment::query()
            ->with(['doctor', 'clinic', 'practiceSession', 'patientProfile.user'])
            ->findOrFail($appointment->id);
    }

    private function dateLabel(Appointment $appointment): string
    {
        return $appointment->session_date->translatedFormat('l, j F Y');
    }

    private function visit(Appointment $appointment): string
    {
        return $appointment->doctor->display_name.' di '.$appointment->clinic->name
            .' pada '.$this->dateLabel($appointment).' pukul '.$appointment->practiceSession->time_range.' WIB';
    }

    private function mailLines(MailMessage $mail): string
    {
        return implode("\n", array_map('strval', array_merge($mail->introLines, $mail->outroLines)));
    }

    private function sentMailCount(): int
    {
        return count(app('mailer')->getSymfonyTransport()->messages());
    }

    // ------------------------------------------------------------------ kanal per status (NTF-07, K-13 = b)

    /**
     * @return array<string, array{0: AppointmentStatus, 1: list<string>}>
     */
    public static function channelsPerStatus(): array
    {
        return [
            'pending' => [AppointmentStatus::Pending, ['database']],
            'confirmed' => [AppointmentStatus::Confirmed, ['database', 'mail']],
            'checked_in' => [AppointmentStatus::CheckedIn, ['database']],
            'completed' => [AppointmentStatus::Completed, ['database']],
            'cancelled' => [AppointmentStatus::Cancelled, ['database', 'mail']],
            'no_show' => [AppointmentStatus::NoShow, ['database']],
        ];
    }

    /**
     * @param  list<string>  $channels
     */
    #[DataProvider('channelsPerStatus')]
    public function test_via_follows_the_target_status_for_every_status(AppointmentStatus $to, array $channels): void
    {
        $user = $this->createPatient();

        // via() hanya memakai status tujuan, bukan status yang tersimpan pada appointment.
        $notification = new AppointmentStatusChangedNotification(new Appointment, AppointmentStatus::Pending, $to);

        $this->assertSame($channels, $notification->via($user));
    }

    public function test_a_check_in_correction_uses_database_only_but_other_confirmations_also_mail(): void
    {
        $user = $this->createPatient();
        $make = fn (?AppointmentStatus $from, AppointmentStatus $to) => (new AppointmentStatusChangedNotification(new Appointment, $from, $to))->via($user);

        $this->assertSame(['database'], $make(AppointmentStatus::CheckedIn, AppointmentStatus::Confirmed));
        $this->assertSame(['database', 'mail'], $make(AppointmentStatus::Pending, AppointmentStatus::Confirmed));
        $this->assertSame(['database', 'mail'], $make(null, AppointmentStatus::Confirmed));
        $this->assertSame(['database', 'mail'], $make(AppointmentStatus::CheckedIn, AppointmentStatus::Cancelled));
    }

    public function test_schedule_changed_notification_always_uses_database_and_mail(): void
    {
        $user = $this->createPatient();

        $notification = new AppointmentScheduleChangedNotification(new Appointment, '09.00–13.00', '10.00–14.00');

        $this->assertSame(['database', 'mail'], $notification->via($user));
    }

    public function test_the_notification_classes_are_synchronous(): void
    {
        $appointment = new Appointment;

        $this->assertNotInstanceOf(ShouldQueue::class, new AppointmentStatusChangedNotification($appointment, null, AppointmentStatus::Pending));
        $this->assertNotInstanceOf(ShouldQueue::class, new AppointmentScheduleChangedNotification($appointment, '09.00–13.00', '10.00–14.00'));
    }

    /**
     * @param  list<string>  $channels
     */
    #[DataProvider('channelsPerStatus')]
    public function test_the_notifier_sends_each_status_through_the_k13_channels(AppointmentStatus $to, array $channels): void
    {
        Notification::fake();
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, $to);
        $from = $to === AppointmentStatus::Pending ? null : AppointmentStatus::Pending;

        $this->notifier()->statusChanged($appointment, $from, $to);

        Notification::assertSentToTimes($user, AppointmentStatusChangedNotification::class, 1);
        Notification::assertSentTo(
            $user,
            AppointmentStatusChangedNotification::class,
            fn (AppointmentStatusChangedNotification $notification, array $sentChannels): bool => $sentChannels === $channels
                && $notification->to === $to
                && $notification->from === $from,
        );
    }

    public function test_the_notifier_sends_a_schedule_change_to_the_account_owner_once(): void
    {
        Notification::fake();
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);

        $this->notifier()->scheduleChanged($appointment, '09.00–13.00', '10.00–14.00');

        Notification::assertSentToTimes($user, AppointmentScheduleChangedNotification::class, 1);
        Notification::assertSentTo(
            $user,
            AppointmentScheduleChangedNotification::class,
            fn (AppointmentScheduleChangedNotification $notification, array $channels): bool => $channels === ['database', 'mail']
                && $notification->oldRange === '09.00–13.00'
                && $notification->newRange === '10.00–14.00',
        );
    }

    // ------------------------------------------------------------------ NTF-01: baris database

    public function test_a_status_change_stores_one_unread_database_notification_with_the_agreed_shape(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Pending);

        $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Pending);

        $stored = $user->notifications()->sole();
        $data = $stored->data;
        $code = $appointment->booking_code;

        $this->assertSame(AppointmentStatusChangedNotification::class, $stored->type);
        $this->assertNull($stored->read_at);
        $this->assertEqualsCanonicalizing(['appointment_id', 'booking_code', 'status', 'title', 'body'], array_keys($data));
        $this->assertSame($appointment->id, $data['appointment_id']);
        $this->assertSame($code, $data['booking_code']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('Pendaftaran diterima', $data['title']);
        $this->assertSame(
            "Pendaftaran {$code} untuk {$this->visit($appointment)} telah diterima dan menunggu konfirmasi petugas. Nomor antrean Anda {$appointment->queue_label}.",
            $data['body'],
        );
        $this->assertSame(0, $this->sentMailCount(), 'Status pending tidak dikirim lewat surel (K-13 = b).');
    }

    /**
     * @return array<string, array{0: AppointmentStatus, 1: ?AppointmentStatus, 2: string}>
     */
    public static function titles(): array
    {
        return [
            'dibuat pending' => [AppointmentStatus::Pending, null, 'Pendaftaran diterima'],
            'dikonfirmasi' => [AppointmentStatus::Confirmed, AppointmentStatus::Pending, 'Pendaftaran dikonfirmasi'],
            'check-in' => [AppointmentStatus::CheckedIn, AppointmentStatus::Confirmed, 'Check-in berhasil'],
            'selesai' => [AppointmentStatus::Completed, AppointmentStatus::CheckedIn, 'Kunjungan selesai'],
            'dibatalkan' => [AppointmentStatus::Cancelled, AppointmentStatus::Confirmed, 'Pendaftaran dibatalkan'],
            'tidak hadir' => [AppointmentStatus::NoShow, AppointmentStatus::Confirmed, 'Ditandai tidak hadir'],
            'koreksi check-in' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, 'Check-in dikoreksi'],
        ];
    }

    #[DataProvider('titles')]
    public function test_the_title_describes_the_transition(AppointmentStatus $to, ?AppointmentStatus $from, string $title): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, $to);

        $data = (new AppointmentStatusChangedNotification($appointment, $from, $to))->toArray($user);

        $this->assertSame($title, $data['title']);
        $this->assertSame($to->value, $data['status']);
        $this->assertStringContainsString($appointment->booking_code, $data['body']);
    }

    public function test_a_cancellation_reason_is_part_of_the_body_and_the_mail_only_when_given(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Cancelled);

        $with = new AppointmentStatusChangedNotification($appointment, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'Berhalangan hadir');
        $without = new AppointmentStatusChangedNotification($appointment, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled);

        $this->assertStringEndsWith('Kuota telah dilepas. Alasan: Berhalangan hadir.', $with->toArray($user)['body']);
        $this->assertStringContainsString('Alasan: Berhalangan hadir.', $this->mailLines($with->toMail($user)));
        $this->assertStringNotContainsString('Alasan', $without->toArray($user)['body']);
        $this->assertStringNotContainsString('Alasan', $this->mailLines($without->toMail($user)));
    }

    // ------------------------------------------------------------------ NTF-02: surel

    public function test_a_confirmed_status_sends_one_mail_to_the_account_with_code_and_schedule(): void
    {
        $user = $this->createPatient(['email' => 'pasien.notif@contoh.test']);
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);

        $this->notifier()->statusChanged($appointment, AppointmentStatus::Pending, AppointmentStatus::Confirmed);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('pasien.notif@contoh.test', $message->getTo()[0]->getAddress());
        $this->assertSame("Status pendaftaran {$appointment->booking_code}: Dikonfirmasi", $message->getSubject());
        $this->assertSame(1, $user->notifications()->count(), 'Kanal database tetap ikut.');
    }

    public function test_the_confirmed_mail_lists_code_doctor_clinic_schedule_queue_and_status(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);

        $mail = (new AppointmentStatusChangedNotification($appointment, AppointmentStatus::Pending, AppointmentStatus::Confirmed))->toMail($user);
        $lines = $this->mailLines($mail);

        $this->assertSame("Status pendaftaran {$appointment->booking_code}: Dikonfirmasi", $mail->subject);
        $this->assertSame("Halo, {$user->name}!", $mail->greeting);
        $this->assertSame('Lihat Pendaftaran', $mail->actionText);
        $this->assertSame(route('patient.appointments.show', $appointment), $mail->actionUrl);
        $this->assertStringContainsString($appointment->booking_code, (string) $mail->actionUrl);

        foreach ([
            'Pendaftaran kunjungan Anda telah dikonfirmasi.',
            "Kode booking: {$appointment->booking_code}",
            "Dokter: {$appointment->doctor->display_name}",
            "Poliklinik: {$appointment->clinic->name}",
            "Jadwal: {$this->dateLabel($appointment)}, pukul {$appointment->practiceSession->time_range} WIB",
            "Nomor antrean: {$appointment->queue_label}",
            'Status: Dikonfirmasi',
            'Surel ini dikirim otomatis.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $lines);
        }

        $this->assertStringContainsString($appointment->booking_code, (string) $mail->render());
    }

    public function test_the_cancelled_mail_states_the_cancellation_and_the_reason(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Cancelled);

        $mail = (new AppointmentStatusChangedNotification($appointment, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'Jadwal bentrok dengan pekerjaan'))->toMail($user);
        $lines = $this->mailLines($mail);

        $this->assertSame("Status pendaftaran {$appointment->booking_code}: Dibatalkan", $mail->subject);
        $this->assertStringContainsString('Pendaftaran kunjungan Anda telah dibatalkan dan kuota telah dilepas.', $lines);
        $this->assertStringContainsString('Status: Dibatalkan', $lines);
        $this->assertStringContainsString('Alasan: Jadwal bentrok dengan pekerjaan.', $lines);
    }

    public function test_the_check_in_correction_mail_explains_the_correction(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);

        $mail = (new AppointmentStatusChangedNotification($appointment, AppointmentStatus::CheckedIn, AppointmentStatus::Confirmed, 'Salah memilih pasien'))->toMail($user);

        $this->assertStringContainsString('Check-in pendaftaran Anda dikoreksi oleh petugas', $this->mailLines($mail));
        $this->assertStringContainsString('Alasan: Salah memilih pasien.', $this->mailLines($mail));
    }

    public function test_a_pending_status_does_not_send_mail_even_through_the_notifier(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Pending);

        $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Pending);
        $this->notifier()->statusChanged($appointment, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn);

        $this->assertSame(0, $this->sentMailCount());
        $this->assertSame(2, $user->notifications()->count());
    }

    // ------------------------------------------------------------------ perubahan jadwal

    public function test_the_schedule_change_data_has_the_agreed_shape(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);

        $this->notifier()->scheduleChanged($appointment, '08.00–12.00', '10.00–14.00');

        $stored = $user->notifications()->sole();
        $data = $stored->data;

        $this->assertSame(AppointmentScheduleChangedNotification::class, $stored->type);
        $this->assertEqualsCanonicalizing(['appointment_id', 'booking_code', 'old_range', 'new_range', 'title', 'body'], array_keys($data));
        $this->assertSame($appointment->id, $data['appointment_id']);
        $this->assertSame($appointment->booking_code, $data['booking_code']);
        $this->assertSame('08.00–12.00', $data['old_range']);
        $this->assertSame('10.00–14.00', $data['new_range']);
        $this->assertSame('Jadwal kunjungan berubah', $data['title']);
        $this->assertSame(
            "Jam praktik {$appointment->doctor->display_name} pada {$this->dateLabel($appointment)} kini 10.00–14.00 (sebelumnya 08.00–12.00). "
                ."Pendaftaran {$appointment->booking_code} tetap berlaku; batalkan atau hubungi rumah sakit bila jadwal baru tidak sesuai.",
            $data['body'],
        );
        $this->assertSame(1, $this->sentMailCount(), 'Perubahan jadwal juga dikirim lewat surel (K-13 = b).');
    }

    public function test_the_schedule_change_mail_shows_old_and_new_hours_and_the_hospital_contacts(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);

        $mail = (new AppointmentScheduleChangedNotification($appointment, '08.00–12.00', '10.00–14.00'))->toMail($user);
        $lines = $this->mailLines($mail);

        $this->assertSame("Perubahan jadwal praktik pendaftaran {$appointment->booking_code}", $mail->subject);
        $this->assertSame('Lihat Pendaftaran', $mail->actionText);
        $this->assertSame(route('patient.appointments.show', $appointment), $mail->actionUrl);

        foreach ([
            'Jam praktik dokter untuk pendaftaran kunjungan Anda telah berubah.',
            "Kode booking: {$appointment->booking_code}",
            'Jam praktik semula: 08.00–12.00 WIB',
            'Jam praktik baru: 10.00–14.00 WIB',
            'batalkan pendaftaran atau hubungi rumah sakit',
            'Telepon rumah sakit: (021) 0000-0000',
            'https://wa.me/6280000000000',
        ] as $expected) {
            $this->assertStringContainsString($expected, $lines);
        }

        $this->assertStringContainsString($appointment->booking_code, (string) $mail->render());
    }

    // ------------------------------------------------------------------ NTF-03: tanpa akun

    public function test_a_walk_in_profile_without_an_account_receives_nothing_and_causes_no_error(): void
    {
        $walkIn = PatientProfile::factory()->create();
        $appointment = $this->appointmentFor($walkIn, AppointmentStatus::Confirmed);
        Exceptions::fake();

        $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Confirmed);
        $this->notifier()->statusChanged($appointment, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'Dokter berhalangan');
        $this->notifier()->scheduleChanged($appointment, '08.00–12.00', '10.00–14.00');

        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, $this->sentMailCount());
        Exceptions::assertNothingReported();
    }

    public function test_notification_fake_sees_no_notification_for_a_walk_in_profile(): void
    {
        Notification::fake();
        $walkIn = PatientProfile::factory()->create();
        $appointment = $this->appointmentFor($walkIn, AppointmentStatus::Confirmed);

        $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Confirmed);

        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------------ sesudah commit / rollback

    public function test_the_notification_is_sent_only_after_the_transaction_commits(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Pending);
        $baseLevel = DB::transactionLevel();
        $levels = [];
        Event::listen(NotificationSending::class, function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        DB::transaction(function () use ($appointment, $user, &$levels): void {
            $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Pending);

            $this->assertSame([], $levels, 'Belum ada pengiriman di dalam transaksi.');
            $this->assertSame(0, $user->notifications()->count());
        });

        $this->assertSame([$baseLevel], $levels, 'Tepat satu pengiriman (kanal database), di luar transaksi pendaftaran.');
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_nothing_is_sent_when_the_transaction_is_rolled_back(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);

        try {
            DB::transaction(function () use ($appointment): void {
                $this->notifier()->statusChanged($appointment, AppointmentStatus::Pending, AppointmentStatus::Confirmed);

                throw new RuntimeException('Transaksi dibatalkan.');
            });
            $this->fail('Pengecualian transaksi seharusnya dilempar.');
        } catch (RuntimeException $e) {
            $this->assertSame('Transaksi dibatalkan.', $e->getMessage());
        }

        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, $this->sentMailCount());
    }

    public function test_a_notification_inside_a_rolled_back_nested_transaction_is_discarded_but_the_outer_one_is_sent(): void
    {
        $user = $this->createPatient();
        $first = $this->appointmentFor($user, AppointmentStatus::Pending);
        $second = $this->appointmentFor($user, AppointmentStatus::Pending);

        DB::transaction(function () use ($first, $second): void {
            $this->notifier()->statusChanged($first, null, AppointmentStatus::Pending);

            try {
                DB::transaction(function () use ($second): void {
                    $this->notifier()->statusChanged($second, null, AppointmentStatus::Pending);

                    throw new RuntimeException('Savepoint dibatalkan.');
                });
            } catch (RuntimeException) {
                // Diharapkan: hanya savepoint bagian dalam yang dibatalkan.
            }
        });

        $stored = $user->notifications()->get();
        $this->assertCount(1, $stored);
        $this->assertSame($first->booking_code, $stored->first()->data['booking_code']);
    }

    // ------------------------------------------------------------------ kegagalan kirim

    public function test_a_notification_failure_is_reported_and_does_not_undo_the_committed_work(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Pending);
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Kanal notifikasi gagal.');
        });
        Exceptions::fake();

        DB::transaction(function () use ($appointment): void {
            Appointment::query()->whereKey($appointment->id)->update(['complaint' => 'Tetap tersimpan']);

            $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Pending);
        });

        $this->assertSame('Tetap tersimpan', Appointment::query()->whereKey($appointment->id)->value('complaint'));
        $this->assertSame(0, $user->notifications()->count());
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Kanal notifikasi gagal.');
    }

    public function test_a_mail_failure_is_reported_and_the_database_notification_is_kept(): void
    {
        $user = $this->createPatient();
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed);
        Event::listen(NotificationSending::class, function (NotificationSending $event): void {
            if ($event->channel === 'mail') {
                throw new RuntimeException('Surel gagal dikirim.');
            }
        });
        Exceptions::fake();

        $this->notifier()->statusChanged($appointment, AppointmentStatus::Pending, AppointmentStatus::Confirmed);

        $this->assertSame(1, $user->notifications()->count(), 'Kanal database dikirim lebih dulu dan tetap tersimpan.');
        $this->assertSame(0, $this->sentMailCount());
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Surel gagal dikirim.');
    }

    public function test_relations_are_loaded_by_the_notifier_so_strict_mode_never_blocks_a_collection_member(): void
    {
        $user = $this->createPatient();
        $first = $this->appointmentFor($user, AppointmentStatus::Pending);
        $second = $this->appointmentFor($user, AppointmentStatus::Pending);
        Exceptions::fake();

        // Koleksi berisi lebih dari satu model: lazy loading dilarang mode ketat, jadi relasi harus dimuat eksplisit.
        $bare = Appointment::query()->whereIn('id', [$first->id, $second->id])->get();
        $this->assertCount(2, $bare);

        foreach ($bare as $appointment) {
            $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Pending);
        }

        Exceptions::assertNothingReported();
        $this->assertSame(2, $user->notifications()->count());
    }

    // ------------------------------------------------------------------ Review Focus 5: tanpa data sensitif

    public function test_no_notification_carries_bpjs_policy_complaint_address_phone_or_birth_date(): void
    {
        $user = $this->createPatient([], [
            'phone' => '081355577788',
            'address' => 'Jl. Rahasia Kenanga No. 99 Tangerang',
            'bpjs_number' => '0009900000077',
        ]);
        $profile = $user->patientProfile;
        $appointment = $this->appointmentFor($user, AppointmentStatus::Confirmed, [
            'complaint' => 'Nyeri dada hebat sejak semalam',
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => '0009900000088',
            'payment_reference_last4' => '0088',
        ]);

        $this->notifier()->statusChanged($appointment, null, AppointmentStatus::Confirmed);
        $this->notifier()->statusChanged($appointment, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'Berhalangan hadir');
        $this->notifier()->scheduleChanged($appointment, '08.00–12.00', '10.00–14.00');

        $dump = (string) json_encode(DB::table('notifications')->pluck('data')->all());
        foreach ([
            new AppointmentStatusChangedNotification($appointment, null, AppointmentStatus::Confirmed),
            new AppointmentStatusChangedNotification($appointment, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'Berhalangan hadir'),
            new AppointmentScheduleChangedNotification($appointment, '08.00–12.00', '10.00–14.00'),
        ] as $notification) {
            $mail = $notification->toMail($user);
            $dump .= "\n".$mail->subject."\n".$this->mailLines($mail)."\n".$mail->render();
        }

        $this->assertSame(3, $user->notifications()->count());

        foreach ([
            '0009900000077',
            '0009900000088',
            'Nyeri dada',
            'Rahasia Kenanga',
            '081355577788',
            $profile->birth_date->toDateString(),
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $dump, "Notifikasi memuat: {$forbidden}");
        }
    }
}
