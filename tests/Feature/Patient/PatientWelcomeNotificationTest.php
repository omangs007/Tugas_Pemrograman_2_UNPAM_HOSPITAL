<?php

namespace Tests\Feature\Patient;

use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * F5-R25 / NTF-08: email selamat datang hanya lewat mail, berbahasa Indonesia, tanpa data pribadi dan tanpa
 * tautan verifikasi (K-22 = a). F5-R17 (bagian T2): terjemahan templat mail bawaan Laravel di lang/id.json.
 */
class PatientWelcomeNotificationTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('id');
        $this->ensureLoginRoute();
    }

    /** Route `login` baru dibuat T3; sebelum itu dipasang route pengganti agar route('login') dapat dibangun. */
    private function ensureLoginRoute(): void
    {
        if (! Route::has('login')) {
            Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function recipient(): User
    {
        return $this->createPatient(
            ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            [
                'birth_date' => '1987-06-15',
                'address' => 'Jl. Rahasia No. 99, Pamulang',
                'phone' => '081234567890',
                'bpjs_number' => '0009900000011',
            ],
        );
    }

    /** @return list<string> */
    private function forbiddenFragments(User $user): array
    {
        return [
            '081234567890',
            'Jl. Rahasia',
            '1987-06-15',
            '15 Juni 1987',
            '0009900000011',
            'BPJS',
            (string) $user->patientProfile->patient_number,
            'verifikasi',
            'Verifikasi',
            'verify',
            'Verify',
        ];
    }

    public function test_it_is_delivered_by_mail_only(): void
    {
        $user = $this->recipient();

        $this->assertSame(['mail'], (new PatientWelcomeNotification)->via($user));
    }

    public function test_it_is_not_queued(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new PatientWelcomeNotification);
    }

    public function test_subject_greeting_lines_and_action_are_indonesian(): void
    {
        $user = $this->recipient();
        $app = (string) config('app.name');

        $mail = (new PatientWelcomeNotification)->toMail($user);

        $this->assertSame("Selamat Datang di {$app}", $mail->subject);
        $this->assertSame('Halo, Siti Rahma!', $mail->greeting);
        $this->assertSame([
            "Akun pasien Anda di {$app} telah berhasil dibuat.",
            'Masuk ke akun Anda untuk melihat dan memperbarui profil.',
            'Pendaftaran kunjungan rawat jalan dapat dilakukan melalui menu Daftar Kunjungan setelah fitur tersebut tersedia, atau langsung di loket pendaftaran.',
        ], $mail->introLines);
        $this->assertSame([], $mail->outroLines);
        $this->assertSame('Masuk ke Akun', $mail->actionText);
    }

    public function test_the_action_points_to_the_login_route(): void
    {
        $mail = (new PatientWelcomeNotification)->toMail($this->recipient());

        $this->assertSame(route('login'), $mail->actionUrl);
    }

    public function test_the_site_name_comes_from_the_config(): void
    {
        config(['app.name' => 'RS Uji Coba']);

        $mail = (new PatientWelcomeNotification)->toMail($this->recipient());
        $html = (string) $mail->render();

        $this->assertSame('Selamat Datang di RS Uji Coba', $mail->subject);
        $this->assertStringContainsString('Akun pasien Anda di RS Uji Coba telah berhasil dibuat.', $html);
    }

    public function test_the_html_mail_uses_indonesian_template_strings(): void
    {
        $html = (string) (new PatientWelcomeNotification)->toMail($this->recipient())->render();

        foreach (['Halo, Siti Rahma!', 'Salam,', 'Hak cipta dilindungi.', 'Jika tombol', 'ke peramban web Anda', 'Masuk ke Akun'] as $expected) {
            $this->assertStringContainsString($expected, $html, "Teks Indonesia hilang: {$expected}");
        }

        foreach (['Hello', 'Regards', 'All rights reserved', "If you're having trouble", 'Whoops'] as $english) {
            $this->assertStringNotContainsString($english, $html, "Teks Inggris bocor: {$english}");
        }
    }

    public function test_the_mail_carries_no_personal_data_or_verification_wording(): void
    {
        $user = $this->recipient();
        $mail = (new PatientWelcomeNotification)->toMail($user);
        $html = (string) $mail->render();
        $plain = implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines, $mail->actionText, $mail->actionUrl]);

        foreach ($this->forbiddenFragments($user) as $fragment) {
            $this->assertStringNotContainsString($fragment, $html, "HTML memuat: {$fragment}");
            $this->assertStringNotContainsString($fragment, $plain, "Baris pesan memuat: {$fragment}");
        }
    }

    public function test_the_recipient_name_is_escaped_in_the_html(): void
    {
        $user = $this->createPatient(['name' => '<script>alert(1)</script> & Co']);

        $html = (string) (new PatientWelcomeNotification)->toMail($user)->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; Co', $html);
    }

    public function test_a_real_delivery_through_the_array_mailer_is_indonesian_and_stays_out_of_the_database(): void
    {
        $user = $this->recipient();

        $user->notify(new PatientWelcomeNotification);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Selamat Datang di '.config('app.name'), $email->getSubject());
        $this->assertSame('siti@contoh.test', $email->getTo()[0]->getAddress());

        foreach (['HTML' => (string) $email->getHtmlBody(), 'teks biasa' => (string) $email->getTextBody()] as $label => $body) {
            $this->assertStringContainsString('Halo, Siti Rahma!', $body, $label);
            $this->assertStringContainsString('Salam,', $body, $label);
            $this->assertStringContainsString('Hak cipta dilindungi.', $body, $label);
            $this->assertStringContainsString(route('login'), $body, $label);

            foreach ($this->forbiddenFragments($user) as $fragment) {
                $this->assertStringNotContainsString($fragment, $body, "{$label} memuat: {$fragment}");
            }
        }

        $this->assertSame(0, DB::table('notifications')->count(), 'Kanal database tidak dipakai.');
    }

    public function test_the_fake_records_a_single_mail_only_delivery(): void
    {
        Notification::fake();
        $user = $this->recipient();

        $user->notify(new PatientWelcomeNotification);

        Notification::assertSentToTimes($user, PatientWelcomeNotification::class, 1);
        Notification::assertSentTo(
            $user,
            PatientWelcomeNotification::class,
            fn (PatientWelcomeNotification $notification, array $channels): bool => $channels === ['mail'],
        );
    }

    // --- lang/id.json (F5-R17, bagian T2) ---

    public function test_the_json_translation_file_has_the_template_keys_in_order(): void
    {
        $raw = (string) file_get_contents(lang_path('id.json'));
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $expected = [
            'Hello!' => 'Halo!',
            'Whoops!' => 'Maaf!',
            'Regards,' => 'Salam,',
            "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:" => "Jika tombol \":actionText\" tidak berfungsi, salin dan tempel URL berikut\nke peramban web Anda:",
            'All rights reserved.' => 'Hak cipta dilindungi.',
        ];

        $this->assertSame(array_keys($expected), array_slice(array_keys($decoded), 0, 5), 'Urutan kunci mengikuti F5-R17.');

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $decoded[$key], 'Terjemahan untuk: '.$key);
        }

        $this->assertStringStartsWith("{\n    \"Hello!\"", $raw, 'Indentasi 4 spasi.');
        $this->assertStringEndsWith("}\n", $raw, 'Diakhiri satu baris baru.');
        $this->assertStringNotContainsString("\r", $raw, 'Akhir baris LF.');
    }

    public function test_the_default_template_strings_are_translated(): void
    {
        $this->assertSame('Halo!', __('Hello!'));
        $this->assertSame('Maaf!', __('Whoops!'));
        $this->assertSame('Salam,', __('Regards,'));
        $this->assertSame('Hak cipta dilindungi.', __('All rights reserved.'));
        $this->assertSame(
            "Jika tombol \"Masuk ke Akun\" tidak berfungsi, salin dan tempel URL berikut\nke peramban web Anda:",
            __(
                "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:",
                ['actionText' => 'Masuk ke Akun'],
            ),
        );
    }
}
