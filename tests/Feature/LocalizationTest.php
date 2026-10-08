<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_application_timezone_is_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertSame('Asia/Jakarta', now()->getTimezone()->getName());
    }

    public function test_application_locale_is_indonesian(): void
    {
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('id_ID', config('app.faker_locale'));
    }

    public function test_validation_messages_are_indonesian_with_translated_attributes(): void
    {
        $errors = Validator::make([], ['full_name' => 'required', 'email' => 'required|email'])->errors();

        $this->assertSame('Kolom nama lengkap wajib diisi.', $errors->first('full_name'));
        $this->assertSame('Kolom email wajib diisi.', $errors->first('email'));
    }

    public function test_auth_and_password_messages_are_generic_indonesian(): void
    {
        $this->assertSame('Email atau password salah.', __('auth.failed'));
        $this->assertSame(__('passwords.sent'), __('passwords.user'));

        // F5-R16: throttle broker (60 detik) sengaja sama dengan `sent` agar tidak membocorkan email terdaftar.
        $this->assertSame(__('passwords.sent'), __('passwords.throttled'));
        $this->assertSame('Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.', __('passwords.sent'));
        $this->assertSame('Password berhasil diubah. Silakan masuk dengan password baru.', __('passwords.reset'));
        $this->assertSame('Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.', __('passwords.token'));
    }

    public function test_json_translations_cover_the_password_reset_notification_and_the_mail_template(): void
    {
        $raw = (string) file_get_contents(lang_path('id.json'));
        $this->assertStringEndsWith("}\n", $raw);

        /** @var array<string, string> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        // F5-R17: T2 menerjemahkan templat mail bawaan, T5 menambah teks notifikasi reset password.
        $expected = [
            'Hello!' => 'Halo!',
            'Whoops!' => 'Maaf!',
            'Regards,' => 'Salam,',
            'All rights reserved.' => 'Hak cipta dilindungi.',
            'Reset Password Notification' => 'Atur Ulang Password',
            'You are receiving this email because we received a password reset request for your account.' => 'Anda menerima email ini karena ada permintaan untuk mengatur ulang password akun Anda.',
            'Reset Password' => 'Atur Ulang Password',
            'This password reset link will expire in :count minutes.' => 'Tautan ini berlaku selama :count menit.',
            'If you did not request a password reset, no further action is required.' => 'Jika Anda tidak meminta pengaturan ulang password, abaikan email ini.',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $decoded[$key] ?? null, "Kunci '{$key}' di lang/id.json.");
            $this->assertSame(str_replace(':count', '60', $value), __($key, ['count' => 60]));
        }
    }

    /** @return array<string, array{string}> */
    public static function languageFiles(): array
    {
        return [
            'auth' => ['auth'],
            'pagination' => ['pagination'],
            'passwords' => ['passwords'],
            'validation' => ['validation'],
        ];
    }

    #[DataProvider('languageFiles')]
    public function test_indonesian_file_has_every_english_key(string $file): void
    {
        $english = $this->flattenKeys(require lang_path("en/{$file}.php"));
        $indonesian = $this->flattenKeys(require lang_path("id/{$file}.php"));

        $this->assertSame([], array_values(array_diff($english, $indonesian)), "Kunci hilang di lang/id/{$file}.php");
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return list<string>
     */
    private function flattenKeys(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            if ($prefix === '' && in_array($key, ['custom', 'attributes'], true)) {
                continue;
            }

            $keys = is_array($value)
                ? [...$keys, ...$this->flattenKeys($value, "{$prefix}{$key}.")]
                : [...$keys, "{$prefix}{$key}"];
        }

        return $keys;
    }
}
