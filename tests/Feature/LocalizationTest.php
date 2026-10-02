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
