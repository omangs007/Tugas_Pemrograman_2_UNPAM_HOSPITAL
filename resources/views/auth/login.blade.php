<x-layouts.auth title="Masuk" heading="Masuk ke Akun Pasien" subtitle="Masuk untuk mengelola profil dan pendaftaran kunjungan Anda.">
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" :autofocus="! $errors->any()" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
        <x-form.checkbox name="remember" label="Ingat saya" />

        <x-loading-button class="w-100 mt-2" loading-text="Memproses…">Masuk</x-loading-button>
    </form>

    <x-slot:footer>
        <p class="mb-0">Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>
    </x-slot:footer>
</x-layouts.auth>
