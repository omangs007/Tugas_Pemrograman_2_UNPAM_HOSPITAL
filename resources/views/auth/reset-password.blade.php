<x-layouts.auth title="Reset Password" heading="Atur Ulang Password" subtitle="Buat password baru untuk akun Anda.">
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" :value="$email" :readonly="$email !== ''" :autofocus="$email === '' && ! $errors->any()" />
        <x-form.input name="password" label="Password baru" type="password" required autocomplete="new-password" help="Minimal 8 karakter, berisi huruf dan angka." :autofocus="$email !== '' && ! $errors->any()" />
        <x-form.input name="password_confirmation" label="Konfirmasi password baru" type="password" required autocomplete="new-password" />

        <x-loading-button class="w-100 mt-2" loading-text="Menyimpan…">Simpan Password Baru</x-loading-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('password.request') }}">Minta tautan baru</a>
    </x-slot:footer>
</x-layouts.auth>
