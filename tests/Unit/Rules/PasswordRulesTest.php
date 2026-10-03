<?php

namespace Tests\Unit\Rules;

use App\Rules\PasswordRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordRulesTest extends TestCase
{
    public function test_default_bundle_matches_the_documented_rules(): void
    {
        $rules = PasswordRules::default();

        $this->assertCount(4, $rules);
        $this->assertSame('required', $rules[0]);
        $this->assertSame('confirmed', $rules[1]);
        $this->assertSame('max:64', $rules[2]);
        $this->assertInstanceOf(Password::class, $rules[3]);
    }

    /**
     * @return array<string, array{string, string|null, bool}>
     */
    public static function cases(): array
    {
        return [
            'valid' => ['Abcdef12', 'Abcdef12', true],
            'valid dengan simbol' => ['Rahasia123!', 'Rahasia123!', true],
            'password umum diterima karena tanpa pemeriksaan kebocoran (tanpa jaringan)' => ['Password123', 'Password123', true],
            'tepat 64 karakter' => [str_repeat('Aa1', 21).'A', str_repeat('Aa1', 21).'A', true],
            '65 karakter' => [str_repeat('Aa1', 21).'AB', str_repeat('Aa1', 21).'AB', false],
            'tanpa angka' => ['Abcdefgh', 'Abcdefgh', false],
            'tanpa huruf' => ['12345678', '12345678', false],
            'kurang dari 8 karakter' => ['Ab1', 'Ab1', false],
            'konfirmasi berbeda' => ['Abcdef12', 'Abcdef13', false],
            'tanpa konfirmasi' => ['Abcdef12', null, false],
            'kosong' => ['', '', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_default_bundle_accepts_and_rejects_the_expected_values(string $password, ?string $confirmation, bool $valid): void
    {
        $data = ['password' => $password];

        if ($confirmation !== null) {
            $data['password_confirmation'] = $confirmation;
        }

        $this->assertSame($valid, Validator::make($data, ['password' => PasswordRules::default()])->passes());
    }
}
