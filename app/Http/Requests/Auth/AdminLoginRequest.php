<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginRequest extends FormRequest
{
    /** Hash dummy (hasher terkonfigurasi, biaya sama dengan BCRYPT_ROUNDS), dibuat sekali per proses. */
    private static ?string $dummyHash = null;

    public function authorize(): bool
    {
        return true;
    }

    /** Email dinormalkan (trim + huruf kecil) sebelum validasi dan autentikasi (Review Focus #4). */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password tidak valid.',
            'password.max' => 'Password maksimal 255 karakter.',
        ];
    }

    /**
     * Autentikasi hanya untuk akun staf aktif, tanpa "ingat saya". Semua kegagalan memakai pesan generik
     * (akun tak terdaftar, password salah, akun pasien, akun nonaktif); alasan sebenarnya hanya masuk audit.
     */
    public function authenticate(AuditLogger $audit): User
    {
        $email = (string) $this->validated('email');

        $credentials = [
            'email' => $email,
            'password' => (string) $this->input('password'),
            'account_type' => AccountType::Staff->value,
            'is_active' => true,
        ];

        if (Auth::attempt($credentials)) {
            /** @var User $user */
            $user = Auth::user();

            return $user;
        }

        $this->recordFailure($audit, $email, (string) $credentials['password']);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }

    private function recordFailure(AuditLogger $audit, string $email, string $password): void
    {
        $account = User::query()->where('email', $email)->first();

        $reason = match (true) {
            $account === null => 'bad_credentials',
            $account->account_type !== AccountType::Staff => 'not_staff',
            ! $account->is_active => 'inactive',
            default => 'bad_credentials',
        };

        $staff = $account !== null && $account->isStaff() ? $account : null;

        // Auth::attempt() tidak menghitung bcrypt bila tidak ada akun staf aktif yang cocok; samakan biaya waktunya
        // agar akun staf aktif tidak dapat dibedakan lewat durasi respons. Password tidak disimpan, dicatat, maupun diaudit.
        if ($account === null || ! $account->isStaff() || ! $account->is_active) {
            Hash::check($password, self::dummyHash());
        }

        $audit->log(
            'auth.staff_login_failed',
            $staff ?? new User,
            null,
            ['email' => $email, 'reason' => $reason],
            'Percobaan masuk ke panel admin gagal.',
            $staff !== null ? Actor::staff($staff) : Actor::system(),
        );
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('dummy-password-for-timing-equalisation');
    }
}
